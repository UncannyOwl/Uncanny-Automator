<?php
/**
 * Uncanny Page Builder: Canvas Template
 *
 * Thin orchestrator for the standalone canvas document — Page Builder owns
 * the full HTML document for every shell mode. CanvasRenderer supplies the
 * render data; the logical pieces live in templates/canvas/:
 *
 *   state.php                 shell decisions every partial consumes
 *   head.php                  wp_head, compiled CSS, cloak, emoji repair
 *   editor-shell-styles.php   chrome layout styles (chrome only)
 *   canvas-mount.php          strips, global parts, sections, empty state
 *   theme-shell-placeholder.php  one composition strip (used twice)
 *   empty-state.php           the "start building" invitation
 *   footer-integrations.php   Alpine bridge/fallback, launcher hook, Lucide
 *
 * All partials share this template's variable scope.
 */

use UncannyPageBuilder\Plugin;

if (!defined('ABSPATH')) {
    exit;
}

/*
 * Template bootstrap
 *
 * WordPress can load this file directly through template_include. In that path,
 * delegate back to CanvasRenderer so the template is rendered with the full
 * canvas data contract in scope.
 */
if (!isset($renderer)) {
    Plugin::getCanvasRenderer()->render();
    return;
}

require __DIR__ . '/canvas/state.php';
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<?php require __DIR__ . '/canvas/head.php'; ?>
<body <?php body_class('uncanny-canvas'); ?>>

<?php if ($showEditorChrome) : ?>
<div id="uncanny-pb-editor-layout" data-design-lens-enabled="false" data-agent-chat-open="false">
<aside id="uncanny-pb-tab-panel-root" aria-label="Editor panel" hidden>
    <div id="uncanny-pb-tab-panel"></div>
</aside>

<div id="uncanny-pb-workspace-root">
<!-- Editor chrome mount. -->
<div id="uncanny-pb-topbar-root">
    <div id="uncanny-pb-topbar"></div>
</div>
<?php endif; ?>

<?php require __DIR__ . '/canvas/canvas-mount.php'; ?>

<?php if ($showEditorChrome) : ?>
</div><!-- #uncanny-pb-workspace-root -->
</div><!-- #uncanny-pb-editor-layout -->
<?php endif; ?>

<?php require __DIR__ . '/canvas/footer-integrations.php'; ?>
<?php wp_footer(); ?>

</body>
</html>
