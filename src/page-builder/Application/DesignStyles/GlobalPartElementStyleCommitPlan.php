<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\DesignStyles;

use UncannyPageBuilder\Domain\GlobalPart\GlobalPartType;

/**
 * Prepared global-part-owned element style write.
 */
final class GlobalPartElementStyleCommitPlan
{
    /**
     * @param array<string, mixed> $partSnapshot
     * @param array<string, mixed> $content
     * @param array<int, array<string, mixed>> $applied
     * @param array<int, array<string, mixed>> $targets
     */
    public function __construct(
        private readonly int $partId,
        private readonly array $partSnapshot,
        private readonly string $title,
        private readonly string $sectionName,
        private readonly array $content,
        private readonly GlobalPartType $type,
        private readonly array $applied,
        private readonly array $targets,
        private readonly bool $promoted,
    ) {}

    public function partId(): int { return $this->partId; }
    /** @return array<string, mixed> */
    public function partSnapshot(): array { return $this->partSnapshot; }
    public function title(): string { return $this->title; }
    public function sectionName(): string { return $this->sectionName; }
    /** @return array<string, mixed> */
    public function content(): array { return $this->content; }
    public function type(): GlobalPartType { return $this->type; }
    /** @return array<int, array<string, mixed>> */
    public function applied(): array { return $this->applied; }
    /** @return array<int, array<string, mixed>> */
    public function targets(): array { return $this->targets; }
    public function promoted(): bool { return $this->promoted; }
}
