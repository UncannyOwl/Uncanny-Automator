<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\Publishing;

use UncannyPageBuilder\Domain\Compiler\ShadowCompiler;
use UncannyPageBuilder\Domain\Concurrency\SourceGenerationSnapshot;
use UncannyPageBuilder\Domain\Concurrency\SourceGenerationStoreInterface;
use UncannyPageBuilder\Domain\Section\SectionRepositoryInterface;

/**
 * Refreshes editor-only compiled state without creating public output.
 */
final class RefreshWorkingCanvas implements WorkingCanvasRefresherInterface
{
    public function __construct(
        private readonly SectionRepositoryInterface $sections,
        private readonly ShadowCompiler $compiler,
        private readonly SourceGenerationStoreInterface $sourceGenerations,
    ) {}

    public function refresh(int $pageId): void
    {
        if ($pageId <= 0) {
            throw new \InvalidArgumentException('page_id must be positive.');
        }

        $workingSections = $this->sections->findByPageId($pageId);
        $compiled = $this->compiler->compile($workingSections);
        $snapshot = new SourceGenerationSnapshot(
            $pageId,
            $workingSections->generation(),
            $this->sourceGenerations->globalGeneration(),
        );

        $this->sourceGenerations->publishIfCurrent(
            $snapshot,
            function () use ($pageId, $compiled): void {
                $this->sections->saveCompiled($pageId, $compiled);
            },
        );
    }
}
