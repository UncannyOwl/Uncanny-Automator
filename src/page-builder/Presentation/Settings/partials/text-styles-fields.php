<?php
/**
 * Shared text-styles field markup.
 *
 * Rendered by the sitewide Typography settings page (inside its own <form>)
 * and by the page-level "Page style overrides" metabox (inside the post edit
 * form). The including template provides the context vars below; everything
 * falls back to the global-form defaults.
 *
 * @var array<string, array<string, string>> $typographyRoles    Current values (sparse in inherit mode)
 * @var array<string, array<string, string>> $typographyDefaults Reset targets (global) / site-effective values (inherit mode)
 * @var array<int, array{
 *     key: string,
 *     label: string,
 *     value: string,
 *     default: string,
 *     control: string,
 *     isColor: bool,
 *     options?: array<int, array{value: string, label: string}>
 * }> $linkFields
 * @var array<int, array{key: string, label: string, description: string, preview: string, fields: array<int, array{key: string, label: string, control: string}>}> $roleDefinitions
 * @var array<int, array{key: string, label: string, options: array<int, array{label: string, value: string, source: string}>}> $fontFamilyCatalog
 *
 * Optional context:
 * @var string   $typographyNamePattern sprintf pattern for typography field names
 * @var string   $tokenNamePattern      sprintf pattern for token field names (links)
 * @var bool     $inheritMode           true on page scope: empty = inherit site value
 * @var string[] $lockedTypographyKeys  locked "role.field" entries (rendered disabled)
 * @var string[] $lockedTokenKeys       locked token keys (rendered disabled)
 * @var string   $resetButtonLabel      per-card reset button label
 */

defined('ABSPATH') || exit;

$typographyNamePattern = $typographyNamePattern ?? 'ds_typography[roles][%1$s][%2$s]';
$tokenNamePattern = $tokenNamePattern ?? 'ds_tokens[%s]';
$inheritMode = $inheritMode ?? false;
$lockedTypographyKeys = $lockedTypographyKeys ?? [];
$lockedTokenKeys = $lockedTokenKeys ?? [];
$resetButtonLabel = $resetButtonLabel ?? _x('Restore Page Builder default', 'Page Builder', 'uncanny-automator');

$customFontValue = '__custom__';
$defaultDisplayValue = _x('Use default', 'Page Builder', 'uncanny-automator');
$siteDefaultLabel = _x('Site default', 'Page Builder', 'uncanny-automator');
$lockedNote = _x('Locked by site settings.', 'Page Builder', 'uncanny-automator');

$roleDefinitionsByKey = [];
foreach ($roleDefinitions as $roleDefinition) {
    $roleDefinitionsByKey[$roleDefinition['key']] = $roleDefinition;
}

$typographyGroups = [
    [
        'key' => 'headings',
        'label' => _x('Headings', 'Page Builder', 'uncanny-automator'),
        'description' => _x('Titles and section headings, from H1 to H6.', 'Page Builder', 'uncanny-automator'),
        'roles' => ['headings', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6'],
    ],
    [
        'key' => 'body',
        'label' => _x('Body text', 'Page Builder', 'uncanny-automator'),
        'description' => _x('Paragraphs, quotes, and other main page text.', 'Page Builder', 'uncanny-automator'),
        'roles' => ['body', 'paragraph', 'blockquote'],
    ],
    [
        'key' => 'navigation',
        'label' => _x('Navigation & buttons', 'Page Builder', 'uncanny-automator'),
        'description' => _x('Menus, links, tabs, and button labels.', 'Page Builder', 'uncanny-automator'),
        'roles' => ['navigation', 'buttons'],
    ],
    [
        'key' => 'small-text',
        'label' => _x('Small text', 'Page Builder', 'uncanny-automator'),
        'description' => _x('Captions, labels, helper text, metadata, and code.', 'Page Builder', 'uncanny-automator'),
        'roles' => ['caption', 'code'],
    ],
];

$primaryTypographyGroups = array_values(array_filter(
    $typographyGroups,
    static fn(array $group): bool => $group['key'] !== 'navigation',
));

$navigationTypographyGroup = null;
foreach ($typographyGroups as $group) {
    if ($group['key'] !== 'navigation') {
        continue;
    }

    $navigationTypographyGroup = $group;
    break;
}

$roleDisplay = [
    'headings' => [
        'label' => _x('All headings', 'Page Builder', 'uncanny-automator'),
        'description' => _x('Default font, weight, and line spacing for headings.', 'Page Builder', 'uncanny-automator'),
        'preview' => _x('Heading preview', 'Page Builder', 'uncanny-automator'),
    ],
    'h1' => [
        'label' => 'H1',
        'description' => _x('Main page title', 'Page Builder', 'uncanny-automator'),
        'preview' => _x('Main page headline', 'Page Builder', 'uncanny-automator'),
    ],
    'h2' => [
        'label' => 'H2',
        'description' => _x('Section heading', 'Page Builder', 'uncanny-automator'),
        'preview' => _x('Section heading', 'Page Builder', 'uncanny-automator'),
    ],
    'h3' => [
        'label' => 'H3',
        'description' => _x('Subsection heading', 'Page Builder', 'uncanny-automator'),
        'preview' => _x('Subsection heading', 'Page Builder', 'uncanny-automator'),
    ],
    'h4' => [
        'label' => 'H4',
        'description' => _x('Smaller heading', 'Page Builder', 'uncanny-automator'),
        'preview' => _x('Smaller heading', 'Page Builder', 'uncanny-automator'),
    ],
    'h5' => [
        'label' => 'H5',
        'description' => _x('Small heading', 'Page Builder', 'uncanny-automator'),
        'preview' => _x('Small heading', 'Page Builder', 'uncanny-automator'),
    ],
    'h6' => [
        'label' => 'H6',
        'description' => _x('Extra-small heading', 'Page Builder', 'uncanny-automator'),
        'preview' => _x('Extra-small heading', 'Page Builder', 'uncanny-automator'),
    ],
    'body' => [
        'label' => _x('Default text', 'Page Builder', 'uncanny-automator'),
        'description' => _x('The main text style for your pages.', 'Page Builder', 'uncanny-automator'),
        'preview' => _x('Build pages that are easy to read from the first line.', 'Page Builder', 'uncanny-automator'),
    ],
    'paragraph' => [
        'label' => _x('Paragraphs', 'Page Builder', 'uncanny-automator'),
        'description' => _x('Standard paragraph text.', 'Page Builder', 'uncanny-automator'),
        'preview' => _x('This is how regular paragraph text will appear.', 'Page Builder', 'uncanny-automator'),
    ],
    'blockquote' => [
        'label' => _x('Quotes', 'Page Builder', 'uncanny-automator'),
        'description' => _x('Quoted text.', 'Page Builder', 'uncanny-automator'),
        'preview' => _x('Strong typography makes the layout feel intentional.', 'Page Builder', 'uncanny-automator'),
    ],
    'navigation' => [
        'label' => _x('Navigation', 'Page Builder', 'uncanny-automator'),
        'description' => _x('Menu and navigation text.', 'Page Builder', 'uncanny-automator'),
        'preview' => _x('Home About Contact', 'Page Builder', 'uncanny-automator'),
    ],
    'buttons' => [
        'label' => _x('Buttons', 'Page Builder', 'uncanny-automator'),
        'description' => _x('Button labels and calls to action.', 'Page Builder', 'uncanny-automator'),
        'preview' => _x('Get started', 'Page Builder', 'uncanny-automator'),
    ],
    'caption' => [
        'label' => _x('Captions and labels', 'Page Builder', 'uncanny-automator'),
        'description' => _x('Helper text, labels, captions, and metadata.', 'Page Builder', 'uncanny-automator'),
        'preview' => _x('Helpful details appear here.', 'Page Builder', 'uncanny-automator'),
    ],
    'code' => [
        'label' => _x('Code', 'Page Builder', 'uncanny-automator'),
        'description' => _x('Code and keyboard-style text.', 'Page Builder', 'uncanny-automator'),
        'preview' => 'const example = "Code preview";',
    ],
];

$fieldLabelMap = [
    'font_family' => _x('Font', 'Page Builder', 'uncanny-automator'),
    'font_size' => _x('Size', 'Page Builder', 'uncanny-automator'),
    'font_weight' => _x('Weight', 'Page Builder', 'uncanny-automator'),
    'line_height' => _x('Line spacing', 'Page Builder', 'uncanny-automator'),
    'letter_spacing' => _x('Letter spacing', 'Page Builder', 'uncanny-automator'),
    'text_transform' => _x('Capitalization', 'Page Builder', 'uncanny-automator'),
    'font_style' => _x('Style', 'Page Builder', 'uncanny-automator'),
];

$knownFontValues = [];
foreach ($fontFamilyCatalog as $fontGroup) {
    foreach ($fontGroup['options'] as $option) {
        $knownFontValues[] = (string) $option['value'];
    }
}

/*
 * Automator's components only exist where its admin bundle loads. The settings
 * page has it; the page edit metabox does not, where a component would render
 * as inert text with no button role and no tab stop. So the reset control is
 * chosen by surface, and the fields around it stay shared.
 *
 * @var bool $useAutomatorComponents Set by the settings page.
 */
$useComponents = !empty($useAutomatorComponents);

/*
 * The card title names the card. On the settings page the component class
 * sizes it below the panel title above it. On the metabox that class reaches
 * nothing, and a heading element there both reads bold and names the card in
 * the outline. An element carrying the class on the settings page would take
 * the heading size instead, which matches the panel title and flattens the
 * order between them.
 */
$renderCardHeading = static function (string $label) use ($useComponents): void {
    if ($useComponents) {
        ?>
        <div class="uap-settings-panel-content-subtitle"><?php echo esc_html($label); ?></div>
        <?php
        return;
    }
    ?>
    <h4><?php echo esc_html($label); ?></h4>
    <?php
};

$renderResetControl = static function (string $marker, string $label) use ($useComponents): void {
    if ($useComponents) {
        ?>
        <?php
        /*
         * The same colour and size the Automator settings page gives the button
         * beside its licence field, which fills black on hover.
         */
        ?>
        <uo-button
            size="small"
            color="secondary"
            type="button"
            class="uap-spacing-top--small"
            <?php echo esc_attr($marker); ?>="true"
        ><?php echo esc_html($label); ?></uo-button>
        <?php
        return;
    }
    ?>
    <button
        type="button"
        class="button button-small"
        <?php echo esc_attr($marker); ?>="true"
    ><?php echo esc_html($label); ?></button>
    <?php
};

$renderRoleCard = function (array $role) use (
    $useComponents,
    $renderCardHeading,
    $renderResetControl,
    $roleDisplay,
    $typographyRoles,
    $typographyDefaults,
    $fieldLabelMap,
    $fontFamilyCatalog,
    $knownFontValues,
    $typographyNamePattern,
    $inheritMode,
    $lockedTypographyKeys,
    $resetButtonLabel,
    $customFontValue,
    $defaultDisplayValue,
    $siteDefaultLabel,
    $lockedNote
): void {
    $display = $roleDisplay[$role['key']] ?? [];
    $roleValues = $typographyRoles[$role['key']] ?? [];
    $roleDefaults = $typographyDefaults[$role['key']] ?? [];
    ?>
    <div
        class="upb-typography-role-card"
        data-upb-typography-role="true"
        data-role-key="<?php echo esc_attr($role['key']); ?>"
    >
        <div class="uap-settings-panel-content-separator"></div>

        <?php
        /*
         * The header is a row of two: the naming on one side, the action on the
         * other. Keeping the title and its description in one child is what lets
         * the metabox place the action opposite them instead of spreading all
         * three across the row.
         */
        ?>
        <div class="upb-typography-role-card__header">
            <div>
                <?php $renderCardHeading((string) ($display['label'] ?? $role['label'])); ?>
                <p class="uap-settings-panel-content-paragraph uap-settings-panel-content-paragraph--subtle">
                    <?php echo esc_html((string) ($display['description'] ?? $role['description'])); ?>
                </p>
            </div>

            <?php $renderResetControl('data-upb-typography-reset', $resetButtonLabel); ?>
        </div>

        <div class="upb-typography-role-card__preview uap-spacing-top">
            <div><strong><?php echo esc_html_x('Preview', 'Page Builder', 'uncanny-automator'); ?></strong></div>
            <div
                class="upb-typography-role-card__preview-text uap-spacing-top--small"
                data-upb-typography-preview="true"
                style="
                    <?php
                    $previewStyles = [];
                    $previewHasLineHeight = false;
                    foreach (['font_family' => 'font-family', 'font_size' => 'font-size', 'font_weight' => 'font-weight', 'line_height' => 'line-height', 'font_style' => 'font-style', 'letter_spacing' => 'letter-spacing', 'text_transform' => 'text-transform'] as $fieldKey => $cssProperty) {
                        $fieldValue = trim((string) ($roleValues[$fieldKey] ?? ''));
                        if ($fieldValue === '' && $inheritMode) {
                            $fieldValue = trim((string) ($roleDefaults[$fieldKey] ?? ''));
                        }
                        if ($fieldValue === '') {
                            continue;
                        }

                        if ($fieldValue === 'inherit') {
                            $fieldValue = 'inherit';
                        }

                        $previewStyles[] = $cssProperty . ':' . $fieldValue;

                        if ('line-height' === $cssProperty) {
                            $previewHasLineHeight = true;
                        }
                    }

                    /*
                     * The settings panel sets a fixed line height in pixels, which
                     * the preview would otherwise inherit and keep whatever the
                     * font size, clipping the sample against the rows below. Fall
                     * back to normal so the box tracks the font size when the role
                     * itself does not set a line height.
                     */
                    if (empty($previewHasLineHeight)) {
                        $previewStyles[] = 'line-height:normal';
                    }

                    echo esc_attr(implode(';', $previewStyles));
                    ?>
                "
            >
                <?php echo esc_html((string) ($display['preview'] ?? $role['preview'])); ?>
            </div>
        </div>

        <div class="upb-typography-role-card__fields">
            <?php
            /*
             * Controls sit two per row in Automator's flex field container, so a
             * role reads as font and size, then weight and line spacing. Each
             * cell carries uap-field to share the row evenly.
             */
            $pairedFieldIndex = 0;
            $pendingCustomFontField = null;

            /**
             * Renders the custom font list at the full card width.
             *
             * The font control holds its values while it renders inside a cell,
             * and this runs once the row closes.
             *
             * @param array{value: string, default: string, locked: bool, visible: bool} $field
             */
            $renderCustomFontField = static function (array $field): void {
                ?>
                <div
                    class="upb-typography-field__custom-value uap-spacing-top"
                    data-upb-typography-font-family-custom-wrap="true"
                    <?php if (!$field['visible']): ?>
                        hidden
                    <?php endif; ?>
                >
                    <div><label><strong><?php echo esc_html_x('Custom font list', 'Page Builder', 'uncanny-automator'); ?></strong></label></div>
                    <?php
                    /*
                     * A font stack is longer than the field is wide, so a single
                     * line input hid most of it behind a horizontal scroll. A
                     * textarea wraps the stack over several lines while the value
                     * stays the comma separated list CSS expects. The script only
                     * reads value and listens for input, which behave the same
                     * here.
                     */
                    ?>
                    <textarea
                        class="uap-field-text uap-spacing-top--small"
                        rows="3"
                        data-upb-typography-font-family-custom="true"
                        data-default-value="<?php echo esc_attr($field['default']); ?>"
                        <?php echo $field['locked'] ? 'disabled' : ''; ?>
                    ><?php echo esc_textarea($field['value']); ?></textarea>
                </div>
                <?php
            };
            ?>
            <?php foreach ($role['fields'] as $field): ?>
                <?php if ($pairedFieldIndex % 2 === 0): ?>
                    <div class="uap-field-container uap-spacing-top">
                <?php endif; ?>
                <?php
                $fieldName = sprintf($typographyNamePattern, $role['key'], $field['key']);
                $fieldValue = (string) ($roleValues[$field['key']] ?? '');
                $defaultValue = (string) ($roleDefaults[$field['key']] ?? '');
                $displayValue = $fieldValue === 'inherit' ? $defaultDisplayValue : $fieldValue;
                $displayDefaultValue = $defaultValue === 'inherit' ? $defaultDisplayValue : $defaultValue;
                $fieldLabel = (string) ($fieldLabelMap[$field['key']] ?? $field['label']);
                $isLocked = in_array($role['key'] . '.' . $field['key'], $lockedTypographyKeys, true);
                // Inherit mode: reset clears back to '' (inherit); the site
                // value only informs placeholders/previews, never the input.
                $resetValue = $inheritMode ? '' : $defaultValue;
                $resetDisplayValue = $inheritMode ? '' : $displayDefaultValue;
                $placeholderValue = '';
                if ($inheritMode) {
                    $placeholderValue = ($defaultValue !== '' && $defaultValue !== 'inherit')
                        ? $defaultValue
                        : $siteDefaultLabel;
                }
                ?>
                <?php if ($field['control'] === 'font_family'): ?>
                    <?php $usesCustomValue = $fieldValue !== '' && !in_array($fieldValue, $knownFontValues, true); ?>
                    <div class="upb-typography-field uap-field<?php echo $pairedFieldIndex % 2 === 0 ? ' uap-spacing-right' : ''; ?>">
                        <div><label><strong><?php echo esc_html($fieldLabel); ?></strong></label></div>
                        <input
                            type="hidden"
                            name="<?php echo esc_attr($fieldName); ?>"
                            value="<?php echo esc_attr($fieldValue); ?>"
                            data-upb-typography-font-family-value="true"
                            data-default-value="<?php echo esc_attr($resetValue); ?>"
                            <?php echo $isLocked ? 'disabled' : ''; ?>
                        />
                        <select
                            class="uap-field-text uap-spacing-top--small"
                            data-upb-typography-font-family-select="true"
                            data-default-value="<?php echo esc_attr($resetValue); ?>"
                            <?php echo $isLocked ? 'disabled' : ''; ?>
                        >
                            <?php if ($inheritMode): ?>
                                <option value="" <?php echo $fieldValue === '' ? 'selected' : ''; ?>>
                                    <?php echo esc_html(($defaultValue !== '' && $defaultValue !== 'inherit') ? sprintf(
                                        /* translators: %s: Default typography value defined in site settings. */
                                        _x('Site default — %s', 'Page Builder', 'uncanny-automator'),
                                        $defaultValue
                                    ) : $siteDefaultLabel); ?>
                                </option>
                            <?php endif; ?>
                            <?php foreach ($fontFamilyCatalog as $fontGroup): ?>
                                <optgroup label="<?php echo esc_attr($fontGroup['label']); ?>">
                                    <?php foreach ($fontGroup['options'] as $option): ?>
                                        <option
                                            value="<?php echo esc_attr($option['value']); ?>"
                                            <?php echo !$usesCustomValue && $fieldValue !== '' && $fieldValue === (string) $option['value'] ? 'selected' : ''; ?>
                                        >
                                            <?php echo esc_html((string) ($option['value'] === 'inherit' ? $defaultDisplayValue : $option['label'])); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endforeach; ?>
                            <option value="<?php echo esc_attr($customFontValue); ?>" <?php echo $usesCustomValue ? 'selected' : ''; ?>>
                                <?php echo esc_html($usesCustomValue ? sprintf(
                                    /* translators: %s: Custom font-family string entered by the user. */
                                    _x('Custom font list: %s', 'Page Builder', 'uncanny-automator'),
                                    $fieldValue
                                ) : _x('Enter custom font list', 'Page Builder', 'uncanny-automator')); ?>
                            </option>
                        </select>
                        <?php
                        /*
                         * The field belongs with the font control that reveals it.
                         * Where the row places its cells side by side, a cell is
                         * too narrow for a font stack, so the field waits for the
                         * row to close and then spans the card. Where the cells
                         * stack, the field follows the font control directly, and
                         * waiting would place it below the next control instead.
                         */
                        $customFontField = [
                            'value' => $fieldValue,
                            'default' => $resetValue,
                            'locked' => $isLocked,
                            'visible' => $usesCustomValue,
                        ];

                        if ($useComponents) {
                            $pendingCustomFontField = $customFontField;
                        } else {
                            $renderCustomFontField($customFontField);
                        }
                        ?>
                        <?php if ($isLocked): ?>
                            <p class="description"><?php echo esc_html($lockedNote); ?></p>
                        <?php elseif (!$usesCustomValue): ?>
                            <p class="description" data-upb-typography-font-family-help="true">
                                <?php echo esc_html_x('Choose a saved font or enter a custom font list.', 'Page Builder', 'uncanny-automator'); ?>
                            </p>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <?php /* uap-field is width:100%, so paired controls share the row evenly. */ ?>
                    <div class="upb-typography-field uap-field<?php echo $pairedFieldIndex % 2 === 0 ? ' uap-spacing-right' : ''; ?>">
                        <div>
                            <label for="<?php echo esc_attr('upb-typography-' . $role['key'] . '-' . $field['key']); ?>">
                                <strong><?php echo esc_html($fieldLabel); ?></strong>
                            </label>
                        </div>
                        <input
                            id="<?php echo esc_attr('upb-typography-' . $role['key'] . '-' . $field['key']); ?>"
                            type="text"
                            class="uap-field-text uap-spacing-top--small"
                            name="<?php echo esc_attr($fieldName); ?>"
                            value="<?php echo esc_attr($displayValue); ?>"
                            <?php if ($placeholderValue !== ''): ?>
                                placeholder="<?php echo esc_attr($placeholderValue); ?>"
                            <?php endif; ?>
                            data-upb-typography-input="true"
                            data-default-value="<?php echo esc_attr($resetValue); ?>"
                            data-default-display-value="<?php echo esc_attr($resetDisplayValue); ?>"
                            data-preview-property="<?php echo esc_attr(str_replace('_', '-', $field['key'])); ?>"
                            <?php echo $isLocked ? 'disabled' : ''; ?>
                        />
                        <?php if ($isLocked): ?>
                            <p class="description"><?php echo esc_html($lockedNote); ?></p>
                        <?php elseif ($field['key'] === 'font_weight'): ?>
                            <p class="description">
                                <?php echo esc_html_x('400 is regular, 600 is semibold, and 700 is bold.', 'Page Builder', 'uncanny-automator'); ?>
                            </p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php $pairedFieldIndex++; ?>
                <?php if ($pairedFieldIndex % 2 === 0): ?>
                    </div>
                    <?php if (null !== $pendingCustomFontField): ?>
                        <?php
                        $renderCustomFontField($pendingCustomFontField);
                        $pendingCustomFontField = null;
                        ?>
                    <?php endif; ?>
                <?php endif; ?>
            <?php endforeach; ?>

            <?php if ($pairedFieldIndex % 2 === 1): ?>
                <?php
                /*
                 * Odd number of controls, so the trailing row is still open. The
                 * empty cell keeps the lone control at the same width as a paired
                 * one instead of letting it stretch across the card.
                 */
                ?>
                    <div class="uap-field"></div>
                </div>
            <?php endif; ?>

            <?php if (null !== $pendingCustomFontField): ?>
                <?php /* A role whose font control landed in a trailing row still owes it. */ ?>
                <?php $renderCustomFontField($pendingCustomFontField); ?>
            <?php endif; ?>
        </div>
    </div>
    <?php
};

/*
 * The settings page gives each group its own sidebar section and sets
 * $onlyTypographyGroup, so just that group renders with no wrapper of its own -
 * the panel title already names it. The page edit metabox includes this partial
 * without that variable and shows every group, each inside native <details>.
 *
 * @var string $onlyTypographyGroup Group key the settings page is rendering.
 */
$singleGroup = isset($onlyTypographyGroup) && '' !== $onlyTypographyGroup ? $onlyTypographyGroup : null;

$openRoleGroup = static function (string $label, string $description) use ($singleGroup): void {
    if (null !== $singleGroup) {
        return;
    }
    ?>
    <details class="upb-typography-role-group">
        <summary class="upb-typography-role-group__summary">
            <span class="upb-typography-role-group__summary-copy">
                <span class="upb-typography-role-group__title"><?php echo esc_html($label); ?></span>
                <span class="upb-typography-role-group__description"><?php echo esc_html($description); ?></span>
            </span>
        </summary>
    <?php
};

$closeRoleGroup = static function () use ($singleGroup): void {
    if (null !== $singleGroup) {
        return;
    }
    ?>
    </details>
    <?php
};

/** Whether a group renders in the current context. */
$showRoleGroup = static function (string $groupKey) use ($singleGroup): bool {
    return null === $singleGroup || $singleGroup === $groupKey;
};

?>
<div class="upb-typography-settings__section">
    <?php foreach ($primaryTypographyGroups as $group): ?>
        <?php if (!$showRoleGroup((string) $group['key'])): ?>
            <?php continue; ?>
        <?php endif; ?>
        <?php $openRoleGroup($group['label'], $group['description']); ?>
            <div class="upb-typography-settings__roles">
                <?php foreach ($group['roles'] as $roleKey): ?>
                    <?php if (!isset($roleDefinitionsByKey[$roleKey])): ?>
                        <?php continue; ?>
                    <?php endif; ?>
                    <?php $renderRoleCard($roleDefinitionsByKey[$roleKey]); ?>
                <?php endforeach; ?>
            </div>
        <?php $closeRoleGroup(); ?>
    <?php endforeach; ?>
</div>

<?php if (is_array($navigationTypographyGroup) && $showRoleGroup((string) $navigationTypographyGroup['key'])): ?>
    <div class="upb-typography-settings__section">
        <?php $openRoleGroup($navigationTypographyGroup['label'], $navigationTypographyGroup['description']); ?>
            <div class="upb-typography-settings__roles">
                <?php foreach ($navigationTypographyGroup['roles'] as $roleKey): ?>
                    <?php if (!isset($roleDefinitionsByKey[$roleKey])): ?>
                        <?php continue; ?>
                    <?php endif; ?>
                    <?php $renderRoleCard($roleDefinitionsByKey[$roleKey]); ?>
                <?php endforeach; ?>
            </div>
        <?php $closeRoleGroup(); ?>
    </div>
<?php endif; ?>

<?php if ($showRoleGroup('links')): ?>
<div class="upb-typography-settings__section">
    <?php
    $openRoleGroup(
        _x('Links', 'Page Builder', 'uncanny-automator'),
        _x('Link color, hover color, and underline style.', 'Page Builder', 'uncanny-automator')
    );
    ?>
        <div class="upb-typography-settings__roles">
            <div class="upb-typography-role-card">
                <div class="upb-typography-role-card__header">
                    <div>
                        <?php $renderCardHeading(_x('Links', 'Page Builder', 'uncanny-automator')); ?>
                        <p class="uap-settings-panel-content-paragraph uap-settings-panel-content-paragraph--subtle">
                            <?php echo esc_html_x('How links should look inside page content.', 'Page Builder', 'uncanny-automator'); ?>
                        </p>
                    </div>

                    <?php $renderResetControl('data-upb-link-reset', $resetButtonLabel); ?>
                </div>

                <div class="upb-typography-role-card__preview uap-spacing-top">
                    <div><strong><?php echo esc_html_x('Preview', 'Page Builder', 'uncanny-automator'); ?></strong></div>
                    <p class="uap-settings-panel-content-paragraph uap-settings-panel-content-paragraph--subtle uap-spacing-top--small uap-spacing-bottom--small">
                        <?php echo esc_html_x('Point at the sample to see the hover colour.', 'Page Builder', 'uncanny-automator'); ?>
                    </p>
                    <div class="upb-typography-role-card__preview-text">
                        <a
                            href="#"
                            data-upb-link-preview="true"
                            onclick="return false;"
                        ><?php echo esc_html_x('Read more', 'Page Builder', 'uncanny-automator'); ?></a>
                    </div>
                </div>

                <div class="upb-typography-role-card__fields">
                    <?php foreach ($linkFields as $field): ?>
                        <?php
                        $isLocked = in_array($field['key'], $lockedTokenKeys, true);
                        $resetValue = $inheritMode ? '' : $field['default'];
                        ?>
                        <div class="upb-typography-field uap-spacing-top">
                            <div>
                                <label for="<?php echo esc_attr('upb-link-' . md5($field['key'])); ?>">
                                    <strong><?php echo esc_html($field['label']); ?></strong>
                                </label>
                            </div>
                            <?php if ($field['control'] === 'select'): ?>
                                <input
                                    type="hidden"
                                    name="<?php echo esc_attr(sprintf($tokenNamePattern, $field['key'])); ?>"
                                    value="<?php echo esc_attr($field['value']); ?>"
                                    data-upb-link-value="<?php echo esc_attr($field['key']); ?>"
                                    <?php echo $isLocked ? 'disabled' : ''; ?>
                                />
                                <select
                                    class="uap-field-text uap-spacing-top--small"
                                    id="<?php echo esc_attr('upb-link-' . md5($field['key'])); ?>"
                                    data-upb-link-field="true"
                                    data-upb-link-select="<?php echo esc_attr($field['key']); ?>"
                                    data-link-property="textDecoration"
                                    data-default-value="<?php echo esc_attr($resetValue); ?>"
                                    <?php echo $isLocked ? 'disabled' : ''; ?>
                                >
                                    <?php if ($inheritMode): ?>
                                        <option value="" <?php echo $field['value'] === '' ? 'selected' : ''; ?>>
                                            <?php echo esc_html($field['default'] !== '' ? sprintf(
                                                /* translators: %s: Default link token value defined in site settings. */
                                                _x('Site default — %s', 'Page Builder', 'uncanny-automator'),
                                                $field['default']
                                            ) : $siteDefaultLabel); ?>
                                        </option>
                                    <?php endif; ?>
                                    <?php foreach (($field['options'] ?? []) as $option): ?>
                                        <option
                                            value="<?php echo esc_attr($option['value']); ?>"
                                            <?php echo $field['value'] !== '' && $field['value'] === $option['value'] ? 'selected' : ''; ?>
                                        >
                                            <?php echo esc_html($option['label']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            <?php else: ?>
                                <input
                                    id="<?php echo esc_attr('upb-link-' . md5($field['key'])); ?>"
                                    type="text"
                                    <?php /* Colour inputs are rewritten by wp-color-picker, so they keep their own control markup. */ ?>
                                    class="<?php echo $field['isColor'] ? 'regular-text upb-color-control' : 'uap-field-text uap-spacing-top--small'; ?>"
                                    name="<?php echo esc_attr(sprintf($tokenNamePattern, $field['key'])); ?>"
                                    value="<?php echo esc_attr($field['value']); ?>"
                                    data-upb-link-field="true"
                                    data-link-property="<?php echo esc_attr($field['key'] === '--bs-link-hover-color' ? 'hoverColor' : 'color'); ?>"
                                    data-default-value="<?php echo esc_attr($resetValue); ?>"
                                    <?php echo $isLocked ? 'disabled' : ''; ?>
                                />
                            <?php endif; ?>
                            <?php if ($isLocked): ?>
                                <p class="description"><?php echo esc_html($lockedNote); ?></p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php $closeRoleGroup(); ?>
</div>
<?php endif; ?>
