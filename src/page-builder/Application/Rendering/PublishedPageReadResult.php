<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\Rendering;

/**
 * A fail-closed public-page read with an operator-facing reason code.
 */
final class PublishedPageReadResult
{
    private function __construct(
        private readonly int $pageId,
        private readonly PublishedPageStatus $status,
        private readonly ?PublishedPage $page,
        private readonly string $diagnosticCode,
    ) {
        if ($pageId < 0 || ($pageId === 0 && $status === PublishedPageStatus::Ready)) {
            throw new \InvalidArgumentException('A ready published-page read requires a positive page ID.');
        }
        if (($status === PublishedPageStatus::Ready) !== ($page instanceof PublishedPage)) {
            throw new \InvalidArgumentException('Only a ready public read may contain a published page.');
        }
        if ($page instanceof PublishedPage && $page->pageId() !== $pageId) {
            throw new \InvalidArgumentException('Published-page read ownership does not match.');
        }
    }

    public static function ready(PublishedPage $page): self
    {
        return new self($page->pageId(), PublishedPageStatus::Ready, $page, '');
    }

    public static function failed(int $pageId, PublishedPageStatus $status, string $diagnosticCode = ''): self
    {
        if ($status === PublishedPageStatus::Ready) {
            throw new \InvalidArgumentException('Ready reads require a published page.');
        }

        return new self($pageId, $status, null, trim($diagnosticCode));
    }

    public function pageId(): int
    {
        return $this->pageId;
    }

    public function status(): PublishedPageStatus
    {
        return $this->status;
    }

    public function page(): ?PublishedPage
    {
        return $this->page;
    }

    public function isReady(): bool
    {
        return $this->status === PublishedPageStatus::Ready;
    }

    public function diagnosticCode(): string
    {
        return $this->diagnosticCode;
    }
}
