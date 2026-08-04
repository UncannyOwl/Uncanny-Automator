<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\Controls\Handlers;

use UncannyPageBuilder\Application\Controls\ControlHandlerInterface;
use UncannyPageBuilder\Application\Controls\ControlInvokeRequest;
use UncannyPageBuilder\Application\Controls\ControlInvokeResult;
use UncannyPageBuilder\Application\Editing\EditableContentOwner;
use UncannyPageBuilder\Application\Editing\EditableUpdateService;
use UncannyPageBuilder\Application\Editing\GlobalPartEditableUpdateService;

final class SectionEditableUpdateHandler implements ControlHandlerInterface
{
    public function __construct(
        private readonly EditableUpdateService $updates,
        private readonly GlobalPartEditableUpdateService $globalPartUpdates,
    ) {}

    public function __invoke(ControlInvokeRequest $request): ControlInvokeResult
    {
        $payload = is_array($request->value()) ? $request->value() : $request->extra();
        $values = $payload['values'] ?? [];
        if (!is_array($values)) {
            throw new \InvalidArgumentException('values must be an object.');
        }

        $owner = $this->resolveOwner($request, $payload);
        $result = $owner?->isGlobalPart()
            ? $this->globalPartUpdates->update(
                globalPartId: $owner->id(),
                editableKey: (string) ($payload['editable_key'] ?? ''),
                editableType: (string) ($payload['editable_type'] ?? ''),
                values: $values,
            )
            : $this->updates->update(
                pageId: $request->pageId(),
                sectionId: (int) ($payload['section_id'] ?? 0),
                editableKey: (string) ($payload['editable_key'] ?? ''),
                editableType: (string) ($payload['editable_type'] ?? ''),
                values: $values,
            );

        return ControlInvokeResult::success(
            controlId: $request->controlId(),
            message: 'Editable saved',
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
