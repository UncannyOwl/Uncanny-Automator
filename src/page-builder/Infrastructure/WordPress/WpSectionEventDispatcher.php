<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\WordPress;

use UncannyPageBuilder\Domain\Section\SectionEventDispatcherInterface;

final class WpSectionEventDispatcher implements SectionEventDispatcherInterface
{
    /**
     * Fires after a section is created, edited, or restored.
     *
     * @param int    $pageId    The page the section belongs to.
     * @param int    $sectionId The section that was saved.
     * @param string $action    One of: 'created', 'edited', 'restored', 'proposal_applied'.
     */
    public function sectionSaved(int $pageId, int $sectionId, string $action): void
    {
        do_action('uncanny_page_builder_section_saved', $pageId, $sectionId, $action);
    }

    /**
     * Fires after a section is deleted from a page.
     *
     * @param int $pageId    The page the section was removed from.
     * @param int $sectionId The section that was deleted.
     */
    public function sectionDeleted(int $pageId, int $sectionId): void
    {
        do_action('uncanny_page_builder_section_deleted', $pageId, $sectionId);
    }

    /**
     * Fires after sections on a page are reordered.
     *
     * @param int $pageId The page whose sections were reordered.
     */
    public function sectionsReordered(int $pageId): void
    {
        do_action('uncanny_page_builder_sections_reordered', $pageId);
    }
}
