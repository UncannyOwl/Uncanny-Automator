<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\Export;

use UncannyPageBuilder\Domain\Binding\DynamicBindingRenderMode;
use UncannyPageBuilder\Domain\Export\StaticExportHtmlRendererInterface;
use UncannyPageBuilder\Domain\Export\StaticExportFallbackHtmlRendererInterface;
use UncannyPageBuilder\Domain\Export\StaticExportPageIdentity;
use UncannyPageBuilder\Domain\Export\DynamicBindingOmissionProof;
use UncannyPageBuilder\Domain\Exception\DeactivationFallbackCompilationFailed;
use UncannyPageBuilder\Infrastructure\Rendering\CanvasRenderer;
use UncannyPageBuilder\Infrastructure\Rendering\ShortcodeBindingNormalizer;

/**
 * Renders static export HTML through the same section renderer used by pages.
 */
final class CanvasStaticExportHtmlRenderer implements StaticExportHtmlRendererInterface, StaticExportFallbackHtmlRendererInterface
{
    public function __construct(
        private readonly CanvasRenderer $renderer,
        private readonly ?ShortcodeBindingNormalizer $shortcodeBindingNormalizer = null,
    ) {}

    public function renderSection(
        array $section,
        int $pageId,
        ?StaticExportPageIdentity $pageIdentity = null,
    ): string {
        return $this->renderer->renderSectionHtml(
            (string) ($section['content']['html'] ?? ''),
            isset($section['id']) ? (int) $section['id'] : null,
            [],
            $pageIdentity,
            DynamicBindingRenderMode::FreezeOnly,
        );
    }

    public function renderFallbackSection(
        array $section,
        int $pageId,
        ?StaticExportPageIdentity $pageIdentity = null,
        ?array &$omittedBindingIds = null,
    ): string {
        $sourceHtml = (string) ($section['content']['html'] ?? '');
        $detectedBindingIds = [];
        $sourceHtml = ($this->shortcodeBindingNormalizer ?? new ShortcodeBindingNormalizer())->normalize($sourceHtml);
        $detectedBindingIds = $this->detectDynamicBindingIds($sourceHtml);

        $removedBindingIds = null;
        $html = $this->renderer->renderSectionHtml(
            $sourceHtml,
            isset($section['id']) ? (int) $section['id'] : null,
            [],
            $pageIdentity,
            DynamicBindingRenderMode::RemoveAll,
            $removedBindingIds,
        );

        if (stripos($html, 'data-ai-dynamic') !== false) {
            throw new DeactivationFallbackCompilationFailed('Deactivation fallback contains a residual dynamic binding marker.');
        }

        $omittedBindingIds = (new DynamicBindingOmissionProof(
            $detectedBindingIds,
            is_array($removedBindingIds) ? $removedBindingIds : [],
        ))->bindingIds();

        return $html;
    }

    /** @return list<string> */
    private function detectDynamicBindingIds(string $html): array
    {
        if (stripos($html, 'data-ai-dynamic') === false) {
            return [];
        }

        $doc = new \DOMDocument();
        $previousErrors = libxml_use_internal_errors(true);
        $loaded = $doc->loadHTML(
            '<meta http-equiv="Content-Type" content="text/html; charset=utf-8">'
                . '<div id="__upb_fallback_detection_root">' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD,
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previousErrors);

        if (!$loaded || !$doc->getElementById('__upb_fallback_detection_root') instanceof \DOMElement) {
            throw new DeactivationFallbackCompilationFailed('Deactivation fallback could not inspect dynamic binding regions.');
        }

        $nodes = (new \DOMXPath($doc))->query('//*[@data-ai-dynamic]');
        if ($nodes === false) {
            throw new DeactivationFallbackCompilationFailed('Deactivation fallback could not inspect dynamic binding regions.');
        }

        $bindingIds = [];
        foreach ($nodes as $node) {
            if ($node instanceof \DOMElement) {
                $bindingIds[] = trim($node->getAttribute('data-ai-dynamic'));
            }
        }

        return $bindingIds;
    }
}
