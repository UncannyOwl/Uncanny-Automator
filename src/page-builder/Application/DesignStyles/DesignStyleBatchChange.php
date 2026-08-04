<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\DesignStyles;

use UncannyPageBuilder\Domain\DesignStyles\DesignWriteScope;

/**
 * One design change inside the Save-click design stack.
 *
 * The batch change carries ownership beside the style declaration so PHP can
 * group by the persisted source before writing anything.
 */
final class DesignStyleBatchChange
{
    public function __construct(
        private readonly string $id,
        private readonly DesignWriteScope $scope,
        private readonly DesignStyleChange $change,
        private readonly int $sectionId = 0,
        private readonly ?DesignStyleSourceOwner $owner = null,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $scope = DesignWriteScope::tryFromString(self::readString($data['scope'] ?? null));
        if (!$scope instanceof DesignWriteScope) {
            throw new \InvalidArgumentException('Each design change requires a valid scope.');
        }

        $id = self::readString($data['id'] ?? null);
        if ($id === '') {
            throw new \InvalidArgumentException('Each design change requires an id.');
        }

        $sectionId = self::readInt($data['section_id'] ?? ($data['sectionId'] ?? null));
        $owner = self::readOwner($data['owner'] ?? null);
        if (!$owner instanceof DesignStyleSourceOwner && $scope === DesignWriteScope::Element && $sectionId > 0) {
            $owner = DesignStyleSourceOwner::section($sectionId);
        }

        return new self(
            id: $id,
            scope: $scope,
            change: DesignStyleChange::fromArray($data),
            sectionId: $sectionId,
            owner: $owner,
        );
    }

    public function id(): string
    {
        return $this->id;
    }

    public function scope(): DesignWriteScope
    {
        return $this->scope;
    }

    public function change(): DesignStyleChange
    {
        return $this->change;
    }

    public function sectionId(): int
    {
        if ($this->owner instanceof DesignStyleSourceOwner && $this->owner->isSection()) {
            return $this->owner->id();
        }

        return $this->sectionId;
    }

    public function owner(): ?DesignStyleSourceOwner
    {
        return $this->owner;
    }

    private static function readString(mixed $value): string
    {
        if (is_string($value)) {
            return trim($value);
        }

        return is_int($value) || is_float($value) ? (string) $value : '';
    }

    private static function readInt(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }

        return is_string($value) && preg_match('/^[0-9]+$/', $value) === 1 ? (int) $value : 0;
    }

    private static function readOwner(mixed $value): ?DesignStyleSourceOwner
    {
        return is_array($value) ? DesignStyleSourceOwner::fromArray($value) : null;
    }
}
