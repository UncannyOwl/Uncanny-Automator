<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\Rendering;

use UncannyPageBuilder\Application\Canvas\CanvasGlobalPartRendererInterface;
use UncannyPageBuilder\Application\Canvas\ResolveEmptyCanvasInvitation;
use UncannyPageBuilder\Application\Editor\SelectEditorPageSource;
use UncannyPageBuilder\Application\GlobalPartDefaultsResolverInterface;
use UncannyPageBuilder\Application\ShellModeService;
use UncannyPageBuilder\Domain\Canvas\AlpineVisibilityGuard;
use UncannyPageBuilder\Domain\Canvas\EmptyCanvasInvitation;
use UncannyPageBuilder\Domain\Compiler\ShadowCompiler;
use UncannyPageBuilder\Domain\Binding\DynamicBindingRenderMode;
use UncannyPageBuilder\Domain\Export\StaticExportPageIdentity;
use UncannyPageBuilder\Domain\GlobalPart\GlobalPartRepositoryInterface;
use UncannyPageBuilder\Domain\GlobalPart\GlobalPartType;
use UncannyPageBuilder\Domain\GlobalPart\PageGlobalPartResolverInterface;
use UncannyPageBuilder\Domain\Section\SectionRepositoryInterface;
use UncannyPageBuilder\Domain\Shell\ShellMode;
use UncannyPageBuilder\Infrastructure\Automator\AutomatorSetupWizardUrl;
use UncannyPageBuilder\Infrastructure\Section\HtmlBridgeArtifactCleaner;
use UncannyPageBuilder\Infrastructure\WordPress\WordPressPostId;

final class CanvasRenderer implements CanvasGlobalPartRendererInterface
{
    private readonly WorkingCanvasSource $workingCanvas;

    public function __construct(
        private readonly GlobalPartRepositoryInterface $globalPartRepo,
        SectionRepositoryInterface $sectionRepo,
        private readonly DynamicRenderer $dynamicRenderer,
        private readonly string $pluginPath,
        ?ShellModeService $shellModeService = null,
        ?PageGlobalPartResolverInterface $pagePartResolver = null,
        private readonly ?ShortcodeBindingNormalizer $shortcodeBindingNormalizer = null,
        ?PageJavaScriptRuntimeRenderer $javaScriptRuntimeRenderer = null,
        ?WorkingCanvasSource $workingCanvas = null,
        ?SelectEditorPageSource $pageSources = null,
        ?ShadowCompiler $compiler = null,
        ?GlobalPartDefaultsResolverInterface $globalPartDefaults = null,
        private readonly ?ResolveEmptyCanvasInvitation $resolveEmptyCanvasInvitation = null,
        private readonly ?AutomatorSetupWizardUrl $agentSetupUrl = null,
    ) {
        $this->workingCanvas = $workingCanvas ?? new WorkingCanvasSource(
            $sectionRepo,
            $globalPartRepo,
            $shellModeService,
            $pagePartResolver,
            $javaScriptRuntimeRenderer,
            $pageSources,
            $compiler,
            $globalPartDefaults,
        );
    }

    public function render(): void
    {
        $postId = WordPressPostId::fromCurrentQuery(get_queried_object_id());
        if ($postId === null) {
            return;
        }

        // Global part canvas — render only the global part's sections.
        if (get_post_type($postId) === 'upb_global_part') {
            $this->renderGlobalPartCanvas($postId);
            return;
        }

        $state = $this->workingCanvas->read($postId);
        $sections = $state['sections'];
        $compiledCss = $state['compiled_css'];
        $shellMode = $state['shell_mode'];
        $headerData = $state['header'];
        $footerData = $state['footer'];
        $emptyCanvasInvitation = $this->emptyCanvasInvitation($sections);
        $agentSetupUrl = $emptyCanvasInvitation === EmptyCanvasInvitation::SetupAgent
            ? ($this->agentSetupUrl?->get() ?? '')
            : '';

        // Load the template with data in scope
        $renderer = $this;
        include $this->pluginPath . 'templates/canvas.php';
    }

    /**
     * Render a global part alone in its own canvas view.
     */
    private function renderGlobalPartCanvas(int $globalPartId): void
    {
        $part = $this->globalPartRepo->findById($globalPartId);

        $postId      = $globalPartId;
        $sections    = $part !== null ? ($part['sections'] ?? []) : [];
        $compiledCss = $part !== null ? ($part['css'] ?? '') : '';
        $shellMode   = ShellMode::UncannyNative;
        $headerData  = null;
        $footerData  = null;
        $emptyCanvasInvitation = $this->emptyCanvasInvitation($sections);
        $agentSetupUrl = $emptyCanvasInvitation === EmptyCanvasInvitation::SetupAgent
            ? ($this->agentSetupUrl?->get() ?? '')
            : '';

        $renderer = $this;
        include $this->pluginPath . 'templates/canvas.php';
    }

    /**
     * @param array<int, mixed> $sections
     */
    private function emptyCanvasInvitation(array $sections): EmptyCanvasInvitation
    {
        if ($this->resolveEmptyCanvasInvitation === null) {
            return $sections === []
                ? EmptyCanvasInvitation::StartAgent
                : EmptyCanvasInvitation::None;
        }

        return ($this->resolveEmptyCanvasInvitation)($sections !== []);
    }

    /**
     * Render a section's HTML, applying dynamic rendering if needed.
     */
    /**
     * @param array<string, bool|int|string> $attributes
     */
    public function renderSectionHtml(
        string $html,
        ?int $sectionId = null,
        array $attributes = [],
        ?StaticExportPageIdentity $pageIdentity = null,
        DynamicBindingRenderMode $bindingMode = DynamicBindingRenderMode::ResolveAll,
    ): string {
        $html = $this->sanitizeRenderHtml($html);
        $html = ($this->shortcodeBindingNormalizer ?? new ShortcodeBindingNormalizer())->normalize($html);

        if (strpos($html, 'data-ai-dynamic') !== false) {
            $html = $this->dynamicRenderer->render($html, $pageIdentity, $bindingMode);
        }

        // Lazy-load images that don't already have a loading attribute.
        $html = preg_replace(
            '/<img(?![^>]*\bloading\s*=)([^>]*?)(\s*\/?>)/i',
            '<img loading="lazy"$1$2',
            $html
        ) ?? $html;

        if ($sectionId !== null) {
            $attributes = [
                'id'              => 'upb-section-' . (int) $sectionId,
                'data-section-id' => (int) $sectionId,
            ] + $attributes;
        }

        if ($attributes !== [] && $html !== '') {
            if (isset($attributes['id'])) {
                $html = preg_replace(
                    '/^((?:\s|<!--[\s\S]*?-->)*<[a-z][a-z0-9]*\b[^>]*\sid\s*=\s*)(["\'])(.*?)\2/i',
                    '$1$2' . (string) $attributes['id'] . '$2',
                    $html,
                    1,
                ) ?? $html;
                if (
                    str_contains($html, 'id="' . (string) $attributes['id'] . '"')
                    || str_contains($html, "id='" . (string) $attributes['id'] . "'")
                ) {
                    unset($attributes['id']);
                }
            }

            $html = preg_replace(
                '/^((?:\s|<!--[\s\S]*?-->)*<[a-z][a-z0-9]*)/i',
                '$1' . $this->renderRootAttributes($attributes),
                $html,
                1
            ) ?? $html;
        }

        try {
            $filteredHtml = apply_filters('uncanny_page_builder_section_html', $html, $sectionId);
        } catch (\Throwable $failure) {
            // This filter runs while the canvas and static exports render. Keep
            // the rendered section when an external callback fails.
            $this->reportExternalCallbackFailure('uncanny_page_builder_section_html', $failure);
            $filteredHtml = $html;
        }
        if (is_string($filteredHtml)) {
            $html = $filteredHtml;
        }

        return AlpineVisibilityGuard::addCloakToXShow($html);
    }

    /**
     * Notify optional observers before a section renders.
     *
     * @param array<string, mixed> $section
     */
    public function notifyBeforeSectionRender(array $section, int $ownerId): void
    {
        try {
            do_action('uncanny_page_builder_before_section_render', $section, $ownerId);
        } catch (\Throwable $failure) {
            // This action is an optional render observer. Its failure cannot
            // replace regular page or global part output.
            $this->reportExternalCallbackFailure('uncanny_page_builder_before_section_render', $failure);
        }
    }

    /**
     * Render global part sections (header or footer).
     */
    public function renderGlobalPart(?array $data): void
    {
        if ($data === null) {
            return;
        }

        if (!empty($data['css'])) {
            $canvasCss = ShadowCompiler::scopeCssToCanvas(ShadowCompiler::repairCss($data['css']));
            echo '<style id="uncanny-page-builder-' . esc_attr($data['post_id']) . '-css">'
                . StyleElementCss::escape($canvasCss) . '</style>';
        }

        if (!empty($data['sections'])) {
            foreach ($data['sections'] as $section) {
                $this->notifyBeforeSectionRender($section, (int) $data['post_id']);
                $html = $section['content']['html'] ?? '';
                echo $this->renderSectionHtml(
                    $html,
                    $section['id'] ?? null,
                    $this->globalPartOwnerAttributes($data, $section),
                );
            }
        }
    }

    public function renderGlobalPartSnapshot(array $part): array
    {
        $html = '';

        foreach (($part['sections'] ?? []) as $section) {
            if (!is_array($section)) {
                continue;
            }

            $this->notifyBeforeSectionRender($section, (int) ($part['post_id'] ?? 0));
            $html .= $this->renderSectionHtml(
                (string) ($section['content']['html'] ?? ''),
                isset($section['id']) ? (int) $section['id'] : null,
                $this->globalPartOwnerAttributes($part, $section),
            );
        }

        $css = (string) ($part['css'] ?? '');

        return [
            'post_id' => (int) ($part['post_id'] ?? 0),
            'type'    => (string) ($part['type'] ?? GlobalPartType::Section->value),
            'html'    => $html,
            'css'     => $css !== ''
                ? ShadowCompiler::scopeCssToCanvas(ShadowCompiler::repairCss($css))
                : '',
        ];
    }

    /**
     * @param array<string, mixed>|null $headerData
     * @param array<string, mixed>|null $footerData
     */
    public function renderCustomJavaScript(int $postId, ?array $headerData = null, ?array $footerData = null): string
    {
        return $this->workingCanvas->javaScript($postId, $headerData, $footerData);
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $section
     * @return array<string, bool|int|string>
     */
    private function globalPartOwnerAttributes(array $data, array $section): array
    {
        return [
            'data-upb-owner-kind'      => 'global_part',
            'data-upb-owner-part-id'   => (int) ($data['post_id'] ?? 0),
            'data-upb-owner-part-type' => (string) ($data['type'] ?? GlobalPartType::Section->value),
        ];
    }

    /**
     * @param array<string, bool|int|string> $attributes
     */
    private function renderRootAttributes(array $attributes): string
    {
        $out = '';
        foreach ($attributes as $name => $value) {
            if (!is_string($name) || preg_match('/^[a-zA-Z][a-zA-Z0-9_-]*$/', $name) !== 1) {
                continue;
            }

            $out .= ' ' . $name . '="' . esc_attr((string) $value) . '"';
        }

        return $out;
    }

    private function sanitizeRenderHtml(string $html): string
    {
        return HtmlBridgeArtifactCleaner::clean($html);
    }

    private function reportExternalCallbackFailure(string $hook, \Throwable $failure): void
    {
        try {
            error_log(sprintf(
                '[Uncanny Page Builder] WordPress callback "%s" failed (%s).',
                $hook,
                $failure::class,
            ));
        } catch (\Throwable) {
            // A log failure cannot replace rendered output.
        }
    }

    private function removeClass(\DOMElement $node, string $className): void
    {
        $classAttr = trim($node->getAttribute('class'));
        if ($classAttr === '') {
            return;
        }

        $classes = preg_split('/\s+/', $classAttr) ?: [];
        $classes = array_values(array_filter(
            $classes,
            static fn(string $candidate): bool => $candidate !== $className
        ));

        if (empty($classes)) {
            $node->removeAttribute('class');
            return;
        }

        $node->setAttribute('class', implode(' ', $classes));
    }
}
