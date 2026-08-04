<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\Controls\Handlers;

use UncannyPageBuilder\Application\Controls\ControlHandlerInterface;
use UncannyPageBuilder\Application\Controls\ControlInvokeRequest;
use UncannyPageBuilder\Application\Controls\ControlInvokeResult;
use UncannyPageBuilder\Application\Editing\EditableContentOwner;
use UncannyPageBuilder\Application\Editing\GlobalPartNodeUpdateService;
use UncannyPageBuilder\Application\Editing\SectionNodeUpdateService;

final class SectionNodeUpdateHandler implements ControlHandlerInterface
{
    public function __construct(
        private readonly SectionNodeUpdateService $updates,
        private readonly GlobalPartNodeUpdateService $globalPartUpdates,
    ) {}

    public function __invoke(ControlInvokeRequest $request): ControlInvokeResult
    {
        $payload = is_array($request->value()) ? $request->value() : $request->extra();
        $target = $payload['target'] ?? [];
        $changes = $payload['changes'] ?? [];

        if (!is_array($target)) {
            throw new \InvalidArgumentException('target must be an object.');
        }
        if (!is_array($changes)) {
            throw new \InvalidArgumentException('changes must be an array.');
        }

        $owner = $this->resolveOwner($request, $payload);
        $result = $owner?->isGlobalPart()
            ? $this->globalPartUpdates->update(
                globalPartId: $owner->id(),
                target: $target,
                changes: array_values(array_filter($changes, 'is_array')),
            )
            : $this->updates->update(
                pageId: $request->pageId(),
                sectionId: (int) ($payload['section_id'] ?? 0),
                target: $target,
                changes: array_values(array_filter($changes, 'is_array')),
            );

        return ControlInvokeResult::success(
            controlId: $request->controlId(),
            message: 'Node saved',
            data: $result,
        );
    }

    /** @param array<string, mixed> $payload */
    private function resolveOwner(ControlInvokeRequest $request, array $payload): ?EditableContentOwner
    {
        $ownerData = $payload['owner'] ?? null;
        if (is_array($ownerData)) {
            $owner = EditableContentOwner::fromArray($ownerData);
            if ($owner instanceof EditableContentOwner) {
                return $owner;
            }
        }

        if ($request->globalPartId() > 0) {
            return EditableContentOwner::globalPart($request->globalPartId());
        }

        return null;
    }
}
