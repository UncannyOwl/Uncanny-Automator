<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\Controls\Handlers;

use UncannyPageBuilder\Application\Controls\ControlHandlerInterface;
use UncannyPageBuilder\Application\Controls\ControlInvokeRequest;
use UncannyPageBuilder\Application\Controls\ControlInvokeResult;
use UncannyPageBuilder\Application\Publishing\PageDraftStatusPortInterface;
use UncannyPageBuilder\Application\Publishing\PageLiveStateReaderInterface;
use UncannyPageBuilder\Application\Publishing\PublishPageInterface;
use UncannyPageBuilder\Application\Publishing\SwitchPageToDraftInterface;
use UncannyPageBuilder\Domain\Concurrency\SourceGenerationSnapshot;
use UncannyPageBuilder\Domain\Concurrency\SourceGenerationStoreInterface;
use UncannyPageBuilder\Domain\Publishing\PageStateRepositoryInterface;
use UncannyPageBuilder\Domain\Publishing\PageLiveState;
use UncannyPageBuilder\Domain\Publishing\DraftResumePolicy;

final class PageStatusHandler implements ControlHandlerInterface
{
    public function __construct(
        private readonly PublishPageInterface $publishPage,
        private readonly SwitchPageToDraftInterface $switchPageToDraft,
        private readonly PageDraftStatusPortInterface $visibility,
        private readonly PageLiveStateReaderInterface $liveState,
        private readonly ?PageStateRepositoryInterface $pageStates = null,
        private readonly ?SourceGenerationStoreInterface $sourceGenerations = null,
    ) {}

    public function __invoke(ControlInvokeRequest $request): ControlInvokeResult
    {
        if ($request->globalPartId() > 0) {
            return $this->acknowledgeGlobalPartSave($request);
        }

        if ($request->pageId() <= 0) {
            throw new \InvalidArgumentException('page_id is required.');
        }

        return match ($request->controlId()) {
            'page.save_draft' => $this->acknowledgeWorkingDraftSave($request),
            'page.make_live' => $this->makeLive($request),
            'page.switch_to_draft' => $this->moveToDraft($request),
            'page.publish', 'page.save_published' => $this->publish($request),
            default => throw new \InvalidArgumentException('Unsupported page status command.'),
        };
    }

    private function publish(ControlInvokeRequest $request): ControlInvokeResult
    {
        $this->expectStatus($request, 'publish');
        $expectedGeneration = $this->expectedGeneration($request->value());
        if ($expectedGeneration === null) {
            throw new \InvalidArgumentException('Publish requires the visible saved generation.');
        }
        $publication = $this->publishPage->publish(
            $request->pageId(),
            max(0, $request->userId()),
            $expectedGeneration,
        );
        return ControlInvokeResult::success(
            controlId: $request->controlId(),
            message: 'Changes published',
            data: [
                'publication' => $publication->toArray(),
            ],
        );
    }

    private function makeLive(ControlInvokeRequest $request): ControlInvokeResult
    {
        $this->expectStatus($request, 'publish');
        $currentStatus = $this->visibility->currentStatus($request->pageId());
        if ($currentStatus === 'trash') {
            throw new \InvalidArgumentException('Restore this page before making it live.');
        }
        $hasPublishedArtifact = $this->pageStates instanceof PageStateRepositoryInterface
            ? $this->pageStates->findForPage($request->pageId())?->isPublished() === true
            : $this->liveState->forPage($request->pageId()) !== PageLiveState::Draft;
        if (!$hasPublishedArtifact) {
            throw new \InvalidArgumentException('Publish changes before making this page live.');
        }
        if ($currentStatus !== 'publish') {
            $this->visibility->setPublished($request->pageId());
        }

        return $this->visibilityResult($request, 'publish', 'Page is live');
    }

    /**
     * Pending Manual edits are persisted before this control is invoked. This
     * acknowledgement must never change the live page status; only the explicit
     * page.switch_to_draft lifecycle command may take a page offline.
     */
    private function acknowledgeWorkingDraftSave(ControlInvokeRequest $request): ControlInvokeResult
    {
        $this->expectStatus($request, 'draft');
        $expectedGeneration = $this->expectedGeneration($request->value());
        if ($expectedGeneration !== null) {
            if (
                !$this->pageStates instanceof PageStateRepositoryInterface
                || !$this->sourceGenerations instanceof SourceGenerationStoreInterface
            ) {
                throw new \LogicException('Draft source generation services are unavailable.');
            }

            $guard = new SourceGenerationSnapshot(
                pageId: $request->pageId(),
                pageGeneration: $expectedGeneration,
                globalGeneration: $this->sourceGenerations->globalGeneration(),
            );
            $this->sourceGenerations->publishIfCurrent(
                $guard,
                function () use ($request): void {
                    $this->pageStates?->saveDraftResumePolicy(
                        $request->pageId(),
                        DraftResumePolicy::Parked,
                    );
                },
            );
        }

        return ControlInvokeResult::success(
            controlId: $request->controlId(),
            message: 'Draft saved',
            data: $expectedGeneration !== null
                ? ['working_generation' => $expectedGeneration]
                : [],
        );
    }

    private function moveToDraft(ControlInvokeRequest $request): ControlInvokeResult
    {
        $this->expectStatus($request, 'draft');
        $draft = $this->switchPageToDraft->switch($request->pageId());
        $resolvedStatus = $draft->status();

        return $this->visibilityResult(
            $request,
            $resolvedStatus,
            $draft->previousStatus() === 'publish' ? 'Page moved to draft' : 'Page is a draft',
        );
    }

    private function visibilityResult(
        ControlInvokeRequest $request,
        string $status,
        string $message,
    ): ControlInvokeResult {
        return ControlInvokeResult::success(
            controlId: $request->controlId(),
            message: $message,
            data: [
                'page' => [
                    'id'     => $request->pageId(),
                    'status' => $status,
                ],
            ],
            editorStatePatch: [
                'page.status' => $status,
            ],
            controlPatches: [
                [
                    'id'    => 'page.status',
                    'value' => $status,
                ],
            ],
        );
    }

    /**
     * Reusables are always-published internal artifacts with no status
     * transition. Their edited content is committed by the pending-changes save
     * that runs before this handler, so the "Update" action only needs to
     * acknowledge the save.
     */
    private function acknowledgeGlobalPartSave(ControlInvokeRequest $request): ControlInvokeResult
    {
        return ControlInvokeResult::success(
            controlId: $request->controlId(),
            message: 'Updated',
        );
    }

    private function expectStatus(ControlInvokeRequest $request, string $expected): void
    {
        if ($this->status($request->value()) !== $expected) {
            throw new \InvalidArgumentException('Unsupported page status.');
        }
    }

    private function status(mixed $value): string
    {
        if (is_array($value)) {
            return trim((string) ($value['status'] ?? ''));
        }

        return trim((string) $value);
    }

    private function expectedGeneration(mixed $value): ?int
    {
        if (!is_array($value) || !array_key_exists('expected_generation', $value)) {
            return null;
        }

        $generation = filter_var(
            $value['expected_generation'],
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 0]],
        );
        if ($generation === false) {
            throw new \InvalidArgumentException('A valid saved generation is required.');
        }

        return $generation;
    }
}
