<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\Concurrency;

/**
 * Captures the two source boundaries that shape a published page.
 *
 * Page generation covers one page-owned aggregate. Global generation covers
 * shared Page Builder source such as reusable header/footer content. Keeping
 * these two boundaries explicit avoids recreating per-feature revision tokens.
 */
final class SourceGenerationSnapshot
{
    public const DEPENDENCY_KEY = 'source_generations';

    public function __construct(
        private readonly int $pageId,
        private readonly int $pageGeneration,
        private readonly int $globalGeneration,
    ) {
        if ($pageId <= 0) {
            throw new \InvalidArgumentException('Source generation page id must be positive.');
        }
        if ($pageGeneration < 0 || $globalGeneration < 0) {
            throw new \InvalidArgumentException('Source generations must not be negative.');
        }
    }

    public function pageId(): int
    {
        return $this->pageId;
    }

    public function pageGeneration(): int
    {
        return $this->pageGeneration;
    }

    public function globalGeneration(): int
    {
        return $this->globalGeneration;
    }

    /**
     * @return array{page: array{id: int, generation: int}, global: int}
     */
    public function toArray(): array
    {
        return [
            'page' => [
                'id' => $this->pageId,
                'generation' => $this->pageGeneration,
            ],
            'global' => $this->globalGeneration,
        ];
    }

    /**
     * @param array<string, mixed> $dependencies
     */
    public static function fromDependencies(array $dependencies): ?self
    {
        $raw = $dependencies[self::DEPENDENCY_KEY] ?? null;
        if (!is_array($raw) || !is_array($raw['page'] ?? null)) {
            return null;
        }

        $pageId = (int) ($raw['page']['id'] ?? 0);
        $pageGeneration = (int) ($raw['page']['generation'] ?? -1);
        $globalGeneration = (int) ($raw['global'] ?? -1);
        if ($pageId <= 0 || $pageGeneration < 0 || $globalGeneration < 0) {
            return null;
        }

        return new self($pageId, $pageGeneration, $globalGeneration);
    }
}
