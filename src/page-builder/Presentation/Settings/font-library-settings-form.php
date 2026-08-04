<?php
/**
 * Plain WordPress font library settings form.
 *
 * Fields are stacked full width and separated per row, matching how the
 * Automator settings panels lay out their own fields. <uo-text-field> posts
 * through a hidden field it names after its own id, so the id carries the POST
 * key and the repeater reindexes ids rather than input names.
 *
 * @var bool $updated
 * @var array<int, array{family: string, weights: string}> $googleFonts
 * @var array<int, array{family: string, attachment_id: int, weight: string, file_name: string}> $customFonts
 * @var array<int, array{value: string, label: string}> $weightOptions
 * @var array{name: string, value: string} $nonce
 */

defined('ABSPATH') || exit;

?>
<form method="post" id="uncanny-page-builder-font-library-form">
    <input
        type="hidden"
        name="<?php echo esc_attr($nonce['name']); ?>"
        value="<?php echo esc_attr($nonce['value']); ?>"
    />

    <div class="uap-settings-panel">
        <div class="uap-settings-panel-top">
            <div class="uap-settings-panel-title">
                <?php echo esc_html_x('Fonts', 'Page Builder', 'uncanny-automator'); ?>
            </div>
            <div class="uap-settings-panel-content">
                <?php if ($updated): ?>
                    <uo-alert type="success">
                        <?php echo esc_html_x('Font settings saved.', 'Page Builder', 'uncanny-automator'); ?>
                    </uo-alert>
                <?php endif; ?>

                <p class="uap-settings-panel-content-paragraph uap-settings-panel-content-paragraph--subtle">
                    <?php echo esc_html_x('Add fonts Uncanny Agent can use when designing your pages.', 'Page Builder', 'uncanny-automator'); ?>
                </p>

                <div class="uap-settings-panel-content-separator"></div>

                <div class="uap-settings-panel-content-subtitle">
                    <?php echo esc_html_x('Google Fonts', 'Page Builder', 'uncanny-automator'); ?>
                </div>
                <p class="uap-settings-panel-content-paragraph uap-settings-panel-content-paragraph--subtle">
                    <?php echo esc_html_x('Add fonts by name from Google Fonts. Leave weights blank to include the full font family.', 'Page Builder', 'uncanny-automator'); ?>
                </p>

                <div data-upb-google-fonts="true">
                    <?php foreach ($googleFonts as $index => $font): ?>
                        <div data-upb-google-font-row="true">
                            <uo-text-field
                                class="uap-spacing-top"
                                id="<?php echo esc_attr(sprintf('upb_gf[%d][family]', $index)); ?>"
                                label="<?php echo esc_attr_x('Font name', 'Page Builder', 'uncanny-automator'); ?>"
                                value="<?php echo esc_attr($font['family']); ?>"
                                data-upb-google-family="true"
                            ></uo-text-field>

                            <uo-text-field
                                class="uap-spacing-top"
                                id="<?php echo esc_attr(sprintf('upb_gf[%d][weights]', $index)); ?>"
                                label="<?php echo esc_attr_x('Font weights', 'Page Builder', 'uncanny-automator'); ?>"
                                helper="<?php echo esc_attr_x('Optional. Use values like 400;600 for regular and semibold.', 'Page Builder', 'uncanny-automator'); ?>"
                                value="<?php echo esc_attr($font['weights']); ?>"
                                data-upb-google-weights="true"
                            ></uo-text-field>

                            <div class="uap-spacing-top">
                                <uo-button size="small" color="danger" type="button" data-upb-remove-row="true">
                                    <?php echo esc_html_x('Remove', 'Page Builder', 'uncanny-automator'); ?>
                                </uo-button>
                            </div>

                            <div class="uap-settings-panel-content-separator"></div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <p
                    class="uap-settings-panel-content-paragraph uap-settings-panel-content-paragraph--subtle"
                    data-upb-google-empty="true"
                    <?php if ($googleFonts !== []): ?>hidden<?php endif; ?>
                >
                    <?php echo esc_html_x('No Google fonts added yet.', 'Page Builder', 'uncanny-automator'); ?>
                </p>

                <div class="uap-spacing-top">
                    <uo-button size="small" type="button" data-upb-add-google-font="true">
                        <?php echo esc_html_x('Add Google font', 'Page Builder', 'uncanny-automator'); ?>
                    </uo-button>
                </div>

                <div class="uap-settings-panel-content-separator"></div>

                <div class="uap-settings-panel-content-subtitle">
                    <?php echo esc_html_x('Custom fonts', 'Page Builder', 'uncanny-automator'); ?>
                </div>
                <p class="uap-settings-panel-content-paragraph uap-settings-panel-content-paragraph--subtle">
                    <?php echo esc_html_x('Upload WOFF2, TTF, or OTF font files for Uncanny Agent to use in your page designs.', 'Page Builder', 'uncanny-automator'); ?>
                </p>

                <div data-upb-custom-fonts="true">
                    <?php foreach ($customFonts as $index => $font): ?>
                        <div data-upb-custom-font-row="true">
                            <uo-text-field
                                class="uap-spacing-top"
                                id="<?php echo esc_attr(sprintf('upb_cf[%d][family]', $index)); ?>"
                                label="<?php echo esc_attr_x('Font name', 'Page Builder', 'uncanny-automator'); ?>"
                                value="<?php echo esc_attr($font['family']); ?>"
                                data-upb-custom-family="true"
                            ></uo-text-field>

                            <?php
                            /*
                             * uap-field-text carries Automator's field border, radius
                             * and shadow. uap-field-select is deliberately not used:
                             * it flattens its left edge to butt against a paired input,
                             * which leaves an orphaned corner on a standalone control.
                             */
                            ?>
                            <div class="uap-spacing-top">
                                <div>
                                    <label
                                        for="<?php echo esc_attr(sprintf('upb-cf-weight-%d', $index)); ?>"
                                        data-upb-custom-weight-label="true"
                                    >
                                        <strong><?php echo esc_html_x('Font weight', 'Page Builder', 'uncanny-automator'); ?></strong>
                                    </label>
                                </div>
                                <select
                                    class="uap-field-text uap-spacing-top--small"
                                    id="<?php echo esc_attr(sprintf('upb-cf-weight-%d', $index)); ?>"
                                    data-upb-custom-weight="true"
                                    name="<?php echo esc_attr(sprintf('upb_cf[%d][weight]', $index)); ?>"
                                >
                                    <?php foreach ($weightOptions as $option): ?>
                                        <option
                                            value="<?php echo esc_attr($option['value']); ?>"
                                            <?php echo $font['weight'] === $option['value'] ? 'selected' : ''; ?>
                                        >
                                            <?php echo esc_html($option['label']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <input
                                type="hidden"
                                value="<?php echo esc_attr((string) $font['attachment_id']); ?>"
                                data-upb-custom-attachment-id="true"
                                name="<?php echo esc_attr(sprintf('upb_cf[%d][attachment_id]', $index)); ?>"
                            />

                            <p class="uap-settings-panel-content-paragraph uap-settings-panel-content-paragraph--subtle uap-spacing-top--small">
                                <span data-upb-custom-file-name="true">
                                    <?php echo esc_html($font['file_name'] !== '' ? $font['file_name'] : _x('No file selected', 'Page Builder', 'uncanny-automator')); ?>
                                </span>
                            </p>

                            <div class="uap-spacing-top">
                                <uo-button size="small" type="button" class="uap-spacing-right--small" data-upb-choose-font-file="true">
                                    <?php echo esc_html_x('Choose font file', 'Page Builder', 'uncanny-automator'); ?>
                                </uo-button>
                                <uo-button size="small" color="danger" type="button" data-upb-remove-row="true">
                                    <?php echo esc_html_x('Remove', 'Page Builder', 'uncanny-automator'); ?>
                                </uo-button>
                            </div>

                            <div class="uap-settings-panel-content-separator"></div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <p
                    class="uap-settings-panel-content-paragraph uap-settings-panel-content-paragraph--subtle"
                    data-upb-custom-empty="true"
                    <?php if ($customFonts !== []): ?>hidden<?php endif; ?>
                >
                    <?php echo esc_html_x('No custom fonts uploaded yet.', 'Page Builder', 'uncanny-automator'); ?>
                </p>

                <div class="uap-spacing-top">
                    <uo-button size="small" type="button" data-upb-add-custom-font="true">
                        <?php echo esc_html_x('Upload custom font', 'Page Builder', 'uncanny-automator'); ?>
                    </uo-button>
                </div>
            </div>
        </div>

        <div class="uap-settings-panel-bottom">
            <div class="uap-settings-panel-bottom-left">
                <uo-button type="submit">
                    <?php echo esc_html_x('Save fonts', 'Page Builder', 'uncanny-automator'); ?>
                </uo-button>
            </div>
        </div>
    </div>
</form>

<template data-upb-google-font-template="true">
    <div data-upb-google-font-row="true">
        <uo-text-field
            class="uap-spacing-top"
            label="<?php echo esc_attr_x('Font name', 'Page Builder', 'uncanny-automator'); ?>"
            data-upb-google-family="true"
        ></uo-text-field>

        <uo-text-field
            class="uap-spacing-top"
            label="<?php echo esc_attr_x('Font weights', 'Page Builder', 'uncanny-automator'); ?>"
            helper="<?php echo esc_attr_x('Optional. Use values like 400;600 for regular and semibold.', 'Page Builder', 'uncanny-automator'); ?>"
            data-upb-google-weights="true"
        ></uo-text-field>

        <div class="uap-spacing-top">
            <uo-button size="small" color="danger" type="button" data-upb-remove-row="true">
                <?php echo esc_html_x('Remove', 'Page Builder', 'uncanny-automator'); ?>
            </uo-button>
        </div>

        <div class="uap-settings-panel-content-separator"></div>
    </div>
</template>

<template data-upb-custom-font-template="true">
    <div data-upb-custom-font-row="true">
        <uo-text-field
            class="uap-spacing-top"
            label="<?php echo esc_attr_x('Font name', 'Page Builder', 'uncanny-automator'); ?>"
            data-upb-custom-family="true"
        ></uo-text-field>

        <div class="uap-spacing-top">
            <?php
            /*
             * A cloned row has no index yet, so the script pairs this label with
             * its select when it assigns the row its field names.
             */
            ?>
            <div><label data-upb-custom-weight-label="true"><strong><?php echo esc_html_x('Font weight', 'Page Builder', 'uncanny-automator'); ?></strong></label></div>
            <select class="uap-field-text uap-spacing-top--small" data-upb-custom-weight="true">
                <?php foreach ($weightOptions as $option): ?>
                    <option value="<?php echo esc_attr($option['value']); ?>">
                        <?php echo esc_html($option['label']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <input type="hidden" value="0" data-upb-custom-attachment-id="true" />

        <p class="uap-settings-panel-content-paragraph uap-settings-panel-content-paragraph--subtle uap-spacing-top--small">
            <span data-upb-custom-file-name="true">
                <?php echo esc_html_x('No file selected', 'Page Builder', 'uncanny-automator'); ?>
            </span>
        </p>

        <div class="uap-spacing-top">
            <uo-button size="small" type="button" class="uap-spacing-right--small" data-upb-choose-font-file="true">
                <?php echo esc_html_x('Choose font file', 'Page Builder', 'uncanny-automator'); ?>
            </uo-button>
            <uo-button size="small" color="danger" type="button" data-upb-remove-row="true">
                <?php echo esc_html_x('Remove', 'Page Builder', 'uncanny-automator'); ?>
            </uo-button>
        </div>

        <div class="uap-settings-panel-content-separator"></div>
    </div>
</template>

<script>
    (function () {
        var form = document.getElementById('uncanny-page-builder-font-library-form');
        if (!form) {
            return;
        }

        var googleContainer = form.querySelector('[data-upb-google-fonts="true"]');
        var customContainer = form.querySelector('[data-upb-custom-fonts="true"]');
        var googleEmpty = form.querySelector('[data-upb-google-empty="true"]');
        var customEmpty = form.querySelector('[data-upb-custom-empty="true"]');
        var googleTemplate = document.querySelector('[data-upb-google-font-template="true"]');
        var customTemplate = document.querySelector('[data-upb-custom-font-template="true"]');
        var addGoogleButton = form.querySelector('[data-upb-add-google-font="true"]');
        var addCustomButton = form.querySelector('[data-upb-add-custom-font="true"]');

        if (!googleContainer || !customContainer || !googleEmpty || !customEmpty || !googleTemplate || !customTemplate) {
            return;
        }

        /*
         * <uo-text-field> names its hidden field after its own id. Rename both so
         * the element and the field it already created stay in agreement; a field
         * renamed before insertion has not created its hidden input yet.
         */
        function setFieldName(element, name) {
            if (!element) {
                return;
            }

            element.id = name;

            var hidden = element.querySelector('input[type="hidden"]');
            if (hidden) {
                hidden.setAttribute('name', name);
            }
        }

        function setPlainName(element, name) {
            if (element) {
                element.setAttribute('name', name);
            }
        }

        function updateEmptyState() {
            googleEmpty.hidden = googleContainer.querySelectorAll('[data-upb-google-font-row="true"]').length > 0;
            customEmpty.hidden = customContainer.querySelectorAll('[data-upb-custom-font-row="true"]').length > 0;
        }

        function syncNames() {
            Array.prototype.forEach.call(
                googleContainer.querySelectorAll('[data-upb-google-font-row="true"]'),
                function (row, index) {
                    setFieldName(row.querySelector('[data-upb-google-family="true"]'), 'upb_gf[' + index + '][family]');
                    setFieldName(row.querySelector('[data-upb-google-weights="true"]'), 'upb_gf[' + index + '][weights]');
                }
            );

            Array.prototype.forEach.call(
                customContainer.querySelectorAll('[data-upb-custom-font-row="true"]'),
                function (row, index) {
                    setFieldName(row.querySelector('[data-upb-custom-family="true"]'), 'upb_cf[' + index + '][family]');
                    setPlainName(row.querySelector('[data-upb-custom-weight="true"]'), 'upb_cf[' + index + '][weight]');
                    setPlainName(row.querySelector('[data-upb-custom-attachment-id="true"]'), 'upb_cf[' + index + '][attachment_id]');

                    /*
                     * The weight select is a plain control, so it carries no label
                     * of its own. Pair it with its label here, where the row index
                     * that makes the id unique is known.
                     */
                    var weight = row.querySelector('[data-upb-custom-weight="true"]');
                    var weightLabel = row.querySelector('[data-upb-custom-weight-label="true"]');
                    var weightId = 'upb-cf-weight-' + index;

                    if (weight) {
                        weight.id = weightId;
                    }

                    if (weightLabel) {
                        weightLabel.setAttribute('for', weightId);
                    }
                }
            );
        }

        function bindCustomFontPicker(row) {
            var chooseButton = row.querySelector('[data-upb-choose-font-file="true"]');
            var attachmentId = row.querySelector('[data-upb-custom-attachment-id="true"]');
            var fileName = row.querySelector('[data-upb-custom-file-name="true"]');

            if (!chooseButton || !attachmentId || !fileName) {
                return;
            }

            chooseButton.addEventListener('click', function () {
                var media = window.wp && window.wp.media ? window.wp.media : null;
                if (!media) {
                    return;
                }

                var frame = media({
                    title: 'Select a font file',
                    button: { text: 'Use this font' },
                    multiple: false
                });

                frame.on('select', function () {
                    var attachment = frame.state().get('selection').first().toJSON();
                    attachmentId.value = String(attachment.id || 0);
                    fileName.textContent = attachment.filename || attachment.title || 'No file selected';
                });

                frame.open();
            });
        }

        function bindRow(row) {
            var removeButton = row.querySelector('[data-upb-remove-row="true"]');
            if (removeButton) {
                removeButton.addEventListener('click', function () {
                    row.remove();
                    syncNames();
                    updateEmptyState();
                });
            }

            if (row.hasAttribute('data-upb-custom-font-row')) {
                bindCustomFontPicker(row);
            }
        }

        function appendTemplate(container, template) {
            var fragment = template.content.cloneNode(true);
            var row = fragment.querySelector('[data-upb-google-font-row="true"], [data-upb-custom-font-row="true"]');
            if (!row) {
                return;
            }

            container.appendChild(fragment);
            bindRow(container.lastElementChild);
            syncNames();
            updateEmptyState();
        }

        Array.prototype.forEach.call(
            form.querySelectorAll('[data-upb-google-font-row="true"], [data-upb-custom-font-row="true"]'),
            bindRow
        );

        if (addGoogleButton) {
            addGoogleButton.addEventListener('click', function () {
                appendTemplate(googleContainer, googleTemplate);
            });
        }

        if (addCustomButton) {
            addCustomButton.addEventListener('click', function () {
                appendTemplate(customContainer, customTemplate);
            });
        }

        syncNames();
        updateEmptyState();
    })();
</script>
