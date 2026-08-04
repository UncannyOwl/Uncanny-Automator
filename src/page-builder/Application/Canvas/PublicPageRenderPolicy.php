<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\Canvas;

use UncannyPageBuilder\Application\ContentType\PostTypeIntentForPostInterface;
use UncannyPageBuilder\Application\Rendering\PublishedPage;
use UncannyPageBuilder\Application\Rendering\PublishedPageReadResult;
use UncannyPageBuilder\Application\Rendering\PublishedPageReaderInterface;
use UncannyPageBuilder\Application\Rendering\PublishedPageStatus;
use UncannyPageBuilder\Domain\Canvas\PageOwnershipRepositoryInterface;

/**
 * Decides when Page Builder may replace a page's existing public output.
 *
 * Working-source presence never grants public authority. Only a valid exact
 * artifact pointer may hand public rendering to Page Builder.
 */
final class PublicPageRenderPolicy
{
    /** @var array<int, PublishedPageReadResult> */
    private array $reads = [];

    public function __construct(
        private readonly PublishedPageReaderInterface $publishedPages,
        private readonly PageOwnershipRepositoryInterface $ownership,
        private readonly PagePasswordProtectionInterface $passwordProtection,
        private readonly ?PostTypeIntentForPostInterface $postTypeIntent = null,
    ) {}

    public function isReady(int $pageId): bool
    {
        return !$this->isPasswordRequired($pageId) && $this->read($pageId)->isReady();
    }

    public function publishedPage(int $pageId): ?PublishedPage
    {
        if ($this->isPasswordRequired($pageId)) {
            return null;
        }

        return $this->read($pageId)->page();
    }

    public function isPasswordRequired(int $pageId): bool
    {
        if ($pageId <= 0) {
            return false;
        }

        try {
            return $this->passwordProtection->isPasswordRequired($pageId);
        } catch (\Throwable) {
            // Access uncertainty must never disclose exact artifact output.
            return true;
        }
    }

    public function read(int $pageId): PublishedPageReadResult
    {
        if ($pageId <= 0) {
            return PublishedPageReadResult::failed(0, PublishedPageStatus::NotManaged, 'invalid_page_id');
        }

        return $this->reads[$pageId] ??= $this->readUncached($pageId);
    }

    private function readUncached(int $pageId): PublishedPageReadResult
    {
        try {
            if (
                $this->postTypeIntent instanceof PostTypeIntentForPostInterface
                && !$this->postTypeIntent->isEnabledForPost($pageId)
            ) {
                return PublishedPageReadResult::failed($pageId, PublishedPageStatus::NotManaged);
            }
        } catch (\Throwable) {
            return PublishedPageReadResult::failed($pageId, PublishedPageStatus::ReadFailed, 'post_type_intent_read_failed');
        }

        try {
            if (!$this->ownership->isOwned($pageId)) {
                return PublishedPageReadResult::failed($pageId, PublishedPageStatus::NotManaged);
            }
        } catch (\Throwable) {
            return PublishedPageReadResult::failed($pageId, PublishedPageStatus::ReadFailed, 'ownership_read_failed');
        }

        $read = $this->publishedPages->read($pageId);

        // An owned page must have state after adoption/migration. Treat a
        // missing row as corruption, never as permission to expose post_content.
        return $read->status() === PublishedPageStatus::NotManaged
            ? PublishedPageReadResult::failed($pageId, PublishedPageStatus::ReadFailed, 'publication_state_missing')
            : $read;
    }
}
