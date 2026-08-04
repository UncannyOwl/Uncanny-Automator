<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\Controls\Handlers;

use UncannyPageBuilder\Application\Controls\ControlHandlerInterface;
use UncannyPageBuilder\Application\Controls\ControlInvokeRequest;
use UncannyPageBuilder\Application\Controls\ControlInvokeResult;
use UncannyPageBuilder\Application\SectionService;

final class SectionReorderHandler implements ControlHandlerInterface
{
    public function __construct(
        private readonly SectionService $sections,
    ) {}

    public function __invoke(ControlInvokeRequest $request): ControlInvokeResult
    {
        $payload = is_array($request->value()) ? $request->value() : $request->extra();
        $sectionIds = $payload['section_ids'] ?? null;
        if (!is_array($sectionIds) || $sectionIds === []) {
            throw new \InvalidArgumentException('section_ids must be a non-empty array.');
        }

        $orderedIds = array_map(
            fn (mixed $id): int => $this->positiveInt($id, 'section_ids must contain only positive integers.'),
            $sectionIds,
        );
        $result = $this->sections->reorder($request->pageId(), $orderedIds);
        $layout = $this->sections->getLayout($request->pageId());

        return ControlInvokeResult::success(
            controlId: $request->controlId(),
            message: 'Sections reordered',
            data: array_merge($result, ['layout' => $layout]),
            editorStatePatch: ['sections' => $this->sectionMeta($layout['sections'])],
            layoutPatch: $layout,
        );
    }

    private function positiveInt(mixed $value, string $message): int
    {
        if (is_int($value)) {
            $int = $value;
        } elseif (is_string($value) && preg_match('/^[1-9][0-9]*$/', $value) === 1) {
            $int = (int) $value;
        } else {
            throw new \InvalidArgumentException($message);
        }

        if ($int <= 0) {
            throw new \InvalidArgumentException($message);
        }

        return $int;
    }

    /**
     * @param array<int, array<string, mixed>> $sections
     * @return array<int, array<string, mixed>>
     */
    private function sectionMeta(array $sections): array
    {
        return array_map(
            static fn (array $section): array => [
                'id'       => (int) ($section['id'] ?? 0),
                'position' => (int) ($section['position'] ?? 0),
                'name'     => (string) ($section['name'] ?? ''),
            ],
            $sections,
        );
    }
}
