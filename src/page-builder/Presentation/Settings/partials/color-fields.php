<?php
/**
 * Shared color token field markup.
 *
 * Rendered by the global Colors page (inside its own <form>) and by the
 * page-level "Page style overrides" metabox (inside the post edit form).
 *
 * @var array<int, array{key: string, label: string, fields: array<int, array{key: string, label: string, value: string, default: string, isColor: bool}>}> $tokenGroups
 *
 * Optional context:
 * @var string   $tokenNamePattern sprintf pattern for token field names
 * @var bool     $inheritMode      true on page scope: empty = inherit site value
 * @var string[] $lockedTokenKeys  locked token keys (rendered disabled)
 */

defined('ABSPATH') || exit;

$tokenNamePattern = $tokenNamePattern ?? 'ds_tokens[%s]';
$inheritMode = $inheritMode ?? false;
$lockedTokenKeys = $lockedTokenKeys ?? [];

$colorFields = [];
foreach ($tokenGroups as $group) {
    foreach ($group['fields'] as $field) {
        $colorFields[] = $field;
    }
}

?>
<div class="upb-typography-settings__section">
    <div class="upb-token-group__fields">
        <?php foreach ($colorFields as $field): ?>
            <?php
            $isLocked = in_array($field['key'], $lockedTokenKeys, true);
            // Inherit mode: reset clears back to '' (inherit); the site value
            // only informs the swatch and placeholder, never the input.
            $resetValue = $inheritMode ? '' : $field['default'];
            $swatchColor = $field['value'] !== '' ? $field['value'] : ($inheritMode ? $field['default'] : $field['value']);
            ?>
            <div class="upb-token-field">
                <label for="<?php echo esc_attr('upb-token-' . md5($field['key'])); ?>">
                    <?php echo esc_html($field['label']); ?>
                </label>
                <div class="upb-token-field__control">
                    <?php if ($field['isColor']): ?>
                        <span
                            class="upb-token-field__swatch"
                            style="background-color: <?php echo esc_attr($swatchColor); ?>;"
                        ></span>
                    <?php endif; ?>
                    <input
                        id="<?php echo esc_attr('upb-token-' . md5($field['key'])); ?>"
                        type="text"
                        class="regular-text <?php echo $field['isColor'] ? 'upb-color-control' : ''; ?>"
                        name="<?php echo esc_attr(sprintf($tokenNamePattern, $field['key'])); ?>"
                        value="<?php echo esc_attr($field['value']); ?>"
                        <?php if ($inheritMode && $field['default'] !== ''): ?>
                            placeholder="<?php echo esc_attr($field['default']); ?>"
                        <?php endif; ?>
                        data-default-value="<?php echo esc_attr($resetValue); ?>"
                        <?php echo $isLocked ? 'disabled' : ''; ?>
                    />
                </div>
                <?php if ($isLocked): ?>
                    <p class="description"><?php echo esc_html_x('Locked by site settings.', 'Page Builder', 'uncanny-automator'); ?></p>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>
