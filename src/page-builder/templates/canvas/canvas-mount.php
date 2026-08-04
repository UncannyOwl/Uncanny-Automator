<?php
/**
 * Canvas document mount.
 *
 * The canvas column: theme-shell placeholder strips (composition chrome
 * only), global header part, sections, the empty state, global footer part,
 * and the modal mount.
 *
 * Inputs: $renderer, $postId, $sections, $headerData, $footerData,
 *         $editorScopeAttr, $showEditorChrome, $hasCanvasSections,
 *         $showThemeShellPlaceholders, $themeShellPreviewUrl,
 *         $emptyCanvasHeading, $emptyCanvasBody, $emptyCanvasActionKind,
 *         $emptyCanvasActionLabel, $emptyCanvasActionUrl,
 *         $isEmbeddedCanvasHost
 */

defined('ABSPATH') || exit;
?>
<!-- Canvas document mount. -->
<div
    id="uncanny-pb-canvas-root"
    data-upb-editor-scope="<?php echo esc_attr($editorScopeAttr); ?>"
    data-upb-embedded-host="<?php echo $isEmbeddedCanvasHost ? '1' : '0'; ?>"
>
<?php if ($showThemeShellPlaceholders) : ?>
    <?php
    $themeShellPlaceholderLabel = _x("Your website's header renders here.", 'Page Builder', 'uncanny-automator');
    require __DIR__ . '/theme-shell-placeholder.php';
    ?>
<?php endif; ?>
<div id="uncanny-pb-canvas" class="<?php echo $showEditorChrome && !$hasCanvasSections ? 'upb-canvas--empty' : ''; ?>" style="width:auto; max-width:100%; min-width:0; flex:1 1 auto; zoom:var(--upb-canvas-zoom, 1);">

<?php $renderer->renderGlobalPart($headerData); ?>

<?php if (is_array($sections)) : ?>
    <?php foreach ($sections as $index => $section) : ?>
        <?php do_action('uncanny_page_builder_before_section_render', $section, $postId); ?>
        <?php
            echo $renderer->renderSectionHtml($section['content']['html'] ?? '', $section['id'] ?? null);
        ?>
    <?php endforeach; ?>
<?php endif; ?>

<?php if ($showEditorChrome && !$hasCanvasSections) : ?>
    <?php require __DIR__ . '/empty-state.php'; ?>
<?php endif; ?>

<?php $renderer->renderGlobalPart($footerData); ?>

</div><!-- #uncanny-pb-canvas -->
<?php if ($showThemeShellPlaceholders) : ?>
    <?php
    $themeShellPlaceholderLabel = _x("Your website's footer renders here.", 'Page Builder', 'uncanny-automator');
    require __DIR__ . '/theme-shell-placeholder.php';
    ?>
<?php endif; ?>

<!-- Modal and panel mount. -->
<div id="uncanny-pb-modal-root">
    <div id="uncanny-pb-modal"></div>
</div>
</div><!-- #uncanny-pb-canvas-root -->
