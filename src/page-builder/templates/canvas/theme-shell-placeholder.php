<?php
/**
 * Theme shell placeholder strip.
 *
 * Composition pages never render the theme's header/footer in the editor;
 * one strip above and one below the canvas say so and link the faithful
 * preview.
 *
 * Inputs: $themeShellPlaceholderLabel (set by the caller per strip),
 *         $themeShellPreviewUrl
 */

defined('ABSPATH') || exit;
?>
<div class="upb-theme-shell-placeholder" data-upb-editor-chrome="true">
    <span class="upb-theme-shell-placeholder__label"><?php echo esc_html($themeShellPlaceholderLabel); ?></span>
    <?php if ($themeShellPreviewUrl !== '') : ?>
        <a class="components-button is-tertiary is-compact upb-theme-shell-placeholder__preview" href="<?php echo esc_url($themeShellPreviewUrl); ?>" target="_blank" rel="noopener" data-upb-theme-shell-preview-link="true"><?php echo esc_html_x('Preview page', 'Page Builder', 'uncanny-automator'); ?></a>
    <?php endif; ?>
</div>
