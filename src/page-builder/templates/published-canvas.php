<?php
/**
 * Standalone public document for one exact published Page Builder artifact.
 *
 * Inputs: $postId, $publishedPage, $publishedHtml,
 * $publishedRuntimeEnabled.
 */

use UncannyPageBuilder\Application\Rendering\PublishedPage;
use UncannyPageBuilder\Application\Rendering\LucideRuntimeInitializer;
use UncannyPageBuilder\Infrastructure\Rendering\StyleElementCss;
use UncannyPageBuilder\Infrastructure\WordPress\CanvasEditorChromeGate;

defined('ABSPATH') || exit;

if (
    !isset($publishedPage)
    || !$publishedPage instanceof PublishedPage
    || !isset($publishedHtml)
    || !is_string($publishedHtml)
    || !isset($publishedRuntimeEnabled)
    || !is_bool($publishedRuntimeEnabled)
) {
    return;
}
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php wp_title('|', true, 'right'); bloginfo('name'); ?></title>

    <?php wp_head(); ?>

    <?php if ($publishedRuntimeEnabled && $publishedPage->css() !== '') : ?>
    <style id="uncanny-page-builder-published-css"><?php echo StyleElementCss::escape($publishedPage->css()); ?></style>
    <?php endif; ?>
</head>
<body <?php body_class('uncanny-canvas'); ?>>

<?php echo $publishedHtml; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- runtime projection of immutable validated artifact HTML. ?>

<?php if ($publishedRuntimeEnabled && CanvasEditorChromeGate::currentUserHasAllowedCapability()) : ?>
    <div id="uncanny-magic-bridge-root" data-page-id="<?php echo esc_attr((string) $postId); ?>"></div>
<?php endif; ?>

<?php
$publishedJavaScript = $publishedPage->customJavaScript();
if ($publishedRuntimeEnabled && $publishedJavaScript !== '') {
    echo $publishedJavaScript; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- immutable validated custom-JavaScript lane.
}
?>

<?php if ($publishedRuntimeEnabled) : ?>
<script><?php echo LucideRuntimeInitializer::script(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed application-owned script. ?></script>
<?php endif; ?>

<?php if ($publishedRuntimeEnabled) : ?>
    <?php do_action('uncanny_page_builder_canvas_footer', $postId); ?>
<?php endif; ?>
<?php wp_footer(); ?>

</body>
</html>
