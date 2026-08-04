<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\Controls;

final class ControlContext
{
    /**
     * @param array{
     *     can_edit: bool,
     *     can_manage: bool,
     *     can_upload: bool,
     *     can_publish?: bool,
     *     can_edit_custom_javascript?: bool
     * } $capabilities
     */
    public function __construct(
        private readonly string $surface,
        private readonly string $scope,
        private readonly int $pageId,
        private readonly int $globalPartId,
        private readonly int $userId,
        private readonly array $capabilities,
    ) {}

    /** @param array{can_edit: bool, can_manage: bool, can_upload: bool, can_publish?: bool, can_edit_custom_javascript?: bool} $capabilities */
    public static function forPage(int $pageId, int $userId, array $capabilities): self
    {
        return new self('canvas', 'page', $pageId, 0, $userId, $capabilities);
    }

    /** @param array{can_edit: bool, can_manage: bool, can_upload: bool, can_publish?: bool, can_edit_custom_javascript?: bool} $capabilities */
    public static function forGlobalPart(int $globalPartId, int $userId, array $capabilities): self
    {
        return new self('canvas', 'global_part', 0, $globalPartId, $userId, $capabilities);
    }

    public function surface(): string
    {
        return $this->surface;
    }

    public function scope(): string
    {
        return $this->scope;
    }

    public function pageId(): int
    {
        return $this->pageId;
    }

    public function globalPartId(): int
    {
        return $this->globalPartId;
    }

    public function userId(): int
    {
        return $this->userId;
    }

    /** @return array{can_edit: bool, can_manage: bool, can_upload: bool, can_publish?: bool, can_edit_custom_javascript?: bool} */
    public function capabilities(): array
    {
        return $this->capabilities;
    }

    public function canEdit(): bool
    {
        return $this->capabilities['can_edit'];
    }

    public function canManage(): bool
    {
        return $this->capabilities['can_manage'];
    }

    public function canPublish(): bool
    {
        return (bool) ($this->capabilities['can_publish'] ?? false);
    }

    public function canEditCustomJavaScript(): bool
    {
        return (bool) ($this->capabilities['can_edit_custom_javascript'] ?? false);
    }

    /** @return array{surface: string, scope: string, page_id: int, global_part_id: int} */
    public function toArray(): array
    {
        return [
            'surface'        => $this->surface,
            'scope'          => $this->scope,
            'page_id'        => $this->pageId,
            'global_part_id' => $this->globalPartId,
        ];
    }
}
