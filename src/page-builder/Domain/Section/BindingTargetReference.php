<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\Section;

final class BindingTargetReference
{
    private const SECTION_PREFIX = 'section';
    private const GLOBAL_PART_PREFIX = 'global_part';

    private function __construct(
        private readonly string $kind,
        private readonly int $id,
    ) {}

    public static function forSection(int $sectionId): self
    {
        return new self(self::SECTION_PREFIX, $sectionId);
    }

    public static function forGlobalPart(int $globalPartId): self
    {
        return new self(self::GLOBAL_PART_PREFIX, $globalPartId);
    }

    public static function fromToken(string $token): self
    {
        if (!preg_match('/^(section|global_part):(\d+)$/', trim($token), $matches)) {
            throw new \InvalidArgumentException('target_id must use the format "section:{id}" or "global_part:{id}".');
        }

        $id = (int) $matches[2];
        if ($id <= 0) {
            throw new \InvalidArgumentException('target_id must contain a positive integer id.');
        }

        return new self($matches[1], $id);
    }

    public function token(): string
    {
        return $this->kind . ':' . $this->id;
    }

    public function id(): int
    {
        return $this->id;
    }

    public function isSection(): bool
    {
        return $this->kind === self::SECTION_PREFIX;
    }

    public function isGlobalPart(): bool
    {
        return $this->kind === self::GLOBAL_PART_PREFIX;
    }
}
