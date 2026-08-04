<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\Controls\Handlers;

use UncannyPageBuilder\Application\Controls\ControlHandlerInterface;
use UncannyPageBuilder\Application\Controls\ControlInvokeRequest;
use UncannyPageBuilder\Application\Controls\ControlInvokeResult;
use UncannyPageBuilder\Application\Editor\SelectEditorPageSource;
use UncannyPageBuilder\Domain\Concurrency\SourceGenerationSnapshot;
use UncannyPageBuilder\Domain\Concurrency\SourceGenerationStoreInterface;
use UncannyPageBuilder\Domain\Publishing\DraftResumePolicy;
use UncannyPageBuilder\Domain\Publishing\PageStateRepositoryInterface;

/**
 * Records the human choice to reopen a parked working draft.
 */
final class PageResumeDraftHandler implements ControlHandlerInterface
{
    public function __construct(
        private readonly SelectEditorPageSource $pageSources,
        private readonly PageStateRepositoryInterface $pageStates,
        private readonly SourceGenerationStoreInterface $sourceGenerations,
    ) {}

    public function __invoke(ControlInvokeRequest $request): ControlInvokeResult
    {
        if ($request->pageId() <= 0 || $request->globalPartId() > 0) {
            throw new \InvalidArgumentException('A page draft is required.');
        }

        $value = is_array($request->value()) ? $request->value() : [];
        $expectedSnapshotId = (int) ($value['snapshot_id'] ?? 0);
        $expectedWorkingGeneration = (int) ($value['working_generation'] ?? -1);
        if ($expectedSnapshotId <= 0 || $expectedWorkingGeneration < 0) {
            throw new \InvalidArgumentException('The saved draft source identity is invalid.');
        }

        $guard = new SourceGenerationSnapshot(
            pageId: $request->pageId(),
            pageGeneration: $expectedWorkingGeneration,
            globalGeneration: $this->sourceGenerations->globalGeneration(),
        );
        $this->sourceGenerations->publishIfCurrent(
            $guard,
            function () use ($request, $expectedSnapshotId, $expectedWorkingGeneration): void {
                $selection = $this->pageSources->forPage($request->pageId());
                $snapshotId = $selection->publishedSnapshot()?->id() ?? 0;

                if (
                    !$selection->shouldOfferParkedDraft()
                    || $snapshotId !== $expectedSnapshotId
                    || $selection->workingGeneration() !== $expectedWorkingGeneration
                ) {
                    throw new \RuntimeException(
                        'The saved draft changed. Reload the editor before choosing a revision.',
                    );
                }

                $this->pageStates->saveDraftResumePolicy(
                    $request->pageId(),
                    DraftResumePolicy::Active,
                );
            },
        );

        return ControlInvokeResult::success(
            controlId: $request->controlId(),
            message: 'Draft loaded',
            data: [
                'working_generation' => $expectedWorkingGeneration,
            ],
        );
    }
}
