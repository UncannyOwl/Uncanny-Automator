<?php
/**
 * Page-scoped Colors meta box template.
 *
 * Renders the same color partials as the global Brand styles page, scoped to
 * one page: values are the page's sparse overrides, the swatch and
 * placeholder show the site values the page inherits, and clearing a field
 * returns it to "inherit". Lives inside the post edit form, so it renders no
 * <form> of its own and saves through the page's Update button.
 *
 * @var \WP_Post $post
 * @var string   $nonceKey
 * @var string   $nonceValue
 * @var array<int, array{key: string, label: string, fields: array<int, array{key: string, label: string, value: string, default: string, isColor: bool}>}> $tokenGroups
 * @var string[] $lockedTokenKeys
 */

defined('ABSPATH') || exit;

$containerId = 'upb-page-colors';
$inheritMode = true;
$tokenNamePattern = 'upb_ds_token[%s]';
$resetConfirmMessage = _x('Clear the color overrides on this page and use the site values?', 'Page Builder', 'uncanny-automator');

?>
<div id="<?php echo esc_attr($containerId); ?>" class="upb-settings-panel upb-typography-settings upb-page-style-overrides">
    <input
        type="hidden"
        name="<?php echo esc_attr($nonceKey); ?>"
        value="<?php echo esc_attr($nonceValue); ?>"
    />

    <p class="upb-settings-description">
        <?php echo esc_html_x('Only change these if this page needs different colors from your site defaults. Empty fields use your site brand styles.', 'Page Builder', 'uncanny-automator'); ?>
    </p>

    <?php include __DIR__ . '/../Settings/partials/color-fields.php'; ?>

    <p class="upb-page-style-overrides__footer">
        <button type="button" class="button" data-upb-colors-reset="true">
            <?php echo esc_html_x('Use site colors', 'Page Builder', 'uncanny-automator'); ?>
        </button>
    </p>

    <?php include __DIR__ . '/../Settings/partials/color-script.php'; ?>
</div>
