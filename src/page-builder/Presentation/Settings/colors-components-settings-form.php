<?php
/**
 * Plain WordPress colors and components settings form.
 *
 * Thin wrapper around the shared color partials: this file owns the global
 * <form>, nonce, hidden advanced token fields, and submit button;
 * the field markup and behavior script are shared with the page-level
 * overrides metabox.
 *
 * @var bool $updated
 * @var string $error
 * @var string $warning
 * @var array<int, array{key: string, label: string, fields: array<int, array{key: string, label: string, value: string, default: string, isColor: bool}>}> $tokenGroups
 * @var array<int, array{key: string, label: string, value: string, default: string, isColor: bool}> $hiddenFields
 * @var array{name: string, value: string} $nonce
 */

defined('ABSPATH') || exit;

$containerId = 'uncanny-page-builder-colors-components-form';

?>
<form method="post" id="<?php echo esc_attr($containerId); ?>">
    <input
        type="hidden"
        name="<?php echo esc_attr($nonce['name']); ?>"
        value="<?php echo esc_attr($nonce['value']); ?>"
    />

    <?php foreach ($hiddenFields as $field): ?>
        <input
            type="hidden"
            name="<?php echo esc_attr(sprintf('ds_tokens[%s]', $field['key'])); ?>"
            value="<?php echo esc_attr($field['value']); ?>"
        />
    <?php endforeach; ?>

    <div class="uap-settings-panel upb-typography-settings">
        <div class="uap-settings-panel-top">
            <div class="uap-settings-panel-title">
                <?php echo esc_html_x('Colors', 'Page Builder', 'uncanny-automator'); ?>
            </div>
            <div class="uap-settings-panel-content">
                <?php if ($updated): ?>
                    <uo-alert type="success">
                        <?php echo esc_html_x('Color settings saved.', 'Page Builder', 'uncanny-automator'); ?>
                    </uo-alert>
                <?php endif; ?>

                <?php if ($error !== ''): ?>
                    <uo-alert type="error"><?php echo esc_html($error); ?></uo-alert>
                <?php endif; ?>

                <?php if ($warning !== ''): ?>
                    <uo-alert type="warning"><?php echo esc_html($warning); ?></uo-alert>
                <?php endif; ?>

                <p class="uap-settings-panel-content-paragraph uap-settings-panel-content-paragraph--subtle">
                    <?php echo esc_html_x('Set the brand colors Uncanny Agent can use when designing pages.', 'Page Builder', 'uncanny-automator'); ?>
                </p>

                <?php include __DIR__ . '/partials/color-fields.php'; ?>
            </div>
        </div>

        <div class="uap-settings-panel-bottom">
            <div class="uap-settings-panel-bottom-left">
                <uo-button type="submit" class="uap-spacing-right--small">
                    <?php echo esc_html_x('Save colors', 'Page Builder', 'uncanny-automator'); ?>
                </uo-button>
                <uo-button
                    type="button"
                    color="secondary"
                    id="upb-colors-restore-default"
                    data-upb-colors-reset="true"
                >
                    <?php echo esc_html_x('Restore default', 'Page Builder', 'uncanny-automator'); ?>
                </uo-button>
            </div>
        </div>
    </div>
</form>

<?php include __DIR__ . '/partials/color-script.php'; ?>
