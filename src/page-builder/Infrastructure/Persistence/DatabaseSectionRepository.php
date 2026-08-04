<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\Persistence;

use UncannyPageBuilder\Application\Concurrency\PageSourceMutation;
use UncannyPageBuilder\Domain\Compiler\CompiledOutput;
use UncannyPageBuilder\Domain\Concurrency\SourceGenerationStoreInterface;
use UncannyPageBuilder\Domain\Exception\StaleSourceGenerationException;
use UncannyPageBuilder\Domain\Exception\SectionNotFoundException;
use UncannyPageBuilder\Domain\Section\Section;
use UncannyPageBuilder\Domain\Section\SectionCollection;
use UncannyPageBuilder\Domain\Section\SectionRepositoryInterface;

final class DatabaseSectionRepository implements SectionRepositoryInterface
{
    private const META_COMPILED = '_uncanny_page_builder_compiled_css';

    public function __construct(
        private readonly ?SourceGenerationStoreInterface $sourceGenerations = null,
        private readonly ?PageSourceMutation $pageSource = null,
    ) {}

    private function table(): string
    {
        return SchemaManager::tableName();
    }

    public function findByPageId(int $pageId): SectionCollection
    {
        global $wpdb;
        $table = $this->table();

        /*
         * Read generation before and after the rows. Aggregate commits advance
         * the generation in the same transaction as row changes, so matching
         * values prove the collection came from one coherent page snapshot.
         */
        for ($attempt = 0; $attempt < 3; $attempt++) {
            $before = $this->generationStore()->pageGeneration($pageId);
            $rows = $wpdb->get_results(
                $wpdb->prepare("SELECT * FROM {$table} WHERE page_id = %d ORDER BY position", $pageId)
            );
            $after = $this->generationStore()->pageGeneration($pageId);

            if ($before !== $after) {
                continue;
            }

            if (empty($rows)) {
                return SectionCollection::fromPersisted([], $before);
            }

            $sections = array_map(fn(object $row) => Section::fromRow($row), $rows);
            return SectionCollection::fromPersisted($sections, $before);
        }

        throw new StaleSourceGenerationException('page', $before ?? 0, $after ?? 0);
    }

    public function findById(int $sectionId): Section
    {
        global $wpdb;
        $table = $this->table();

        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $sectionId)
        );

        if ($row === null) {
            throw SectionNotFoundException::withId($sectionId);
        }

        return Section::fromRow($row);
    }

    public function save(int $pageId, SectionCollection $sections, CompiledOutput $compiled): void
    {
        $this->saveSections($pageId, $sections, $compiled);
    }

    public function saveCompiled(int $pageId, CompiledOutput $compiled): void
    {
        global $wpdb;

        $css = $compiled->minifiedCss();
        $wpdb->last_error = '';
        update_post_meta($pageId, self::META_COMPILED, wp_slash($css));

        /*
         * update_post_meta() returns false both for an unchanged value and for
         * a failed query. Only the latter is an error; capture it before the
         * verification read can replace wpdb's diagnostic state.
         */
        if ($wpdb->last_error !== '') {
            throw new WordPressWriteVerificationException('Failed to persist compiled page CSS.');
        }

        // WordPress unslashes metadata inputs and its boolean result cannot
        // prove the desired bytes reached storage. Read the transactional
        // table directly so an object-cache hit cannot hide stale output.
        if (!$this->compiledCssWasPersisted($pageId, $css)) {
            throw new WordPressWriteVerificationException('Failed to persist compiled page CSS.');
        }
    }

    public function replaceAll(int $pageId, SectionCollection $sections, CompiledOutput $compiled): void
    {
        $nextGeneration = $sections->generation() + 1;
        $this->commitPage(
            $pageId,
            $sections->generation(),
            function () use ($pageId, $sections, $compiled): void {
                global $wpdb;
                $table = $this->table();

                if ($wpdb->delete($table, ['page_id' => $pageId], ['%d']) === false) {
                    throw new \RuntimeException('Failed to delete sections for replace-all.');
                }

                $sections->reindex();
                foreach ($sections->all() as $section) {
                    $data = [
                        'page_id'  => $pageId,
                        'position' => $section->position(),
                        'name'     => $section->name(),
                        'html'     => $section->content()->html(),
                        'css'      => $section->content()->css(),
                        'element_styles' => $section->content()->elementStyles()->toJson(),
                    ];
                    $formats = ['%d', '%d', '%s', '%s', '%s', '%s'];

                    if ($section->id() !== null) {
                        $data = ['id' => $section->id()] + $data;
                        array_unshift($formats, '%d');
                    }

                    if ($wpdb->insert($table, $data, $formats) === false) {
                        throw new \RuntimeException("Failed to insert section '{$section->name()}'.");
                    }

                    if ($section->id() === null) {
                        $section->assignId((int) $wpdb->insert_id);
                    }
                }

                $this->saveCompiled($pageId, $compiled);
            },
        );
        $sections->markPersistedAtGeneration($nextGeneration);
    }

    public function hasSections(int $pageId): bool
    {
        global $wpdb;
        $table = $this->table();

        $count = (int) $wpdb->get_var(
            $wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE page_id = %d", $pageId)
        );

        return $count > 0;
    }

    public function pageExists(int $pageId): bool
    {
        return get_post($pageId) !== null;
    }

    public function markAsEnginePage(int $pageId): void
    {
        $ownership = new WpPageOwnershipRepository();
        $ownership->markActive($pageId);
        $ownership->markOwned($pageId);
    }

    public function isOwnedPage(int $pageId): bool
    {
        return (new WpPageOwnershipRepository())->isOwned($pageId);
    }

    public function markAsOwned(int $pageId): void
    {
        (new WpPageOwnershipRepository())->markOwned($pageId);
    }

    public function getPermalink(int $pageId): string
    {
        return (string) get_permalink($pageId);
    }

    // ── Internals ──────────────────────────────

    private function compiledCssWasPersisted(int $pageId, string $expectedCss): bool
    {
        global $wpdb;
        $postmetaTable = isset($wpdb->postmeta) ? (string) $wpdb->postmeta : (string) $wpdb->prefix . 'postmeta';
        $wpdb->last_error = '';
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT meta_value FROM {$postmetaTable}
             WHERE post_id = %d AND meta_key = %s
             ORDER BY meta_id DESC LIMIT 1",
            $pageId,
            self::META_COMPILED,
        ));

        if ($wpdb->last_error !== '') {
            throw new WordPressWriteVerificationException('Failed to verify compiled page CSS persistence.');
        }

        /*
         * wpdb::get_var() returns null for both a missing row and an existing
         * empty string. An empty compile is valid for a page with no sections,
         * so verify row existence separately from its value.
         */
        if (!is_object($row) || !property_exists($row, 'meta_value')) {
            return false;
        }

        $stored = $row->meta_value;
        if (!is_string($stored)) {
            return false;
        }

        $decoded = function_exists('maybe_unserialize') ? maybe_unserialize($stored) : $stored;

        return is_string($decoded) && hash_equals($expectedCss, $decoded);
    }

    private function saveSections(
        int $pageId,
        SectionCollection $sections,
        CompiledOutput $compiled,
    ): void {
        $sections->reindex();
        $nextGeneration = $sections->generation() + 1;
        $this->commitPage(
            $pageId,
            $sections->generation(),
            function () use ($pageId, $sections, $compiled): void {
                global $wpdb;
                $table = $this->table();

                // Upsert each section.
                foreach ($sections->all() as $section) {
                    if ($section->isNew()) {
                        if (
                            $wpdb->insert($table, [
                            'page_id'  => $pageId,
                            'position' => $section->position(),
                            'name'     => $section->name(),
                            'html'     => $section->content()->html(),
                            'css'      => $section->content()->css(),
                            'element_styles' => $section->content()->elementStyles()->toJson(),
                            ], ['%d', '%d', '%s', '%s', '%s', '%s']) === false
                        ) {
                            throw new \RuntimeException("Failed to insert section '{$section->name()}'.");
                        }

                        $section->assignId((int) $wpdb->insert_id);
                    } else {
                        $updated = $wpdb->update(
                            $table,
                            [
                                'position' => $section->position(),
                                'name'     => $section->name(),
                                'html'     => $section->content()->html(),
                                'css'      => $section->content()->css(),
                                'element_styles' => $section->content()->elementStyles()->toJson(),
                            ],
                            ['id' => $section->id()],
                            ['%d', '%s', '%s', '%s', '%s'],
                            ['%d']
                        );

                        if ($updated === false) {
                            throw new \RuntimeException("Failed to update section {$section->id()}.");
                        }
                    }
                }

                // Keep the table aligned with the saved page snapshot so deletes do
                // not silently resurrect on the next read.
                $this->deleteMissingSections($pageId, $sections);

                $this->saveCompiled($pageId, $compiled);
            },
        );
        $sections->markPersistedAtGeneration($nextGeneration);
    }

    private function deleteMissingSections(int $pageId, SectionCollection $sections): void
    {
        global $wpdb;

        $persistedIds = [];
        foreach ($sections->all() as $section) {
            $sectionId = (int) $section->id();
            if ($sectionId > 0) {
                $persistedIds[$sectionId] = true;
            }
        }

        $rows = $wpdb->get_results(
            $wpdb->prepare("SELECT id FROM {$this->table()} WHERE page_id = %d", $pageId)
        );

        foreach ($rows as $row) {
            $rowId = isset($row->id) ? (int) $row->id : 0;
            if ($rowId <= 0 || isset($persistedIds[$rowId])) {
                continue;
            }

            if ($wpdb->delete($this->table(), ['id' => $rowId, 'page_id' => $pageId], ['%d', '%d']) === false) {
                throw new \RuntimeException("Failed to delete section {$rowId}.");
            }
        }
    }

    private function generationStore(): SourceGenerationStoreInterface
    {
        return $this->sourceGenerations ?? new WordPressSourceGenerationStore();
    }

    /**
     * @param callable(): mixed $write
     */
    private function commitPage(int $pageId, int $expectedGeneration, callable $write): mixed
    {
        if ($this->pageSource instanceof PageSourceMutation) {
            return $this->pageSource->runExpected($pageId, $expectedGeneration, $write);
        }

        return $this->generationStore()->commitPage($pageId, $expectedGeneration, $write);
    }
}
