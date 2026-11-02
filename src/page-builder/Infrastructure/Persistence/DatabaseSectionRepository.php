<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\Persistence;

use UncannyPageBuilder\Application\Concurrency\PageSourceMutation;
use UncannyPageBuilder\Domain\Compiler\CompiledOutput;
use UncannyPageBuilder\Domain\Compiler\ShadowCompiler;
use UncannyPageBuilder\Domain\Concurrency\SourceGenerationStoreInterface;
use UncannyPageBuilder\Domain\Exception\StaleSourceGenerationException;
use UncannyPageBuilder\Domain\Exception\SectionNotFoundException;
use UncannyPageBuilder\Domain\Section\Section;
use UncannyPageBuilder\Domain\Section\SectionCollection;
use UncannyPageBuilder\Domain\Section\SectionRepositoryInterface;
use UncannyPageBuilder\Domain\Section\SectionRootIdentityRemapper;
use UncannyPageBuilder\Infrastructure\Compiler\CssMinifier;

final class DatabaseSectionRepository implements SectionRepositoryInterface
{
    private const META_COMPILED = '_uncanny_page_builder_compiled_css';

    private readonly ShadowCompiler $compiler;

    public function __construct(
        private readonly ?SourceGenerationStoreInterface $sourceGenerations = null,
        private readonly ?PageSourceMutation $pageSource = null,
        ?ShadowCompiler $compiler = null,
    ) {
        $this->compiler = $compiler ?? new ShadowCompiler(new CssMinifier());
    }

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
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table; live state read, must not be cached.
            $rows = $wpdb->get_results(
                $wpdb->prepare('SELECT * FROM %i WHERE page_id = %d ORDER BY position', $table, $pageId)
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

        throw new StaleSourceGenerationException('page', (int) ($before ?? 0), (int) ($after ?? 0));
    }

    public function findById(int $sectionId): Section
    {
        global $wpdb;
        $table = $this->table();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table; live state read, must not be cached.
        $row = $wpdb->get_row(
            $wpdb->prepare('SELECT * FROM %i WHERE id = %d', $table, $sectionId)
        );

        if ($row === null) {
            throw SectionNotFoundException::withId((int) $sectionId);
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
        $hasNewSections = $this->hasNewSections($sections);
        $this->commitPage(
            $pageId,
            $sections->generation(),
            function () use ($pageId, $sections, $compiled, $hasNewSections): void {
                global $wpdb;
                $table = $this->table();

                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table write.
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

                    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table write.
                    if ($wpdb->insert($table, $data, $formats) === false) {
                        throw new \RuntimeException(sprintf("Failed to insert section '%s'.", esc_html($section->name())));
                    }

                    if ($section->id() === null) {
                        $this->assignDurableIdentity($section, (int) $wpdb->insert_id, $table);
                    }
                }

                $this->saveCompiled(
                    $pageId,
                    $this->compiledAfterIdentityAssignment($sections, $compiled, $hasNewSections),
                );
            },
        );
        $sections->markPersistedAtGeneration($nextGeneration);
    }

    public function hasSections(int $pageId): bool
    {
        global $wpdb;
        $table = $this->table();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table; live state read, must not be cached.
        $count = (int) $wpdb->get_var(
            $wpdb->prepare('SELECT COUNT(*) FROM %i WHERE page_id = %d', $table, $pageId)
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
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Live state read; must not be cached.
        $row = $wpdb->get_row($wpdb->prepare(
            'SELECT meta_value FROM %i
             WHERE post_id = %d AND meta_key = %s
             ORDER BY meta_id DESC LIMIT 1',
            $postmetaTable,
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

        // Never instantiate objects from stored metadata (PHP Object Injection).
        $decoded = function_exists('is_serialized') && is_serialized($stored)
            ? @unserialize(trim($stored), ['allowed_classes' => false])
            : $stored;

        return is_string($decoded) && hash_equals($expectedCss, $decoded);
    }

    private function saveSections(
        int $pageId,
        SectionCollection $sections,
        CompiledOutput $compiled,
    ): void {
        $sections->reindex();
        $nextGeneration = $sections->generation() + 1;
        $hasNewSections = $this->hasNewSections($sections);
        $this->commitPage(
            $pageId,
            $sections->generation(),
            function () use ($pageId, $sections, $compiled, $hasNewSections): void {
                global $wpdb;
                $table = $this->table();

                // Upsert each section.
                foreach ($sections->all() as $section) {
                    if ($section->isNew()) {
                        if (
                            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table write.
                            $wpdb->insert($table, [
                            'page_id'  => $pageId,
                            'position' => $section->position(),
                            'name'     => $section->name(),
                            'html'     => $section->content()->html(),
                            'css'      => $section->content()->css(),
                            'element_styles' => $section->content()->elementStyles()->toJson(),
                            ], ['%d', '%d', '%s', '%s', '%s', '%s']) === false
                        ) {
                            throw new \RuntimeException(sprintf("Failed to insert section '%s'.", esc_html($section->name())));
                        }

                        $this->assignDurableIdentity($section, (int) $wpdb->insert_id, $table);
                    } else {
                        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table write.
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
                            throw new \RuntimeException(sprintf('Failed to update section %d.', (int) $section->id()));
                        }
                    }
                }

                // Keep the table aligned with the saved page snapshot so deletes do
                // not silently resurrect on the next read.
                $this->deleteMissingSections($pageId, $sections);

                $this->saveCompiled(
                    $pageId,
                    $this->compiledAfterIdentityAssignment($sections, $compiled, $hasNewSections),
                );
            },
        );
        $sections->markPersistedAtGeneration($nextGeneration);
    }

    private function hasNewSections(SectionCollection $sections): bool
    {
        foreach ($sections->all() as $section) {
            if ($section->isNew()) {
                return true;
            }
        }

        return false;
    }

    private function assignDurableIdentity(Section $section, int $sectionId, string $table): void
    {
        global $wpdb;

        $sourceSectionId = $section->sourceRootId();
        $section->assignId($sectionId);
        if ($sourceSectionId === null || $sourceSectionId === $sectionId) {
            return;
        }

        $section->replaceContent(SectionRootIdentityRemapper::remap(
            $section->content(),
            $sourceSectionId,
            $sectionId,
        ));
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table write.
        $updated = $wpdb->update(
            $table,
            [
                'html' => $section->content()->html(),
                'css' => $section->content()->css(),
                'element_styles' => $section->content()->elementStyles()->toJson(),
            ],
            ['id' => $sectionId],
            ['%s', '%s', '%s'],
            ['%d'],
        );
        if ($updated === false) {
            throw new \RuntimeException(sprintf('Failed to persist section %d root identity.', (int) $sectionId));
        }
    }

    private function compiledAfterIdentityAssignment(
        SectionCollection $sections,
        CompiledOutput $compiled,
        bool $hasNewSections,
    ): CompiledOutput {
        if (!$hasNewSections) {
            return $compiled;
        }

        return $this->compiler->compile($sections);
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

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table; live state read, must not be cached.
        $rows = $wpdb->get_results(
            $wpdb->prepare('SELECT id FROM %i WHERE page_id = %d', $this->table(), $pageId)
        );

        foreach ($rows as $row) {
            $rowId = isset($row->id) ? (int) $row->id : 0;
            if ($rowId <= 0 || isset($persistedIds[$rowId])) {
                continue;
            }

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table write.
            if ($wpdb->delete($this->table(), ['id' => $rowId, 'page_id' => $pageId], ['%d', '%d']) === false) {
                throw new \RuntimeException(sprintf('Failed to delete section %d.', (int) $rowId));
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
