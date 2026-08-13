<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\Controls\Handlers;

use UncannyPageBuilder\Application\Canvas\CanvasRefreshRendererInterface;
use UncannyPageBuilder\Application\Controls\ControlHandlerInterface;
use UncannyPageBuilder\Application\Controls\ControlInvokeRequest;
use UncannyPageBuilder\Application\Controls\ControlInvokeResult;
use UncannyPageBuilder\Application\History\HistoryOperationRestorer;
use UncannyPageBuilder\Application\History\HistoryRestoreResult;
use UncannyPageBuilder\Application\History\HistoryTransitionPreview;
use UncannyPageBuilder\Application\History\OperationHistoryService;

final class HistoryRedoHandler implements ControlHandlerInterface
{
    public function __construct(
        private readonly OperationHistoryService $history,
        private readonly CanvasRefreshRendererInterface $renderer,
        private readonly HistoryOperationRestorer $restorer,
    ) {}

    public function __invoke(ControlInvokeRequest $request): ControlInvokeResult
    {
        $pageId = $request->pageId();
        if ($pageId <= 0) {
            throw new \InvalidArgumentException('page_id is required.');
        }

        $base = $this->historyBase($request);
        $preview = $this->history->previewPageTransition(
            $pageId,
            'redo',
            $base['working_generation'],
        );
        if ($preview === null) {
            return ControlInvokeResult::success(
                controlId: $request->controlId(),
                message: 'Nothing to redo',
                controlPatches: $this->historyControlPatches($pageId),
            );
        }

        if ($base['commit']) {
            return $this->commit($request, $preview, $base['working_generation']);
        }

        return ControlInvokeResult::success(
            controlId: $request->controlId(),
            message: 'Redo previewed',
            data: $this->renderPreview($pageId, $preview),
            controlPatches: $this->historyControlPatches($pageId),
        );
    }

    /** @return array<string, mixed> */
    private function renderPreview(
        int $pageId,
        HistoryTransitionPreview $preview,
    ): array {
        $data = $preview->toArray();
        if ($preview->kind() !== 'sections') {
            return $data;
        }

        $data['rendered_sections'] = $this->renderer->withOwnerRenderContext(
            $pageId,
            fn (): array => [
                'target' => $this->renderer->renderSections(
                    is_array($preview->target()['sections'] ?? null) ? $preview->target()['sections'] : [],
                    $pageId,
                ),
                'baseline' => $this->renderer->renderSections(
                    is_array($preview->baseline()['sections'] ?? null) ? $preview->baseline()['sections'] : [],
                    $pageId,
                ),
            ],
        );

        return $data;
    }

    private function commit(
        ControlInvokeRequest $request,
        HistoryTransitionPreview $preview,
        int $workingGeneration,
    ): ControlInvokeResult {
        $transition = $this->history->applyPreviewedPageTransition(
            pageId: $request->pageId(),
            direction: 'redo',
            operationId: $preview->operationId(),
            expectedGeneration: $workingGeneration,
            restore: fn ($entry): HistoryRestoreResult => $this->restorer->restore(
                $entry,
                false,
                $request->userId(),
            ),
        );
        $result = $transition['result'];
        $page = $result->pageDetailsValue();

        return ControlInvokeResult::success(
            controlId: $request->controlId(),
            message: 'Redone',
            data: [
                'working_generation' => $workingGeneration + 1,
                'operation_id' => $transition['entry']->id(),
                'direction' => 'redo',
                'operation' => $transition['entry']->operation(),
                ...($page !== null ? ['page' => $page->toArray()] : []),
            ],
            controlPatches: $this->historyControlPatches($request->pageId()),
            layoutPatch: $result->sectionLayout(),
        );
    }

    /** @return array{working_generation: int, commit: bool} */
    private function historyBase(ControlInvokeRequest $request): array
    {
        $value = is_array($request->value()) ? $request->value() : [];
        if (($value['loaded_source'] ?? null) !== 'working') {
            throw new \InvalidArgumentException('Load the working draft before using saved history.');
        }
        $generation = filter_var(
            $value['working_generation'] ?? null,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 0]],
        );
        if ($generation === false) {
            throw new \InvalidArgumentException('Saved history requires the visible working generation.');
        }

        return [
            'working_generation' => $generation,
            'commit' => ($value['commit'] ?? false) === true,
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function historyControlPatches(int $pageId): array
    {
        return [
            [
                'id'      => 'history.undo',
                'enabled' => $this->history->canUndo(OperationHistoryService::SCOPE_PAGE, $pageId),
            ],
            [
                'id'      => 'history.redo',
                'enabled' => $this->history->canRedo(OperationHistoryService::SCOPE_PAGE, $pageId),
            ],
        ];
    }
}
