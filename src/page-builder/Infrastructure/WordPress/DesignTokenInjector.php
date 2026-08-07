<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\WordPress;

use UncannyPageBuilder\Domain\DesignStandards\DesignTokenCssRenderer;

/**
 * Injects working design tokens for authenticated page/reusable canvases.
 *
 * Must run AFTER Bootstrap's <link> tag is output (priority 50 on wp_head)
 * so that our overrides take precedence over Bootstrap's compiled defaults.
 *
 * Tokens are scoped to the canvas content container with zero specificity,
 * NOT :root/body. The canvas editor is an admin-owned standalone document
 * that also hosts WordPress editor chrome (command bar, inline toolbar,
 * agent panel) in the same DOM tree. Emitting --bs-body-font-family at
 * document scope made Bootstrap's `body { font-family:
 * var(--bs-body-font-family) }` rule apply the configured design font to
 * that chrome. Zero-specificity scoping keeps the design font on rendered
 * page content only without overriding AI-authored section classes.
 *
 * Public pages receive the token CSS frozen inside their selected artifact;
 * this adapter must never read current working design state for them.
 */
final class DesignTokenInjector
{
    /**
     * Canvas content container. :where() prevents these default rules from
     * beating generated section classes such as .ai-brut-headline.
     */
    public const CANVAS_SELECTOR = DesignTokenCssRenderer::CANVAS_SELECTOR;

    public function __construct(
        private readonly WorkingDesignTokenCss $workingCss,
    ) {}

    public function inject(): void
    {
        $isGlobalPart = is_singular('upb_global_part');
        if (!is_admin() && !$isGlobalPart) {
            return;
        }

        $postId = (int) get_the_ID();
        if ($postId <= 0) {
            return;
        }

        $css = $this->workingCss->render($postId, $isGlobalPart);

        echo '<style id="uncanny-engine-bootstrap-theme">' . $css . "</style>\n";
    }

    /**
     * Render resolved design tokens as a scoped CSS custom-property block.
     *
     * @param array<string, string|int|float> $tokens
     * @param string $selector Scope selector the tokens are emitted under.
     */
    public static function renderRootVariables(array $tokens, string $selector = self::CANVAS_SELECTOR): string
    {
        return DesignTokenCssRenderer::renderRootVariables($tokens, $selector);
    }

    /**
     * Re-apply Bootstrap body defaults at the canvas boundary only.
     */
    public static function renderCanvasBodyRules(string $selector = self::CANVAS_SELECTOR): string
    {
        return DesignTokenCssRenderer::renderCanvasBodyRules($selector);
    }

    /**
     * Render scoped typography role selectors for body descendants.
     */
    public static function renderRoleSelectors(string $selector = self::CANVAS_SELECTOR): string
    {
        return DesignTokenCssRenderer::renderRoleSelectors($selector);
    }

    /**
     * Render heading size CSS rules.
     *
     * Bootstrap's heading sizes are Sass-only (h1=2.5rem through h6=1rem).
     * We inject CSS rules that read from our --bs-heading-h*-font-size tokens,
     * allowing heading sizes to be customized without Sass recompilation.
     *
     * @param array<string, string> $tokens
     * @param string $selector Scope selector heading rules are emitted under.
     */
    public static function renderHeadingSizeRules(array $tokens, string $selector = self::CANVAS_SELECTOR): string
    {
        return DesignTokenCssRenderer::renderHeadingSizeRules($tokens, $selector);
    }
}
