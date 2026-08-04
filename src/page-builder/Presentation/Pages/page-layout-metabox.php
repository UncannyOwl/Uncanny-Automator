<?php
/**
 * Page layout meta box — shell mode select.
 *
 * Posts through the page's Update button; the nonce and field name are the
 * contract EditorEnvironmentProvider::handleSave() reads.
 *
 * @var \WP_Post $post
 * @var \UncannyPageBuilder\Domain\Shell\ShellModeContext $shellCtx
 * @var bool $layoutReadOnly
 * @var int $workingGeneration
 */

use UncannyPageBuilder\Domain\Shell\ShellMode;
use UncannyPageBuilder\Infrastructure\WordPress\PageEditorMetaBoxes;

defined('ABSPATH') || exit;

$shellModeOptions = [
    [
        'value' => ShellMode::None->value,
        'label' => _x('Choose how this page should work', 'Page Builder', 'uncanny-automator'),
    ],
    [
        'value' => ShellMode::UncannyNative->value,
        'label' => _x('Build the full page with Uncanny Page Builder', 'Page Builder', 'uncanny-automator'),
    ],
    [
        'value' => ShellMode::ThemeComposition->value,
        'label' => _x("Use my website's header and footer", 'Page Builder', 'uncanny-automator'),
    ],
];
$shellModeHelpByValue = [
    ShellMode::None->value => _x('Choose how this page should fit into your website, then update the page.', 'Page Builder', 'uncanny-automator'),
    ShellMode::UncannyNative->value => _x('Uncanny Page Builder controls the full page, including the saved header and footer from your Page layout settings.', 'Page Builder', 'uncanny-automator'),
    ShellMode::ThemeComposition->value => _x('Your theme keeps the page wrapper, header, and footer. Uncanny Page Builder adds the page content inside it.', 'Page Builder', 'uncanny-automator'),
];
$currentMode = $shellCtx->mode->value;
$layoutReadOnly = isset($layoutReadOnly) && $layoutReadOnly;

?>
<input
    type="hidden"
    name="<?php echo esc_attr(PageEditorMetaBoxes::SOURCE_GENERATION_FIELD); ?>"
    value="<?php echo esc_attr((string) $workingGeneration); ?>"
/>
<?php if ($layoutReadOnly): ?>
    <p class="notice notice-warning inline" style="padding: 8px 10px;">
        <?php echo esc_html_x(
            'A newer saved draft is parked. Open the Page Builder editor and load that draft before changing page layout.',
            'Page Builder',
            'uncanny-automator',
        ); ?>
    </p>
<?php endif; ?>
<input
    type="hidden"
    name="<?php echo esc_attr(PageEditorMetaBoxes::SHELL_MODE_NONCE_KEY); ?>"
    value="<?php echo esc_attr(wp_create_nonce(PageEditorMetaBoxes::SHELL_MODE_NONCE_ACTION)); ?>"
/>

<p>
    <label for="upb-shell-mode-select"><strong><?php echo esc_html_x('How this page should render', 'Page Builder', 'uncanny-automator'); ?></strong></label>
</p>
<select
    id="upb-shell-mode-select"
    name="<?php echo esc_attr(PageEditorMetaBoxes::SHELL_MODE_FIELD); ?>"
    style="width: 100%;"
    <?php disabled($layoutReadOnly); ?>
>
    <?php foreach ($shellModeOptions as $option): ?>
        <option
            value="<?php echo esc_attr($option['value']); ?>"
            <?php selected($currentMode, $option['value']); ?>
        >
            <?php echo esc_html($option['label']); ?>
        </option>
    <?php endforeach; ?>
</select>

<?php foreach ($shellModeHelpByValue as $value => $help): ?>
    <p
        class="description"
        data-upb-shell-mode-help="<?php echo esc_attr($value); ?>"
        <?php if ($value !== $currentMode): ?>
            hidden
        <?php endif; ?>
    >
        <?php echo esc_html($help); ?>
    </p>
<?php endforeach; ?>

<?php if (!$layoutReadOnly): ?>
    <p class="description"><?php echo esc_html_x('Layout changes save when you update this page.', 'Page Builder', 'uncanny-automator'); ?></p>
<?php endif; ?>

<script>
(function () {
    var select = document.getElementById('upb-shell-mode-select');
    if (!select) {
        return;
    }

    select.addEventListener('change', function () {
        document.querySelectorAll('[data-upb-shell-mode-help]').forEach(function (help) {
            help.hidden = help.getAttribute('data-upb-shell-mode-help') !== select.value;
        });
    });
})();
</script>
