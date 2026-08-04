<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\Controls\Handlers;

use UncannyPageBuilder\Application\Controls\ControlHandlerInterface;
use UncannyPageBuilder\Application\Controls\ControlInvokeRequest;
use UncannyPageBuilder\Application\Controls\ControlInvokeResult;
use UncannyPageBuilder\Application\History\OperationHistoryService;

final class HistoryUndoHandler implements ControlHandlerInterface
{
    public function __construct(
        private readonly OperationHistoryService $history,
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
            'undo',
            $base['working_generation'],
        );
        if ($preview === null) {
            return ControlInvokeResult::success(
                controlId: $request->controlId(),
                message: 'Nothing to undo',
                controlPatches: $this->historyControlPatches($pageId),
            );
        }

        return ControlInvokeResult::success(
            controlId: $request->controlId(),
            message: 'Undo previewed',
            data: $preview->toArray(),
            controlPatches: $this->historyControlPatches($pageId),
        );
    }

    /** @return array{working_generation: int} */
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

        return ['working_generation' => $generation];
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
