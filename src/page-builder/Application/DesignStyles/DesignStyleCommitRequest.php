<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\DesignStyles;

use UncannyPageBuilder\Domain\DesignStyles\DesignWriteScope;

/**
 * A typed request to commit pending design style changes.
 *
 * Scope decides routing (global / page / element). Capability context is carried
 * so the application service can enforce permission without reaching for globals.
 */
final class DesignStyleCommitRequest
{
    /**
     * @param DesignStyleChange[] $changes
     * @param array{can_edit: bool, can_manage: bool, can_upload: bool, can_publish?: bool} $capabilities
     */
    public function __construct(
        private readonly DesignWriteScope $scope,
        private readonly int $pageId,
        private readonly array $changes,
        private readonly array $capabilities,
        private readonly int $sectionId = 0,
        private readonly ?DesignStyleSourceOwner $owner = null,
    ) {}

    public function scope(): DesignWriteScope
    {
        return $this->scope;
    }

    public function pageId(): int
    {
        return $this->pageId;
    }

    /** @return DesignStyleChange[] */
    public function changes(): array
    {
        return $this->changes;
    }

    public function sectionId(): int
    {
        return $this->sectionId;
    }

    public function owner(): ?DesignStyleSourceOwner
    {
        if ($this->owner instanceof DesignStyleSourceOwner) {
            return $this->owner;
        }

        return $this->sectionId > 0
            ? DesignStyleSourceOwner::section($this->sectionId)
            : null;
    }

    public function canEdit(): bool
    {
        return $this->capabilities['can_edit'] ?? false;
    }

    public function canManage(): bool
    {
        return $this->capabilities['can_manage'] ?? false;
    }
}
