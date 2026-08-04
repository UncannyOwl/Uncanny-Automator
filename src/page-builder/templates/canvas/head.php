<?php
/**
 * Canvas document <head>.
 *
 * wp_head, the Alpine cloak, the compiled page CSS (scoped to the canvas),
 * the public canvas head filter, and the emoji repair styles. Editor shell
 * styles load only with chrome.
 *
 * Inputs: $compiledCss, $showEditorChrome
 */

use UncannyPageBuilder\Domain\Compiler\ShadowCompiler;
use UncannyPageBuilder\Domain\Canvas\AlpineVisibilityGuard;
use UncannyPageBuilder\Domain\Canvas\CanvasResetCss;
use UncannyPageBuilder\Infrastructure\Rendering\StyleElementCss;

defined('ABSPATH') || exit;
?>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php wp_title('|', true, 'right'); bloginfo('name'); ?></title>

    <?php wp_head(); ?>

    <style id="uncanny-page-builder-alpine-cloak"><?php echo esc_html(AlpineVisibilityGuard::cloakCss()); ?></style>

    <?php if (!empty($compiledCss)) : ?>
    <?php $canvasCss = ShadowCompiler::scopeCssToCanvas(ShadowCompiler::repairCss((string) $compiledCss)); ?>
    <style id="uncanny-page-builder-css"><?php echo StyleElementCss::escape($canvasCss); ?></style>
    <?php endif; ?>

    <?php
    /*
     * Standalone canvas documents need the same inherited-style barrier as
     * frontend composition renders so previews and admin canvas surfaces do
     * not pick up hostile admin/theme typography before section CSS applies.
     */
    ?>
    <style id="uncanny-page-builder-reset"><?php echo StyleElementCss::escape(CanvasResetCss::render()); ?></style>

    <?php echo wp_kses_post(apply_filters('uncanny_page_builder_canvas_head', '')); ?>

    <?php
    /*
     * Canvas-authored CSS can contain broad image rules. WordPress replaces
     * emoji text in the admin bar with img.emoji, so keep those inline icons
     * immune to canvas image sizing whenever the admin bar is visible.
     */
    ?>
    <style id="uncanny-page-builder-emoji-repair">
        img.wp-smiley,
        img.emoji {
            display: inline !important;
            border: none !important;
            box-shadow: none !important;
            height: 1em !important;
            width: 1em !important;
            max-width: 1em !important;
            margin: 0 0.07em !important;
            vertical-align: -0.1em !important;
            background: none !important;
            padding: 0 !important;
        }
    </style>

    <?php if ($showEditorChrome) : ?>
        <?php require __DIR__ . '/editor-shell-styles.php'; ?>
    <?php endif; ?>
</head>
