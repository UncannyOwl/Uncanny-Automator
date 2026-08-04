<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\Controls\Handlers;

use UncannyPageBuilder\Application\Controls\ControlHandlerInterface;
use UncannyPageBuilder\Application\Controls\ControlInvokeRequest;
use UncannyPageBuilder\Application\Controls\ControlInvokeResult;
use UncannyPageBuilder\Application\GlobalPartService;
use UncannyPageBuilder\Application\SectionService;
use UncannyPageBuilder\Domain\Exception\SectionNotFoundException;
use UncannyPageBuilder\Domain\GlobalPart\GlobalPartType;

final class SectionSaveAsReusableHandler implements ControlHandlerInterface
{
    public function __construct(
        private readonly GlobalPartService $globalParts,
        private readonly SectionService $sections,
    ) {}

    public function __invoke(ControlInvokeRequest $request): ControlInvokeResult
    {
        $payload = is_array($request->value()) ? $request->value() : $request->extra();
        $sectionId = $this->positiveInt($payload['section_id'] ?? null, 'section_id is required.');
        $title = trim((string) ($payload['title'] ?? ''));
        $type = GlobalPartType::fromString((string) ($payload['type'] ?? 'section'));

        if ($title === '') {
            throw new \InvalidArgumentException('title is required.');
        }

        if ($this->sections->findSection($request->pageId(), $sectionId) === null) {
            throw SectionNotFoundException::withId($sectionId);
        }

        $result = $this->globalParts->createFromSectionId($sectionId, $title, $type);
        if ((int) ($result['id'] ?? 0) <= 0) {
            throw new \RuntimeException('Reusable section was created without a valid ID.');
        }

        return ControlInvokeResult::success(
            controlId: $request->controlId(),
            message: 'Reusable saved',
            data: $result,
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
}
