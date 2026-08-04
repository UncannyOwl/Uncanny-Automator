<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\DesignStyles;

use UncannyPageBuilder\Domain\GlobalPart\GlobalPartType;

/**
 * Names the persisted source that owns an element style edit.
 *
 * Element design writes are not always page sections. Header/footer canvases and
 * runtime binding wrappers need an explicit owner so the save path writes to the
 * correct persisted source instead of guessing from the rendered DOM.
 */
final class DesignStyleSourceOwner
{
    private function __construct(
        private readonly string $kind,
        private readonly int $id,
        private readonly ?GlobalPartType $globalPartType = null,
    ) {}

    public static function section(int $sectionId): self
    {
        return new self('section', $sectionId);
    }

    public static function globalPart(int $partId, GlobalPartType $type): self
    {
        return new self('global_part', $partId, $type);
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): ?self
    {
        $kind = self::readString($data['kind'] ?? null);

        if ($kind === 'global_part') {
            $partId = self::readInt($data['part_id'] ?? ($data['global_part_id'] ?? null));
            $type = GlobalPartType::tryFrom(self::readString($data['part_type'] ?? null));

            return $partId > 0 && $type instanceof GlobalPartType
                ? self::globalPart($partId, $type)
                : null;
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

    public function globalPartType(): ?GlobalPartType
    {
        return $this->globalPartType;
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
