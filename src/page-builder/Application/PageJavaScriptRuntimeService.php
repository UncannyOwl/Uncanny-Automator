<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application;

use UncannyPageBuilder\Application\Concurrency\GlobalSourceMutation;
use UncannyPageBuilder\Application\Concurrency\PageSourceMutation;
use UncannyPageBuilder\Application\Publishing\WorkingCanvasRefreshScheduler;
use UncannyPageBuilder\Application\Observability\FailureReporterInterface;
use UncannyPageBuilder\Domain\Concurrency\SourceGenerationStoreInterface;
use UncannyPageBuilder\Domain\Editing\ExactSourcePatcher;
use UncannyPageBuilder\Domain\Exception\StaleSourceGenerationException;
use UncannyPageBuilder\Domain\JavaScriptRuntime\CustomJavaScriptRepositoryInterface;

/**
 * CRUD service for Page Builder-owned custom JavaScript.
 *
 * Raw source is stored separately from section markup. Page writes advance the
 * working generation; only explicit human publication captures that source in
 * an immutable public artifact.
 */
final class PageJavaScriptRuntimeService
{
    public const MAX_SOURCE_BYTES = 200000;
    public const WORKING_CANVAS_REFRESH_WARNING = 'The custom JavaScript source was saved, but working canvases could not be queued for refresh.';

    public function __construct(
        private readonly CustomJavaScriptRepositoryInterface $repository,
        private readonly ?WorkingCanvasRefreshScheduler $workingCanvasRefreshes = null,
        private readonly ?ExactSourcePatcher $sourcePatcher = null,
        private readonly ?SourceGenerationStoreInterface $sourceGenerations = null,
        private readonly ?GlobalSourceMutation $globalSource = null,
        private readonly ?PageSourceMutation $pageSource = null,
        private readonly ?FailureReporterInterface $failureReporter = null,
    ) {}

    public function readForPage(int $pageId): string
    {
        return $this->repository->readForPost($pageId);
    }

    public function replaceForPage(int $pageId, string $javascript, int $savedBy): string
    {
        $this->assertSourceSize($javascript);
        $generation = $this->sourceGenerations?->pageGeneration($pageId);
        if ($this->readForPage($pageId) === $javascript) {
            $this->assertPageGenerationStillCurrent($pageId, $generation);
            return $javascript;
        }
        $this->writeForPage($pageId, $javascript, $generation);

        unset($savedBy);

        return $javascript;
    }

    public function clearForPage(int $pageId, int $savedBy): string
    {
        if ($this->sourceGenerations instanceof SourceGenerationStoreInterface) {
            $generation = $this->sourceGenerations->pageGeneration($pageId);
            if ($this->readForPage($pageId) === '') {
                $this->assertPageGenerationStillCurrent($pageId, $generation);
                return '';
            }
            $this->commitPage(
                $pageId,
                $generation,
                fn(): mixed => $this->repository->clearForPost($pageId),
            );
        } else {
            if ($this->readForPage($pageId) === '') {
                return '';
            }
            $this->repository->clearForPost($pageId);
        }
        unset($savedBy);

        return '';
    }

    /**
     * @param array<int, mixed> $patches
     * @return array{before: string, after: string, error: ?string, too_large: bool}
     */
    public function previewSourcePatchForPage(int $pageId, array $patches): array
    {
        $before = $this->readForPage($pageId);

        return ['before' => $before, ...$this->patchCurrentSource($before, $patches)];
    }

    /**
     * @param array<int, mixed> $patches
     * @return array{before: string, after: string, error: ?string, too_large: bool}
     */
    public function applySourcePatchForPage(int $pageId, array $patches, int $savedBy): array
    {
        /*
         * Capture the generation before reading the source. If another writer
         * changes the page source while this patch is being prepared, commitPage
         * rejects the stale snapshot before the old JavaScript can be restored.
         */
        $generation = $this->sourceGenerations?->pageGeneration($pageId);
        $before = $this->readForPage($pageId);
        $preview = ['before' => $before, ...$this->patchCurrentSource($before, $patches)];
        if ($preview['error'] !== null || $preview['too_large']) {
            return $preview;
        }
        if ($preview['before'] === $preview['after']) {
            $this->assertPageGenerationStillCurrent($pageId, $generation);
            return $preview;
        }

        $this->writeForPage($pageId, $preview['after'], $generation);
        unset($savedBy);

        return $preview;
    }

    public function readForGlobalPart(int $globalPartId): string
    {
        return $this->repository->readForPost($globalPartId);
    }

    public function replaceForGlobalPart(int $globalPartId, string $javascript): string
    {
        return $this->replaceForGlobalPartWithWarnings($globalPartId, $javascript)['javascript'];
    }

    /** @return array{javascript: string, warnings: list<string>} */
    public function replaceForGlobalPartWithWarnings(int $globalPartId, string $javascript): array
    {
        $generation = $this->sourceGenerations?->globalGeneration();
        $this->writeForGlobalPart($globalPartId, $javascript, $generation);

        return [
            'javascript' => $javascript,
            'warnings' => $this->workingCanvasRefreshWarnings($globalPartId),
        ];
    }

    public function clearForGlobalPart(int $globalPartId): void
    {
        $this->clearForGlobalPartWithWarnings($globalPartId);
    }

    /** @return array{warnings: list<string>} */
    public function clearForGlobalPartWithWarnings(int $globalPartId): array
    {
        if ($this->sourceGenerations instanceof SourceGenerationStoreInterface) {
            $generation = $this->sourceGenerations->globalGeneration();
            $this->commitGlobal(
                $generation,
                fn(): mixed => $this->repository->clearForPost($globalPartId),
            );
        } else {
            $this->repository->clearForPost($globalPartId);
        }

        return ['warnings' => $this->workingCanvasRefreshWarnings($globalPartId)];
    }

    /**
     * @param array<int, mixed> $patches
     * @return array{before: string, after: string, error: ?string, too_large: bool}
     */
    public function previewSourcePatchForGlobalPart(int $globalPartId, array $patches): array
    {
        $before = $this->readForGlobalPart($globalPartId);

        return ['before' => $before, ...$this->patchCurrentSource($before, $patches)];
    }

    /**
     * @param array<int, mixed> $patches
     * @return array{before: string, after: string, error: ?string, too_large: bool, warnings?: list<string>}
     */
    public function applySourcePatchForGlobalPart(int $globalPartId, array $patches): array
    {
        /*
         * The global generation and JavaScript source form one optimistic
         * snapshot. Capture the generation first so a concurrent global write
         * makes this patch stale instead of becoming its new write baseline.
         */
        $generation = $this->sourceGenerations?->globalGeneration();
        $before = $this->readForGlobalPart($globalPartId);
        $preview = ['before' => $before, ...$this->patchCurrentSource($before, $patches)];
        if ($preview['error'] !== null || $preview['too_large']) {
            return $preview;
        }

        $this->writeForGlobalPart($globalPartId, $preview['after'], $generation);
        $preview['warnings'] = $this->workingCanvasRefreshWarnings($globalPartId);

        return $preview;
    }

    /** @return list<string> */
    private function workingCanvasRefreshWarnings(int $globalPartId): array
    {
        if (!$this->workingCanvasRefreshes instanceof WorkingCanvasRefreshScheduler) {
            return [];
        }

        try {
            $this->workingCanvasRefreshes->enqueueAll();
        } catch (\Throwable $failure) {
            // The source write is complete. Do not retry it because a derived refresh fails.
            try {
                $this->failureReporter?->report(
                    'global-part JavaScript source',
                    $globalPartId,
                    'working_canvas.enqueue',
                    $failure,
                );
            } catch (\Throwable) {
                // A report failure cannot change the completed source result.
            }
            return [self::WORKING_CANVAS_REFRESH_WARNING];
        }

        return [];
    }

    private function writeForPage(int $pageId, string $javascript, ?int $expectedGeneration): void
    {
        $this->assertSourceSize($javascript);
        if ($this->sourceGenerations instanceof SourceGenerationStoreInterface) {
            if ($expectedGeneration === null) {
                throw new \LogicException('A page source generation is required for a guarded JavaScript write.');
            }

            $this->commitPage(
                $pageId,
                $expectedGeneration,
                fn(): mixed => $this->repository->writeForPost($pageId, $javascript),
            );

            return;
        }

        $this->repository->writeForPost($pageId, $javascript);
    }

    private function writeForGlobalPart(int $globalPartId, string $javascript, ?int $expectedGeneration): void
    {
        $this->assertSourceSize($javascript);
        if ($this->sourceGenerations instanceof SourceGenerationStoreInterface) {
            if ($expectedGeneration === null) {
                throw new \LogicException('A global source generation is required for a guarded JavaScript write.');
            }

            $this->commitGlobal(
                $expectedGeneration,
                fn(): mixed => $this->repository->writeForPost($globalPartId, $javascript),
            );

            return;
        }

        $this->repository->writeForPost($globalPartId, $javascript);
    }

    private function assertSourceSize(string $javascript): void
    {
        if (strlen($javascript) <= self::MAX_SOURCE_BYTES) {
            return;
        }

        throw new \InvalidArgumentException(sprintf(
            'Custom JavaScript source exceeds the %d byte limit.',
            self::MAX_SOURCE_BYTES,
        ));
    }

    /**
     * A no-op is coherent only while the generation captured before its read is
     * still current. Otherwise the caller observed a mixed concurrent snapshot.
     */
    private function assertPageGenerationStillCurrent(int $pageId, ?int $expectedGeneration): void
    {
        if (!$this->sourceGenerations instanceof SourceGenerationStoreInterface || $expectedGeneration === null) {
            return;
        }

        $currentGeneration = $this->sourceGenerations->pageGeneration($pageId);
        if ($currentGeneration !== $expectedGeneration) {
            throw new StaleSourceGenerationException('page', $expectedGeneration, $currentGeneration);
        }
    }

    /**
     * @param callable(): mixed $write
     */
    private function commitGlobal(int $expectedGeneration, callable $write): mixed
    {
        if ($this->globalSource instanceof GlobalSourceMutation) {
            return $this->globalSource->runExpected($expectedGeneration, $write);
        }

        if (!$this->sourceGenerations instanceof SourceGenerationStoreInterface) {
            throw new \LogicException('A global source generation store is required.');
        }

        return $this->sourceGenerations->commitGlobal($expectedGeneration, $write);
    }

    /**
     * @param callable(): mixed $write
     */
    private function commitPage(int $pageId, int $expectedGeneration, callable $write): mixed
    {
        if ($this->pageSource instanceof PageSourceMutation) {
            return $this->pageSource->runExpected($pageId, $expectedGeneration, $write);
        }

        if (!$this->sourceGenerations instanceof SourceGenerationStoreInterface) {
            throw new \LogicException('A page source generation store is required.');
        }

        return $this->sourceGenerations->commitPage($pageId, $expectedGeneration, $write);
    }

    /**
     * @param array<int, mixed> $patches
     * @return array{after: string, error: ?string, too_large: bool}
     */
    private function patchCurrentSource(string $before, array $patches): array
    {
        [$after, $error] = ($this->sourcePatcher ?? new ExactSourcePatcher())->apply($before, $patches, 'javascript');
        if ($error !== null) {
            return [
                'after' => $before,
                'error' => $error,
                'too_large' => false,
            ];
        }

        return [
            'after' => $after,
            'error' => null,
            'too_large' => strlen($after) > self::MAX_SOURCE_BYTES,
        ];
    }
}
