<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\Section;

use UncannyPageBuilder\Domain\Compiler\CompiledOutput;

interface SectionRepositoryInterface
{
    /**
     * Load the SectionCollection for a page (post).
     * Returns empty collection if no sections exist.
     */
    public function findByPageId(int $pageId): SectionCollection;

    /**
     * Load a single section by its database ID.
     *
     * @throws \UncannyPageBuilder\Domain\Exception\SectionNotFoundException
     */
    public function findById(int $sectionId): Section;

    /**
     * Persist the exact page snapshot atomically.
     * Updates/inserts the supplied rows, removes rows missing from the
     * collection, and writes compiled CSS metadata.
     */
    public function save(int $pageId, SectionCollection $sections, CompiledOutput $compiled): void;

    /**
     * Persist only the compiled CSS metadata used by active rendering.
     */
    public function saveCompiled(int $pageId, CompiledOutput $compiled): void;

    /**
     * Delete all section rows for a page and insert fresh ones,
     * then persist compiled CSS metadata.
     *
     * Compiled output is saved immediately after the section transaction
     * commits, minimising the window where sections and CSS can diverge.
     */
    public function replaceAll(int $pageId, SectionCollection $sections, CompiledOutput $compiled): void;

    /**
     * Check whether any sections exist for a page.
     */
    public function hasSections(int $pageId): bool;

    /**
     * Check whether a post exists and is a valid page target.
     */
    public function pageExists(int $pageId): bool;

    /**
     * Check whether a page is owned by Uncanny Page Builder (_uncanny_page_builder_owned = 1).
     */
    public function isOwnedPage(int $pageId): bool;

    /**
     * Mark a page as owned by Uncanny Page Builder.
     */
    public function markAsOwned(int $pageId): void;

    /**
     * Mark a post as an Engine-managed page (sets _uncanny_page_builder_active = 1).
     */
    public function markAsEnginePage(int $pageId): void;

    /**
     * Get the public permalink for a page.
     */
    public function getPermalink(int $pageId): string;
}
