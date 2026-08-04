<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\WordPress;

use UncannyPageBuilder\Domain\ContentType\ContentType;
use UncannyPageBuilder\Domain\ContentType\ContentTypeCatalogInterface;

/**
 * Maps registered WordPress post types into host-neutral domain facts.
 */
final class WordPressContentTypeCatalog implements ContentTypeCatalogInterface
{
    public function contentTypes(): array
    {
        $registered = get_post_types([], 'objects');
        if (!is_array($registered)) {
            return [];
        }

        $contentTypes = [];

        foreach ($registered as $slug => $postType) {
            if (!is_string($slug) || !is_object($postType)) {
                continue;
            }

            $label = $postType->labels->name ?? $postType->label ?? $slug;
            if (!is_string($label) || trim($label) === '') {
                $label = $slug;
            }

            $contentTypes[] = new ContentType(
                slug: $slug,
                label: $label,
                public: (bool) ($postType->public ?? false),
                showsUi: (bool) ($postType->show_ui ?? false),
                supportsEditor: post_type_supports($slug, 'editor'),
            );
        }

        return $contentTypes;
    }
}
