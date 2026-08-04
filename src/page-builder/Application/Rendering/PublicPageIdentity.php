<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\Rendering;

/**
 * Raw WordPress identity that must still match the selected publication.
 *
 * The publisher commits these fields beside the artifact pointer. A mismatch
 * means another writer split that atomic public state after publication.
 */
final class PublicPageIdentity
{
    public function __construct(
        private readonly int $pageId,
        private readonly string $status,
        private readonly string $title,
        private readonly string $slug,
    ) {
        if ($pageId <= 0) {
            throw new \InvalidArgumentException('Public page identity requires a positive page ID.');
        }
    }

    public function pageId(): int
    {
        return $this->pageId;
    }

    public function matchesPublication(string $title, string $slug): bool
    {
        /*
         * WordPress post status controls whether WordPress exposes the page;
         * it does not select Page Builder's rendering lane. A draft page may
         * still have a valid immutable artifact for authenticated previews.
         */
        return hash_equals($title, $this->title)
            && hash_equals($slug, $this->slug);
    }
}
