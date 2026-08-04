<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\Controls\Handlers;

use UncannyPageBuilder\Application\Controls\ControlHandlerInterface;
use UncannyPageBuilder\Application\Controls\ControlInvokeRequest;
use UncannyPageBuilder\Application\Controls\ControlInvokeResult;
use UncannyPageBuilder\Application\Controls\PageDetailsPortInterface;
use UncannyPageBuilder\Application\Reusable\ReusablePortInterface;

final class PageTitleHandler implements ControlHandlerInterface
{
    public function __construct(
        private readonly PageDetailsPortInterface $pageDetails,
        private readonly ReusablePortInterface $reusables,
    ) {}

    public function __invoke(ControlInvokeRequest $request): ControlInvokeResult
    {
        $targetId = $request->pageId() > 0 ? $request->pageId() : $request->globalPartId();
        $isGlobalPart = $request->globalPartId() > 0 && $request->pageId() <= 0;
        if ($targetId <= 0) {
            throw new \InvalidArgumentException('page_id or global_part_id is required.');
        }

        if ($isGlobalPart) {
            $reusable = $this->reusables->update(
                $targetId,
                (string) $request->value(),
                null,
            );

            return ControlInvokeResult::success(
                controlId: $request->controlId(),
                message: 'Title saved',
                data: [
                    'global_part' => [
                        'id'     => $targetId,
                        'title'  => $reusable->title(),
                        'status' => $reusable->status(),
                    ],
                ],
                editorStatePatch: [
                    'page.title' => $reusable->title(),
                ],
                controlPatches: [
                    [
                        'id'    => 'page.title',
                        'value' => $reusable->title(),
                    ],
                ],
            );
        }

        $current = $this->pageDetails->find($targetId);
        if ($current === null) {
            throw new \RuntimeException('Draft page details are unavailable.');
        }

        $saved = $this->pageDetails->update(
            $targetId,
            (string) $request->value(),
            $current->slug(),
            max(0, $request->userId()),
        );

        return ControlInvokeResult::success(
            controlId: $request->controlId(),
            message: 'Draft title saved',
            data: [
                'page' => [
                    'id'     => $targetId,
                    'title'  => $saved->title(),
                ],
            ],
            editorStatePatch: [
                'page.title' => $saved->title(),
            ],
            controlPatches: [
                [
                    'id'    => 'page.title',
                    'value' => $saved->title(),
                ],
            ],
        );
    }
}
