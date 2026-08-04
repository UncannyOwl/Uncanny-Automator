<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\i18\strings;

final class CanvasHiddenControlStrings
{
    /**
     * @return array{label: string, description: string}
     */
    public function sectionCreate(): array
    {
        return [
            'label' => _x('Create section', 'Page Builder', 'uncanny-automator'),
            'description' => _x('Create a new section on a page.', 'Page Builder', 'uncanny-automator'),
        ];
    }

    /**
     * @return array{label: string, description: string}
     */
    public function sectionEditableUpdate(): array
    {
        return [
            'label' => _x('Update editable', 'Page Builder', 'uncanny-automator'),
            'description' => _x('Apply an inline editable update to section HTML.', 'Page Builder', 'uncanny-automator'),
        ];
    }

    /**
     * @return array{label: string, description: string}
     */
    public function sectionNodeUpdate(): array
    {
        return [
            'label' => _x('Update node', 'Page Builder', 'uncanny-automator'),
            'description' => _x('Apply a Design Lens node update to section HTML.', 'Page Builder', 'uncanny-automator'),
        ];
    }

    /**
     * @return array{label: string, description: string}
     */
    public function sectionRewriteSource(): array
    {
        return [
            'label' => _x('Rewrite section code', 'Page Builder', 'uncanny-automator'),
            'description' => _x('Replace section HTML and CSS.', 'Page Builder', 'uncanny-automator'),
        ];
    }

    /**
     * @return array{label: string, description: string}
     */
    public function designStyleCommit(): array
    {
        return [
            'label' => _x('Commit design style', 'Page Builder', 'uncanny-automator'),
            'description' => _x('Persist the pending design stack in one batch.', 'Page Builder', 'uncanny-automator'),
        ];
    }

    /**
     * @return array{label: string, description: string}
     */
    public function manualChangesCommit(): array
    {
        return [
            'label' => _x('Save Manual changes', 'Page Builder', 'uncanny-automator'),
            'description' => _x('Persist the pending human page changes in one guarded batch.', 'Page Builder', 'uncanny-automator'),
        ];
    }

    /**
     * @return array{label: string, description: string}
     */
    public function pageResumeDraft(): array
    {
        return [
            'label' => _x('Load saved draft', 'Page Builder', 'uncanny-automator'),
            'description' => _x('Reopen the newer saved working draft in the Manual editor.', 'Page Builder', 'uncanny-automator'),
        ];
    }

    /**
     * @return array{label: string, description: string}
     */
    public function sectionReorder(): array
    {
        return [
            'label' => _x('Reorder', 'Page Builder', 'uncanny-automator'),
            'description' => _x('Persist the section order for this page.', 'Page Builder', 'uncanny-automator'),
        ];
    }

    /**
     * @return array{label: string, description: string}
     */
    public function siteDesignRead(): array
    {
        return [
            'label' => _x('Get site design', 'Page Builder', 'uncanny-automator'),
            'description' => _x('Read the site design system in customer language.', 'Page Builder', 'uncanny-automator'),
        ];
    }

    /**
     * @return array{label: string, description: string}
     */
    public function bindingManage(): array
    {
        return [
            'label' => _x('Manage binding', 'Page Builder', 'uncanny-automator'),
            'description' => _x('Read or update dynamic bindings.', 'Page Builder', 'uncanny-automator'),
        ];
    }

    /**
     * @return array{label: string, description: string}
     */
    public function manageNavigation(): array
    {
        return [
            'label' => _x('Manage navigation', 'Page Builder', 'uncanny-automator'),
            'description' => _x('Read and update navigation menus.', 'Page Builder', 'uncanny-automator'),
        ];
    }

    /**
     * @return array{label: string, description: string}
     */
    public function manageMedia(): array
    {
        return [
            'label' => _x('Manage media', 'Page Builder', 'uncanny-automator'),
            'description' => _x('Search, inspect, and upload media.', 'Page Builder', 'uncanny-automator'),
        ];
    }

    /**
     * @return array{label: string, description: string}
     */
    public function pageReadContext(): array
    {
        return [
            'label' => _x('Read page context', 'Page Builder', 'uncanny-automator'),
            'description' => _x('Read page outline and next steps.', 'Page Builder', 'uncanny-automator'),
        ];
    }

    /**
     * @return array{label: string, description: string}
     */
    public function partRead(): array
    {
        return [
            'label' => _x('Read part', 'Page Builder', 'uncanny-automator'),
            'description' => _x('Read details for a section or reusable part.', 'Page Builder', 'uncanny-automator'),
        ];
    }

    /**
     * @return array{label: string, description: string}
     */
    public function runtimeRead(): array
    {
        return [
            'label' => _x('Read runtime', 'Page Builder', 'uncanny-automator'),
            'description' => _x('Read custom JavaScript for a page or reusable part.', 'Page Builder', 'uncanny-automator'),
        ];
    }

    /**
     * @return array{label: string, description: string}
     */
    public function findLucideIcons(): array
    {
        return [
            'label' => _x('Find Lucide icons', 'Page Builder', 'uncanny-automator'),
            'description' => _x('Find valid Lucide icon names after warning recovery.', 'Page Builder', 'uncanny-automator'),
        ];
    }

    /**
     * @return array{label: string, description: string}
     */
    public function editPart(): array
    {
        return [
            'label' => _x('Edit part', 'Page Builder', 'uncanny-automator'),
            'description' => _x('Edit a section or reusable part with target edits, CSS rules, structure edits, exact patches, or full source replacement.', 'Page Builder', 'uncanny-automator'),
        ];
    }

    /**
     * @return array{label: string, description: string}
     */
    public function editRuntime(): array
    {
        return [
            'label' => _x('Edit runtime', 'Page Builder', 'uncanny-automator'),
            'description' => _x('Replace or clear custom JavaScript for a page or reusable part.', 'Page Builder', 'uncanny-automator'),
        ];
    }

    /**
     * @return array{label: string, description: string}
     */
    public function previewChange(): array
    {
        return [
            'label' => _x('Preview change', 'Page Builder', 'uncanny-automator'),
            'description' => _x('Preview a proposed HTML or CSS change before saving.', 'Page Builder', 'uncanny-automator'),
        ];
    }

    /**
     * @return array{label: string, description: string}
     */
    public function previewRuntimeChange(): array
    {
        return [
            'label' => _x('Preview runtime change', 'Page Builder', 'uncanny-automator'),
            'description' => _x('Preview a proposed custom JavaScript patch before saving.', 'Page Builder', 'uncanny-automator'),
        ];
    }

    /**
     * @return array{label: string, description: string}
     */
    public function manageSections(): array
    {
        return [
            'label' => _x('Manage sections', 'Page Builder', 'uncanny-automator'),
            'description' => _x('Add, move, duplicate, or delete page sections.', 'Page Builder', 'uncanny-automator'),
        ];
    }

    /**
     * @return array{label: string, description: string}
     */
    public function manageCanvas(): array
    {
        return [
            'label' => _x('Manage canvas', 'Page Builder', 'uncanny-automator'),
            'description' => _x('Create, update, or delete a page or reusable canvas.', 'Page Builder', 'uncanny-automator'),
        ];
    }

    /**
     * @return array{label: string, description: string}
     */
    public function manageReusable(): array
    {
        return [
            'label' => _x('Manage reusable', 'Page Builder', 'uncanny-automator'),
            'description' => _x('Create, convert, update, or delete reusable parts.', 'Page Builder', 'uncanny-automator'),
        ];
    }
}
