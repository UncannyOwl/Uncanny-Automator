<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\ContentType;

use UncannyPageBuilder\Domain\Settings\ContentTypeSettings;

/**
 * Decides whether Page Builder may be offered for a content type.
 *
 * Administrator intent is applied only while WordPress currently reports the
 * content type as public, visible in the admin UI, and editor-capable.
 */
final class PageBuilderDisplayPolicy
{
    /**
     * Core WordPress content types whose rendering contract Page Builder owns.
     *
     * Plugin-owned custom post types need explicit integrations before they
     * can join this list. Public visibility and editor support alone do not
     * prove that Page Builder can preserve their templates and dynamic data.
     *
     * @var list<string>
     */
    private const SUPPORTED_SLUGS = ['page', 'post'];

    public function supportsSlug(string $slug): bool
    {
        return in_array(strtolower(trim($slug)), self::SUPPORTED_SLUGS, true);
    }

    public function isEligible(ContentType $contentType): bool
    {
        return $this->supportsSlug($contentType->slug())
            && $contentType->isPublic()
            && $contentType->showsUi()
            && $contentType->supportsEditor();
    }

    public function shouldDisplay(
        ContentType $contentType,
        ContentTypeSettings $settings,
    ): bool {
        return $settings->isEnabled($contentType->slug())
            && $this->isEligible($contentType);
    }
}
