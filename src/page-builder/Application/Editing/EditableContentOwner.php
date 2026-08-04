<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\Editing;

/**
 * Names the persisted source that owns an inline content edit.
 *
 * Inline content can belong to a normal page section or to a global part that
 * is rendered inside the page canvas. The save lane must carry that ownership
 * explicitly so the backend writes to the correct persisted source.
 */
final class EditableContentOwner
{
    private function __construct(
        private readonly string $kind,
        private readonly int $id,
    ) {}

    public static function section(int $sectionId): self
    {
        return new self('section', $sectionId);
    }

    public static function globalPart(int $partId): self
    {
        return new self('global_part', $partId);
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): ?self
    {
        $kind = self::readString($data['kind'] ?? null);

        if ($kind === 'global_part') {
            $partId = self::readInt($data['part_id'] ?? ($data['global_part_id'] ?? null));

            return $partId > 0 ? self::globalPart($partId) : null;
        }

        if ($kind === 'section') {
            $sectionId = self::readInt($data['section_id'] ?? null);

            return $sectionId > 0 ? self::section($sectionId) : null;
        }

        return null;
    }

    public function isSection(): bool
    {
        return $this->kind === 'section';
    }

    public function isGlobalPart(): bool
    {
        return $this->kind === 'global_part';
    }

    public function id(): int
    {
        return $this->id;
    }

    private static function readString(mixed $value): string
    {
        return is_string($value) ? trim($value) : '';
    }

    private static function readInt(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }

        return is_string($value) && preg_match('/^[0-9]+$/', $value) === 1 ? (int) $value : 0;
    }
}
