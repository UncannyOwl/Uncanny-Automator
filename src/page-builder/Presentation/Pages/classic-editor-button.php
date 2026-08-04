<?php
/**
 * Classic Editor entry point for an existing WordPress page.
 *
 * This markup is rendered inside WordPress's existing post form. Submitting
 * through that form preserves pending title and content changes before the
 * redirect filter adopts the page.
 *
 * @var string $openField
 * @var string $nonceField
 */

defined('ABSPATH') || exit;

wp_nonce_field(
    \UncannyPageBuilder\Infrastructure\WordPress\BlockEditorButton::ACTION,
    $nonceField,
    false,
);
?>
<div class="upb-classic-editor-action" style="margin-top: 10px;">
    <button
        id="<?php echo esc_attr(\UncannyPageBuilder\Infrastructure\WordPress\BlockEditorButton::CLASSIC_BUTTON_ID); ?>"
        type="submit"
        class="button button-secondary"
        name="<?php echo esc_attr($openField); ?>"
        value="1"
    >
        <?php echo esc_html_x('Edit with Uncanny Page Builder', 'Page Builder', 'uncanny-automator'); ?>
    </button>
</div>
