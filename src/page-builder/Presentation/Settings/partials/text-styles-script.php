<?php
/**
 * Shared text-styles behavior script.
 *
 * Binds against the container identified by $containerId, so it works on the
 * sitewide Typography settings page (container = the settings <form>) and in
 * the page-edit metabox (container = a wrapper <div> inside the post form).
 *
 * @var string $containerId
 */

defined('ABSPATH') || exit;

$customFontValue = '__custom__';
$defaultDisplayValue = _x('Use default', 'Page Builder', 'uncanny-automator');

?>
<script>
    (function () {
        var root = document.getElementById('<?php echo esc_js($containerId); ?>');
        if (!root) {
            return;
        }

        var form = root.closest('form');

        function syncLinkValues() {
            Array.prototype.forEach.call(
                root.querySelectorAll('[data-upb-link-select]'),
                function (select) {
                    var key = select.getAttribute('data-upb-link-select') || '';
                    var hidden = root.querySelector('[data-upb-link-value="' + key + '"]');
                    if (hidden) {
                        hidden.value = select.value || '';
                    }
                }
            );
        }

        /*
         * The hover colour only shows while pointing at the sample, so keep both
         * colours and pick between them. Reading the field on hover instead would
         * miss edits made while the pointer is already over the sample.
         */
        var linkPreviewColors = { base: '', hover: '' };
        var linkPreviewHovered = false;

        function paintLinkPreview(preview) {
            var useHover = linkPreviewHovered && linkPreviewColors.hover !== '';
            preview.style.color = useHover ? linkPreviewColors.hover : linkPreviewColors.base;
        }

        function applyLinkPreview() {
            syncLinkValues();

            var preview = root.querySelector('[data-upb-link-preview="true"]');
            if (!preview) {
                return;
            }

            linkPreviewColors.base = '';
            linkPreviewColors.hover = '';

            Array.prototype.forEach.call(
                root.querySelectorAll('[data-upb-link-field="true"]'),
                function (field) {
                    var property = field.getAttribute('data-link-property') || '';
                    if (property === 'color') {
                        linkPreviewColors.base = field.value || '';
                        return;
                    }

                    if (property === 'hoverColor') {
                        linkPreviewColors.hover = field.value || '';
                        return;
                    }

                    if (property === 'textDecoration') {
                        preview.style.textDecoration = field.value || '';
                    }
                }
            );

            paintLinkPreview(preview);
        }

        (function bindLinkPreviewHover() {
            var preview = root.querySelector('[data-upb-link-preview="true"]');
            if (!preview) {
                return;
            }

            preview.addEventListener('mouseenter', function () {
                linkPreviewHovered = true;
                paintLinkPreview(preview);
            });

            preview.addEventListener('mouseleave', function () {
                linkPreviewHovered = false;
                paintLinkPreview(preview);
            });
        })();

        function applyPreview(card) {
            var preview = card.querySelector('[data-upb-typography-preview="true"]');
            if (!preview) {
                return;
            }

            var familyValue = card.querySelector('[data-upb-typography-font-family-value="true"]');
            preview.style.fontFamily = familyValue ? familyValue.value : '';

            Array.prototype.forEach.call(
                card.querySelectorAll('[data-preview-property]'),
                function (input) {
                    var property = input.getAttribute('data-preview-property') || '';
                    var cssProperty = property.replace(/-([a-z])/g, function (_, letter) {
                        return letter.toUpperCase();
                    });
                    var nextValue = input.value === '<?php echo esc_attr($defaultDisplayValue); ?>'
                        ? 'inherit'
                        : input.value || '';
                    preview.style[cssProperty] = nextValue;
                }
            );
        }

        function syncFontFamily(card) {
            var select = card.querySelector('[data-upb-typography-font-family-select="true"]');
            var hiddenValue = card.querySelector('[data-upb-typography-font-family-value="true"]');
            var customWrap = card.querySelector('[data-upb-typography-font-family-custom-wrap="true"]');
            var customInput = card.querySelector('[data-upb-typography-font-family-custom="true"]');
            var help = card.querySelector('[data-upb-typography-font-family-help="true"]');

            if (!select || !hiddenValue || !customWrap || !customInput) {
                return;
            }

            if (select.value === '<?php echo esc_attr($customFontValue); ?>') {
                customWrap.hidden = false;
                if (help) {
                    help.hidden = true;
                }
                hiddenValue.value = customInput.value || '';
            } else {
                customWrap.hidden = true;
                if (help) {
                    help.hidden = false;
                }
                hiddenValue.value = select.value || '';
            }

            applyPreview(card);
        }

        Array.prototype.forEach.call(
            root.querySelectorAll('[data-upb-typography-role="true"]'),
            function (card) {
                syncFontFamily(card);
                applyPreview(card);

                Array.prototype.forEach.call(
                    card.querySelectorAll('[data-upb-typography-input="true"]'),
                    function (input) {
                        input.addEventListener('input', function () {
                            applyPreview(card);
                        });
                    }
                );

                var select = card.querySelector('[data-upb-typography-font-family-select="true"]');
                if (select) {
                    select.addEventListener('change', function () {
                        syncFontFamily(card);
                    });
                }

                var customInput = card.querySelector('[data-upb-typography-font-family-custom="true"]');
                if (customInput) {
                    customInput.addEventListener('input', function () {
                        syncFontFamily(card);
                    });
                }

                var resetButton = card.querySelector('[data-upb-typography-reset="true"]');
                if (resetButton) {
                    resetButton.addEventListener('click', function () {
                        Array.prototype.forEach.call(
                            card.querySelectorAll('[data-upb-typography-input="true"]'),
                            function (input) {
                                input.value = input.getAttribute('data-default-display-value') || '';
                            }
                        );

                        var hiddenValue = card.querySelector('[data-upb-typography-font-family-value="true"]');
                        var familySelect = card.querySelector('[data-upb-typography-font-family-select="true"]');
                        var familyCustom = card.querySelector('[data-upb-typography-font-family-custom="true"]');
                        var defaultFamily = hiddenValue ? hiddenValue.getAttribute('data-default-value') || '' : '';

                        if (hiddenValue) {
                            hiddenValue.value = defaultFamily;
                        }

                        if (familyCustom) {
                            familyCustom.value = defaultFamily;
                        }

                        if (familySelect) {
                            var matched = false;
                            Array.prototype.forEach.call(familySelect.options, function (option) {
                                if (option.value === defaultFamily) {
                                    familySelect.value = defaultFamily;
                                    matched = true;
                                }
                            });

                            if (!matched) {
                                familySelect.value = '<?php echo esc_attr($customFontValue); ?>';
                            }
                        }

                        syncFontFamily(card);

                        /*
                         * syncFontFamily only repaints the preview once it has a
                         * font control to read, and the per level heading cards
                         * carry a size and nothing else. Repaint here so their
                         * preview follows the restored values too.
                         */
                        applyPreview(card);
                    });
                }
            }
        );

        Array.prototype.forEach.call(
            root.querySelectorAll('[data-upb-link-field="true"]'),
            function (field) {
                field.addEventListener('input', applyLinkPreview);
                field.addEventListener('change', applyLinkPreview);
            }
        );

        Array.prototype.forEach.call(
            root.querySelectorAll('[data-upb-link-reset="true"]'),
            function (button) {
                button.addEventListener('click', function () {
                    Array.prototype.forEach.call(
                        root.querySelectorAll('[data-upb-link-field="true"]'),
                        function (field) {
                            var nextValue = field.getAttribute('data-default-value') || '';
                            field.value = nextValue;
                        }
                    );

                    /* Runs on click, so the picker is loaded by now. */
                    if (window.jQuery && typeof window.jQuery.fn.wpColorPicker === 'function') {
                        root.querySelectorAll('.upb-color-control').forEach(function (field) {
                            window.jQuery(field).wpColorPicker('color', field.value || '');
                        });
                    }

                    applyLinkPreview();
                });
            }
        );

        /*
         * This script is inline in the page body, while wp-color-picker is a
         * footer script, so the plugin does not exist yet when this runs.
         * Testing for it here skipped the setup and left the colour fields as
         * plain text inputs, so wait for ready and test inside.
         */
        if (window.jQuery) {
            window.jQuery(function ($) {
                if (typeof $.fn.wpColorPicker !== 'function') {
                    return;
                }

                /*
                 * The picker reports the new colour through the callback before
                 * it writes it back to the input, so reading the input here would
                 * apply the previous colour and the preview would trail a step
                 * behind. Take the value from the callback and set it first. Each
                 * input is bound separately so the callbacks know their own field.
                 */
                $(root).find('.upb-color-control').each(function () {
                    var field = this;

                    $(field).wpColorPicker({
                        change: function (event, ui) {
                            if (ui && ui.color) {
                                field.value = ui.color.toString();
                            }

                            applyLinkPreview();
                        },
                        clear: function () {
                            field.value = '';
                            applyLinkPreview();
                        }
                    });
                });
            });
        }

        applyLinkPreview();

        if (form) {
            form.addEventListener('submit', function () {
                Array.prototype.forEach.call(
                    root.querySelectorAll('[data-upb-typography-input="true"]'),
                    function (input) {
                        if (input.value === '<?php echo esc_attr($defaultDisplayValue); ?>') {
                            input.value = 'inherit';
                        }
                    }
                );
            });
        }
    })();
</script>
