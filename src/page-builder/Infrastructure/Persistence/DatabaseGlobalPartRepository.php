<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\Persistence;

use UncannyPageBuilder\Application\Concurrency\GlobalSourceMutation;
use UncannyPageBuilder\Domain\Compiler\CompiledOutput;
use UncannyPageBuilder\Domain\Concurrency\SourceGenerationStoreInterface;
use UncannyPageBuilder\Domain\Exception\StaleSourceGenerationException;
use UncannyPageBuilder\Domain\GlobalPart\GlobalPartCreationCleanupInterface;
use UncannyPageBuilder\Domain\GlobalPart\GlobalPartRepositoryInterface;
use UncannyPageBuilder\Domain\GlobalPart\GlobalPartSnapshotRepositoryInterface;
use UncannyPageBuilder\Domain\GlobalPart\GlobalPartSourceUpdateRepositoryInterface;
use UncannyPageBuilder\Domain\GlobalPart\GlobalPartType;
use UncannyPageBuilder\Domain\Section\Section;
use UncannyPageBuilder\Domain\Section\SectionCollection;
use UncannyPageBuilder\Infrastructure\WordPress\KsesSanitizer;

final class DatabaseGlobalPartRepository implements
    GlobalPartSnapshotRepositoryInterface,
    GlobalPartSourceUpdateRepositoryInterface,
    GlobalPartCreationCleanupInterface
{
    private const CPT           = 'upb_global_part';
    private const META_TYPE     = '_upb_global_part_type';
    private const META_COMPILED = '_uncanny_ai_compiled_css';

    /** @var array<int, array<string, mixed>|null> */
    private array $partsById = [];

    /** @var array<string, array<int, array<string, mixed>>> */
    private array $partsByType = [];

    /** @var array<string, array<string, mixed>|null> */
    private array $firstPartByType = [];

    private ?int $cacheGeneration = null;

    public function __construct(
        private readonly ?KsesSanitizer $ksesSanitizer = null,
        private readonly ?SourceGenerationStoreInterface $sourceGenerations = null,
        private readonly ?GlobalSourceMutation $globalSource = null,
    ) {}

    private function table(): string
    {
        return SchemaManager::globalSectionsTableName();
    }

    public function createPost(string $title, GlobalPartType $type): int
    {
        $store = $this->generationStore();
        $postId = $this->commitGlobal(
            $store->globalGeneration(),
            static function () use ($title, $type): int {
                $postId = wp_insert_post([
                    'post_type'   => self::CPT,
                    'post_title'  => $title,
                    'post_status' => 'publish',
                ], true);

                if (is_wp_error($postId) || (int) $postId <= 0) {
                    throw new \RuntimeException(
                        is_wp_error($postId)
                            ? $postId->get_error_message()
                            : 'WordPress did not return a global part ID.',
                    );
                }

                $postId = (int) $postId;
                $typeMetaUpdated = update_post_meta($postId, self::META_TYPE, $type->value);
                $storedType = (string) get_post_meta($postId, self::META_TYPE, true);
                if ($typeMetaUpdated === false && $storedType !== $type->value) {
                    throw new \RuntimeException('Global part type initialization failed.');
                }

                return $postId;
            },
        );
        $this->resetCaches();

        return (int) $postId;
    }

    public function removeCreatedGlobalPart(int $globalPartId): void
    {
        if ($globalPartId <= 0) {
            throw new \InvalidArgumentException('global_part_id must be positive.');
        }

        $store = $this->generationStore();

        for ($attempt = 0; $attempt < 3; $attempt++) {
            $generation = $store->globalGeneration();

            try {
                $this->commitGlobal($generation, function () use ($globalPartId): void {
                    global $wpdb;

                    if ($wpdb->delete($this->table(), ['global_part_id' => $globalPartId], ['%d']) === false) {
                        throw new \RuntimeException('Failed to remove sections for the incomplete global part.');
                    }

                    $deleted = wp_delete_post($globalPartId, true);
                    if ($deleted === false || is_wp_error($deleted)) {
                        throw new \RuntimeException('Failed to remove the incomplete global part post.');
                    }
                });

                $this->resetCaches();

                return;
            } catch (StaleSourceGenerationException $exception) {
                if ($attempt === 2) {
                    throw $exception;
                }
            }
        }
    }

    public function saveSections(int $globalPartId, SectionCollection $sections): void
    {
        $nextGeneration = $sections->generation() + 1;
        $this->commitGlobal(
            $sections->generation(),
            fn(): mixed => $this->replaceSectionRows($globalPartId, $sections),
        );
        $sections->markPersistedAtGeneration($nextGeneration);
        $this->resetCaches();
    }

    public function saveSnapshot(
        int $globalPartId,
        SectionCollection $sections,
        CompiledOutput $compiled,
    ): void {
        $nextGeneration = $sections->generation() + 1;
        $this->commitGlobal(
            $sections->generation(),
            function () use ($globalPartId, $sections, $compiled): void {
                $this->replaceSectionRows($globalPartId, $sections);
                $this->persistCompiled($globalPartId, $compiled);
            },
        );

        $sections->markPersistedAtGeneration($nextGeneration);
        $this->resetCaches();
    }

    /**
     * Update only the canonical source row so legacy sibling rows retain their
     * exact stored IDs, positions, HTML, CSS, and element-style payloads.
     */
    public function saveSource(
        int $globalPartId,
        Section $source,
        CompiledOutput $compiled,
        int $expectedGeneration,
    ): void {
        if ($source->id() === null || $source->id() <= 0) {
            throw new \RuntimeException('A persisted global-part source row is required.');
        }

        $this->commitGlobal(
            $expectedGeneration,
            function () use ($globalPartId, $source, $compiled): void {
                $this->updateSourceRow($globalPartId, $source);
                $this->persistCompiled($globalPartId, $compiled);
            },
        );

        $this->resetCaches();
    }

    public function saveCompiled(int $globalPartId, CompiledOutput $compiled): void
    {
        $this->persistCompiled($globalPartId, $compiled);
        $this->resetCaches();
    }

    private function persistCompiled(int $globalPartId, CompiledOutput $compiled): void
    {
        $css = $compiled->minifiedCss();
        update_post_meta($globalPartId, self::META_COMPILED, wp_slash($css));
        if (!$this->compiledCssWasPersisted($globalPartId, $css)) {
            throw new \RuntimeException('Failed to persist compiled global-part CSS.');
        }

        $write = static fn (): mixed => wp_update_post([
            'ID' => $globalPartId,
            'post_content' => wp_slash($compiled->seoHtml()),
        ], true);

        $postResult = $this->ksesSanitizer instanceof KsesSanitizer
            ? $this->ksesSanitizer->runWithBuilderAllowlist($write)
            : $write();
        if (is_wp_error($postResult) || (int) $postResult <= 0) {
            $detail = is_wp_error($postResult)
                ? ': ' . $postResult->get_error_message()
                : '.';
            throw new \RuntimeException('Failed to persist compiled global-part HTML' . $detail);
        }
    }

    /**
     * Verify the exact database bytes instead of trusting update_post_meta's
     * ambiguous return value or a potentially stale object-cache entry.
     */
    private function compiledCssWasPersisted(int $globalPartId, string $expectedCss): bool
    {
        global $wpdb;
        $postmetaTable = isset($wpdb->postmeta) ? (string) $wpdb->postmeta : (string) $wpdb->prefix . 'postmeta';
        $stored = $wpdb->get_var($wpdb->prepare(
            "SELECT meta_value FROM {$postmetaTable}
             WHERE post_id = %d AND meta_key = %s
             ORDER BY meta_id DESC LIMIT 1",
            $globalPartId,
            self::META_COMPILED,
        ));

        if ($stored === null || $stored === false) {
            return false;
        }

        $decoded = function_exists('maybe_unserialize') ? maybe_unserialize($stored) : $stored;

        return is_string($decoded) && hash_equals($expectedCss, $decoded);
    }

    public function findByType(GlobalPartType $type): ?array
    {
        for ($attempt = 0; $attempt < 3; $attempt++) {
            $generation = $this->prepareReadGeneration();
            if (array_key_exists($type->value, $this->firstPartByType)) {
                return $this->firstPartByType[$type->value];
            }

            $posts = get_posts([
                'post_type'              => self::CPT,
                'numberposts'            => 1,
                'post_status'            => 'publish',
                'orderby'                => 'title',
                'order'                  => 'ASC',
                'meta_key'               => self::META_TYPE,
                'meta_value'             => $type->value,
                'update_post_meta_cache' => true,
            ]);

            $parts = $this->hydrateParts($posts, $type->value, $generation);
            if (!$this->finishConsistentRead($generation)) {
                continue;
            }
            $firstPart = $parts[0] ?? null;

            $this->firstPartByType[$type->value] = $firstPart;

            return $firstPart;
        }

        throw new StaleSourceGenerationException('global', $generation ?? 0, $this->generationStore()->globalGeneration());
    }

    public function findAllByType(GlobalPartType $type): array
    {
        for ($attempt = 0; $attempt < 3; $attempt++) {
            $generation = $this->prepareReadGeneration();
            if (array_key_exists($type->value, $this->partsByType)) {
                return $this->partsByType[$type->value];
            }

            $posts = get_posts([
                'post_type'              => self::CPT,
                'numberposts'            => -1,
                'post_status'            => 'publish',
                'orderby'                => 'title',
                'order'                  => 'ASC',
                'meta_key'               => self::META_TYPE,
                'meta_value'             => $type->value,
                'update_post_meta_cache' => true,
            ]);

            if (empty($posts)) {
                if (!$this->finishConsistentRead($generation)) {
                    continue;
                }
                $this->partsByType[$type->value] = [];
                $this->firstPartByType[$type->value] = null;
                return [];
            }

            $parts = $this->hydrateParts($posts, $type->value, $generation);
            if (!$this->finishConsistentRead($generation)) {
                continue;
            }

            $this->partsByType[$type->value] = $parts;
            $this->firstPartByType[$type->value] = $parts[0] ?? null;

            return $parts;
        }

        throw new StaleSourceGenerationException('global', $generation ?? 0, $this->generationStore()->globalGeneration());
    }

    public function findById(int $postId): ?array
    {
        for ($attempt = 0; $attempt < 3; $attempt++) {
            $generation = $this->prepareReadGeneration();
            if (array_key_exists($postId, $this->partsById)) {
                return $this->partsById[$postId];
            }

            $post = get_post($postId);
            if (!$post instanceof \WP_Post || $post->post_type !== self::CPT || $post->post_status !== 'publish') {
                if (!$this->finishConsistentRead($generation)) {
                    continue;
                }
                $this->partsById[$postId] = null;
                return null;
            }

            update_meta_cache('post', [$postId]);

            $parts = $this->hydrateParts([$post], null, $generation);
            if (!$this->finishConsistentRead($generation)) {
                continue;
            }

            return $parts[0] ?? null;
        }

        throw new StaleSourceGenerationException('global', $generation ?? 0, $this->generationStore()->globalGeneration());
    }

    /**
     * @param \WP_Post[] $posts
     * @return array<int, array<string, mixed>>
     */
    private function hydrateParts(array $posts, ?string $knownType = null, int $generation = 0): array
    {
        if ($posts === []) {
            return [];
        }

        $postIds = array_map(static fn(\WP_Post $post): int => (int) $post->ID, $posts);
        $sectionsByPostId = $this->loadSectionsForGlobalParts($postIds);

        $parts = [];
        foreach ($posts as $post) {
            $postId = (int) $post->ID;
            $part = [
                'post_id'  => $postId,
                'type'     => $knownType ?? (string) get_post_meta($postId, self::META_TYPE, true),
                'title'    => $post->post_title,
                'sections' => $sectionsByPostId[$postId] ?? [],
                'css'      => (string) (get_post_meta($postId, self::META_COMPILED, true) ?: ''),
                'generation' => $generation,
            ];

            $this->partsById[$postId] = $part;
            $parts[] = $part;
        }

        return $parts;
    }

    /**
     * @param int[] $postIds
     * @return array<int, array<int, array<string, mixed>>>
     */
    private function loadSectionsForGlobalParts(array $postIds): array
    {
        if ($postIds === []) {
            return [];
        }

        global $wpdb;
        $table = $this->table();

        $placeholders = implode(',', array_fill(0, count($postIds), '%d'));
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE global_part_id IN ({$placeholders}) ORDER BY global_part_id, position",
                ...$postIds
            )
        );

        $sectionsByPostId = [];
        foreach ($rows as $row) {
            $globalPartId = (int) $row->global_part_id;
            $sectionsByPostId[$globalPartId][] = [
                'id'       => (int) $row->id,
                'position' => (int) $row->position,
                'name'     => $row->name,
                'content'  => [
                    'html'           => $row->html,
                    'css'            => $row->css,
                    'element_styles' => \UncannyPageBuilder\Domain\DesignStyles\ElementStyleSheet::fromJson($row->element_styles ?? '')->toArray(),
                ],
            ];
        }

        return $sectionsByPostId;
    }

    private function insertSections(int $globalPartId, SectionCollection $sections): void
    {
        global $wpdb;
        $table = $this->table();

        $sections->reindex();
        foreach ($sections->all() as $section) {
            $data = [
                'global_part_id' => $globalPartId,
                'position'       => $section->position(),
                'name'           => $section->name(),
                'html'           => $section->content()->html(),
                'css'            => $section->content()->css(),
                'element_styles' => $section->content()->elementStyles()->toJson(),
            ];
            $formats = ['%d', '%d', '%s', '%s', '%s', '%s'];

            if ($section->id() !== null) {
                $data = ['id' => $section->id()] + $data;
                array_unshift($formats, '%d');
            }

            if ($wpdb->insert($table, $data, $formats) === false) {
                throw new \RuntimeException("Failed to insert global part section '{$section->name()}'.");
            }

            if ($section->id() === null) {
                $section->assignId((int) $wpdb->insert_id);
            }
        }
    }

    private function updateSourceRow(int $globalPartId, Section $source): void
    {
        global $wpdb;

        $elementStyles = $source->content()->elementStyles()->toJson();
        $updated = $wpdb->update(
            $this->table(),
            [
                'position'       => $source->position(),
                'name'           => $source->name(),
                'html'           => $source->content()->html(),
                'css'            => $source->content()->css(),
                'element_styles' => $elementStyles,
            ],
            [
                'id'             => $source->id(),
                'global_part_id' => $globalPartId,
            ],
            ['%d', '%s', '%s', '%s', '%s'],
            ['%d', '%d'],
        );

        if ($updated === false || !$this->sourceRowWasPersisted($globalPartId, $source, $elementStyles)) {
            throw new \RuntimeException('Failed to persist the canonical global-part source row.');
        }
    }

    private function sourceRowWasPersisted(
        int $globalPartId,
        Section $source,
        string $elementStyles,
    ): bool {
        global $wpdb;

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT id, global_part_id, position, name, html, css, element_styles
             FROM {$this->table()}
             WHERE id = %d AND global_part_id = %d
             LIMIT 1",
            $source->id(),
            $globalPartId,
        ));
        if (!is_object($row)) {
            return false;
        }

        return (int) ($row->id ?? 0) === $source->id()
            && (int) ($row->global_part_id ?? 0) === $globalPartId
            && (int) ($row->position ?? -1) === $source->position()
            && (string) ($row->name ?? '') === $source->name()
            && (string) ($row->html ?? '') === $source->content()->html()
            && (string) ($row->css ?? '') === $source->content()->css()
            && (string) ($row->element_styles ?? '') === $elementStyles;
    }

    private function resetCaches(): void
    {
        $this->partsById = [];
        $this->partsByType = [];
        $this->firstPartByType = [];
        $this->cacheGeneration = null;
    }

    private function replaceSectionRows(int $globalPartId, SectionCollection $sections): void
    {
        global $wpdb;

        if ($wpdb->delete($this->table(), ['global_part_id' => $globalPartId], ['%d']) === false) {
            throw new \RuntimeException('Failed to delete global part sections.');
        }

        $this->insertSections($globalPartId, $sections);
    }

    private function prepareReadGeneration(): int
    {
        $generation = $this->generationStore()->globalGeneration();
        if ($this->cacheGeneration !== $generation) {
            $this->resetCaches();
            $this->cacheGeneration = $generation;
        }

        return $generation;
    }

    private function finishConsistentRead(int $generation): bool
    {
        if ($this->generationStore()->globalGeneration() === $generation) {
            return true;
        }

        $this->resetCaches();

        return false;
    }

    /**
     * @param callable(): mixed $write
     */
    private function commitGlobal(int $expectedGeneration, callable $write): mixed
    {
        if ($this->globalSource instanceof GlobalSourceMutation) {
            return $this->globalSource->runExpected($expectedGeneration, $write);
        }

        return $this->generationStore()->commitGlobal($expectedGeneration, $write);
    }

    private function generationStore(): SourceGenerationStoreInterface
    {
        return $this->sourceGenerations ?? new WordPressSourceGenerationStore();
    }
}
