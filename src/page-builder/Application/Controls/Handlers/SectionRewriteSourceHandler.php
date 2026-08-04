<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\Controls\Handlers;

use UncannyPageBuilder\Application\Controls\ControlHandlerInterface;
use UncannyPageBuilder\Application\Controls\ControlInvokeRequest;
use UncannyPageBuilder\Application\Controls\ControlInvokeResult;
use UncannyPageBuilder\Application\SectionService;
use UncannyPageBuilder\Domain\Exception\SectionNotFoundException;
use UncannyPageBuilder\Domain\Section\SectionRepositoryInterface;

final class SectionRewriteSourceHandler implements ControlHandlerInterface
{
    public function __construct(
        private readonly SectionService $sections,
        private readonly SectionRepositoryInterface $repository,
    ) {}

    public function __invoke(ControlInvokeRequest $request): ControlInvokeResult
    {
        $payload = is_array($request->value()) ? $request->value() : $request->extra();
        $sectionId = $this->positiveInt($payload['section_id'] ?? 0, 'section_id must be a positive integer.');
        $html = $payload['html'] ?? null;
        $css = $payload['css'] ?? '';

        if (!is_string($html) || trim($html) === '') {
            throw new \InvalidArgumentException('html is required.');
        }

        if (!is_string($css)) {
            throw new \InvalidArgumentException('css must be a string.');
        }

        $section = $this->repository->findById($sectionId);
        if ($section->pageId() !== $request->pageId()) {
            throw SectionNotFoundException::withId($sectionId);
        }

        $name = trim((string) ($payload['name'] ?? '')) ?: $section->name();
        $result = $this->sections->create(
            pageId: $request->pageId(),
            sectionName: $name,
            content: ['html' => $html, 'css' => $css],
            action: 'edit_section',
            sectionId: $sectionId,
        );

        $layout = $this->sections->getLayout($request->pageId());
        $saved = $this->repository->findById($sectionId);

        return ControlInvokeResult::success(
            controlId: $request->controlId(),
            message: 'Section code saved',
            data: [
                'section_id' => $saved->id(),
                'page_id'    => $request->pageId(),
                'position'   => $saved->position(),
                'name'       => $saved->name(),
                'preview'    => $result['preview'],
            ],
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
