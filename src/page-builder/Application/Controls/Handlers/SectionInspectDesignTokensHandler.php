<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\Controls\Handlers;

use UncannyPageBuilder\Application\Controls\ControlHandlerInterface;
use UncannyPageBuilder\Application\Controls\ControlInvokeRequest;
use UncannyPageBuilder\Application\Controls\ControlInvokeResult;
use UncannyPageBuilder\Application\DesignStandardsService;
use UncannyPageBuilder\Application\SectionService;
use UncannyPageBuilder\Domain\Exception\SectionNotFoundException;

final class SectionInspectDesignTokensHandler implements ControlHandlerInterface
{
    public function __construct(
        private readonly SectionService $sections,
        private readonly DesignStandardsService $designStandards,
    ) {}

    public function __invoke(ControlInvokeRequest $request): ControlInvokeResult
    {
        $payload = is_array($request->value()) ? $request->value() : $request->extra();
        $sectionId = $this->positiveInt($payload['section_id'] ?? null, 'section_id is required.');

        $section = $this->sections->findSection($request->pageId(), $sectionId);
        if ($section === null) {
            throw SectionNotFoundException::withId($sectionId);
        }

        $result = $this->designStandards->getConsumedTokens(
            $section->content()->css(),
            $section->content()->html(),
            $request->pageId(),
        );

        return ControlInvokeResult::success(
            controlId: $request->controlId(),
            message: 'Section inspected',
            data: [
                'section_id'      => $sectionId,
                'page_id'         => $request->pageId(),
                'consumed_tokens' => $result['consumed_tokens'],
                'resolved_values' => $result['resolved_values'],
            ],
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
