<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\DesignStyles;

/**
 * The complete design stack submitted by one Save click.
 */
final class DesignStyleBatchCommitRequest
{
    /**
     * @param DesignStyleBatchChange[] $changes
     * @param array{can_edit: bool, can_manage: bool, can_upload: bool, can_publish?: bool} $capabilities
     */
    public function __construct(
        private readonly int $pageId,
        private readonly array $changes,
        private readonly array $capabilities,
    ) {}

    public function pageId(): int
    {
        return $this->pageId;
    }

    /** @return DesignStyleBatchChange[] */
    public function changes(): array
    {
        return $this->changes;
    }

    public function canEdit(): bool
    {
        return $this->capabilities['can_edit'] ?? false;
    }

    public function canManage(): bool
    {
        return $this->capabilities['can_manage'] ?? false;
    }

    /**
     * @return array{can_edit: bool, can_manage: bool, can_upload: bool, can_publish?: bool}
     */
    public function capabilities(): array
    {
        return $this->capabilities;
    }
}
