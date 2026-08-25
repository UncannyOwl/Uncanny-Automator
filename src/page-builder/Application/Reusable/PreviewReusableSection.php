<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\Reusable;

use UncannyPageBuilder\Application\Canvas\CanvasRefreshRendererInterface;
use UncannyPageBuilder\Application\GlobalPartService;
use UncannyPageBuilder\Domain\Compiler\ShadowCompiler;
use UncannyPageBuilder\Domain\Section\SectionCollection;
use UncannyPageBuilder\Domain\Section\SectionContent;
use UncannyPageBuilder\Domain\Section\SectionRootIdentityRemapper;

/**
 * Build one reusable section projection in the target page context.
 */
final class PreviewReusableSection
{
    public function __construct(
        private readonly GlobalPartService $globalParts,
        private readonly CanvasRefreshRendererInterface $renderer,
        private readonly ShadowCompiler $compiler,
    ) {}

    /**
     * Resolve dynamic bindings and compile styles for a browser-only section.
     *
     * @return array{
     *     source: array<string, mixed>,
     *     content: array<string, mixed>,
     *     rendered_html: string,
     *     compiled_css: string
     * }
     */
    public function render(int $globalPartId, int $pageId, int $previewSectionId): array
    {
        $source = $this->globalParts->resolveSourceContent($globalPartId);
        if (!is_array($source) || !is_array($source['content'] ?? null)) {
            throw new \OutOfBoundsException('Reusable section source was not found.');
        }

        $sourceSectionId = is_int($source['section_id'] ?? null) ? $source['section_id'] : 0;
        if ($sourceSectionId <= 0) {
            throw new \RuntimeException('Reusable section source identity is unavailable.');
        }

        $sourceContent = SectionContent::fromArray($source['content']);
        $embeddedSectionId = SectionRootIdentityRemapper::rootSectionId($sourceContent);
        $previewContent = SectionRootIdentityRemapper::remap(
            $sourceContent,
            $sourceSectionId,
            $previewSectionId,
        );
        if ($embeddedSectionId !== null && $embeddedSectionId !== $sourceSectionId) {
            $previewContent = SectionRootIdentityRemapper::remap(
                $previewContent,
                $embeddedSectionId,
                $previewSectionId,
            );
        }

        $previewSection = [
            'id' => $previewSectionId,
            'name' => (string) ($source['title'] ?? ''),
            'content' => $previewContent->toArray(),
        ];
        $projection = $this->renderer->withOwnerRenderContext(
            $pageId,
            fn (): array => $this->renderer->renderSections([$previewSection], $pageId),
        );
        if (
            !isset($projection[0])
            || (int) ($projection[0]['id'] ?? 0) !== $previewSectionId
            || !is_string($projection[0]['html'] ?? null)
        ) {
            throw new \RuntimeException('Reusable section rendering returned an invalid projection.');
        }

        return [
            'source' => $source,
            'content' => $previewContent->toArray(),
            'rendered_html' => $projection[0]['html'],
            'compiled_css' => $this->compiler
                ->compilePreview(SectionCollection::fromArray([$previewSection], $pageId))
                ->minifiedCss(),
        ];
    }
}
