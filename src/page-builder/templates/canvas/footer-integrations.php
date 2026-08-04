<?php
/**
 * Footer integrations.
 *
 * Standalone mode owns wp_footer(), so the Alpine error bridge, the canvas
 * footer hook (Automator's launcher mounts here), the Magic Bridge root, the
 * Alpine fallback enqueue, and the Lucide refresh must all be registered
 * immediately before WordPress prints footer scripts. The caller prints
 * wp_footer() right after this partial.
 *
 * Inputs: $postId, $showEditorChrome
 */

use UncannyPageBuilder\Infrastructure\WordPress\CanvasEditorChromeGate;

defined('ABSPATH') || exit;

/*
 * Alpine auto-starts from the bundled runtime. This listener must exist
 * before any footer script can fire Alpine.start(), otherwise section badges
 * never learn about Alpine expression failures.
 */
?>
<script>
(function() {
    window.__upb_alpine_errors = window.__upb_alpine_errors || [];

    function installUpbAlpineErrorBridge() {
        if (!window.Alpine || typeof window.Alpine.setErrorHandler !== 'function') {
            return;
        }

        if (window.__upb_alpine_error_bridge_installed) {
            return;
        }

        window.__upb_alpine_error_bridge_installed = true;
        window.Alpine.setErrorHandler(function(error, el, expression) {
            var sectionEl = el && typeof el.closest === 'function'
                ? el.closest('[data-section-id]')
                : null;
            var rawSectionId = sectionEl ? sectionEl.getAttribute('data-section-id') : '';
            var sectionId = rawSectionId ? parseInt(rawSectionId, 10) : null;
            var message = error && error.message ? error.message : String(error);

            if (isNaN(sectionId)) {
                sectionId = null;
            }

            window.__upb_alpine_errors.push({
                message: message,
                sectionId: sectionId,
                element: el && el.tagName ? el.tagName.toLowerCase() : '',
                expression: expression || ''
            });
            document.dispatchEvent(new CustomEvent('upb-alpine-error', {
                detail: { sectionId: sectionId, message: message }
            }));
        });
    }

    document.addEventListener('alpine:init', installUpbAlpineErrorBridge);
    installUpbAlpineErrorBridge();
})();
</script>

<?php if ($showEditorChrome) : ?>
    <?php do_action('uncanny_page_builder_canvas_footer', $postId); ?>

    <?php
    /*
     * The canvas import command opens this shared form from inside the iframe.
     * Submit into the parent admin window so PageFactory can create the new
     * draft and return the user to the normal Pages workflow.
     */
    $pageImportFormId = 'upb-canvas-import-page-source-form';
    $pageImportFileInputId = 'upb-import-page-source-file';
    $pageImportTarget = '_top';
    $pageImportReturnContext = 'editor';
    $pageImportReturnPageId = max(0, (int) $postId);
    require UNCANNY_PB_PATH . 'templates/admin/page-source-import-form.php';
    ?>
<?php endif; ?>

<?php
$runtimeScripts = $renderer->renderCustomJavaScript(
    $postId,
    $headerData ?? null,
    $footerData ?? null,
);
if ($runtimeScripts !== '') {
    echo $runtimeScripts;
}
?>

<?php if (CanvasEditorChromeGate::currentUserHasAllowedCapability()) : ?>
    <div id="uncanny-magic-bridge-root" data-page-id="<?php echo esc_attr($postId); ?>"></div>
<?php endif; ?>

<?php
/*
 * WordPress core does not ship Alpine. Page Builder owns this runtime, so
 * only guard against this plugin's script handle being queued twice.
 */
if (
    !wp_script_is('uncanny-page-builder-alpine', 'enqueued')
    && !wp_script_is('uncanny-page-builder-alpine', 'done')
) {
    wp_enqueue_script(
        'uncanny-page-builder-alpine',
        UNCANNY_PB_URL . 'assets/js/alpine.min.js',
        [],
        UNCANNY_PB_VERSION,
        ['strategy' => 'defer', 'in_footer' => true]
    );
}
?>

<script>
(function() {
    function renderLucideIcons() {
        if (window.lucide && typeof window.lucide.createIcons === 'function') {
            window.lucide.createIcons({ icons: window.lucide.icons });
        }
    }

    renderLucideIcons();
    window.addEventListener('load', renderLucideIcons, { once: true });
})();
</script>
