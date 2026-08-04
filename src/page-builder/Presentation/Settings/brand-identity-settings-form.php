<?php
/**
 * Plain WordPress brand identity settings form.
 *
 * @var bool $updated
 * @var int $logoId
 * @var string $logoSource
 * @var string $logoUrl
 * @var string $logoField
 * @var array{name: string, value: string} $nonce
 */

defined('ABSPATH') || exit;

$hasLogo = $logoId > 0 && $logoUrl !== '';
$logoButtonLabel = $hasLogo
    ? _x('Change logo', 'Page Builder', 'uncanny-automator')
    : _x('Select logo', 'Page Builder', 'uncanny-automator');
$logoContext = $logoSource !== ''
    ? sprintf(
        /* translators: %s: logo source label. */
        _x('Auto-detected from %s.', 'Page Builder', 'uncanny-automator'),
        $logoSource
    )
    : '';

?>

<form method="post" id="uncanny-page-builder-brand-identity-form">
    <input
        type="hidden"
        name="<?php echo esc_attr($nonce['name']); ?>"
        value="<?php echo esc_attr($nonce['value']); ?>"
    />
    <input
        type="hidden"
        id="uncanny-page-builder-brand-logo-id"
        name="<?php echo esc_attr($logoField); ?>"
        value="<?php echo esc_attr((string) $logoId); ?>"
        data-upb-brand-logo-id="true"
    />

    <div class="uap-settings-panel">
        <div class="uap-settings-panel-top">
            <div class="uap-settings-panel-title">
                <?php echo esc_html_x('Logo', 'Page Builder', 'uncanny-automator'); ?>
            </div>
            <div class="uap-settings-panel-content">
                <?php if ($updated): ?>
                    <uo-alert type="success">
                        <?php echo esc_html_x('Logo settings saved.', 'Page Builder', 'uncanny-automator'); ?>
                    </uo-alert>
                <?php endif; ?>

                <p class="uap-settings-panel-content-paragraph uap-settings-panel-content-paragraph--subtle">
                    <?php echo esc_html_x('Choose the logo Uncanny Agent should use when creating headers and branded page sections.', 'Page Builder', 'uncanny-automator'); ?>
                </p>

                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row">
                                <label for="uncanny-page-builder-brand-logo-id">
                                    <?php echo esc_html_x('Logo', 'Page Builder', 'uncanny-automator'); ?>
                                </label>
                            </th>
                            <td>
                                <div>
                                    <img
                                        src="<?php echo esc_url($logoUrl); ?>"
                                        alt=""
                                        data-upb-brand-logo-preview="true"
                                        <?php if (!$hasLogo): ?>
                                            hidden
                                        <?php endif; ?>
                                        style="display:block;max-width:300px;height:auto;max-height:96px;object-fit:contain;margin-bottom:16px;"
                                    />
                                    <p
                                        class="description"
                                        data-upb-brand-logo-empty="true"
                                        <?php if ($hasLogo): ?>
                                            hidden
                                        <?php endif; ?>
                                    >
                                        <?php echo esc_html_x('No logo selected', 'Page Builder', 'uncanny-automator'); ?>
                                    </p>
                                    <p class="description" data-upb-brand-logo-context="true">
                                        <?php echo esc_html($logoContext); ?>
                                    </p>
                                    <p>
                                        <uo-button
                                            size="small"
                                            type="button"
                                            data-upb-brand-logo-picker="true"
                                            data-select-label="<?php echo esc_attr_x('Select logo', 'Page Builder', 'uncanny-automator'); ?>"
                                            data-change-label="<?php echo esc_attr_x('Change logo', 'Page Builder', 'uncanny-automator'); ?>"
                                            data-use-label="<?php echo esc_attr_x('Use as logo', 'Page Builder', 'uncanny-automator'); ?>"
                                            data-manual-context=""
                                        >
                                            <?php echo esc_html($logoButtonLabel); ?>
                                        </uo-button>
                                        <uo-button
                                            size="small"
                                            color="danger"
                                            type="button"
                                            data-upb-brand-logo-remove="true"
                                            <?php if (!$hasLogo): ?>
                                                hidden
                                            <?php endif; ?>
                                        >
                                            <?php echo esc_html_x('Remove logo', 'Page Builder', 'uncanny-automator'); ?>
                                        </uo-button>
                                    </p>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="uap-settings-panel-bottom">
            <div class="uap-settings-panel-bottom-left">
                <uo-button type="submit">
                    <?php echo esc_html_x('Save logo', 'Page Builder', 'uncanny-automator'); ?>
                </uo-button>
            </div>
        </div>
    </div>
</form>

<script>
    (function () {
        var pickerButton = document.querySelector('[data-upb-brand-logo-picker="true"]');
        var removeButton = document.querySelector('[data-upb-brand-logo-remove="true"]');
        var input = document.querySelector('[data-upb-brand-logo-id="true"]');
        var preview = document.querySelector('[data-upb-brand-logo-preview="true"]');
        var emptyState = document.querySelector('[data-upb-brand-logo-empty="true"]');
        var context = document.querySelector('[data-upb-brand-logo-context="true"]');

        if (!pickerButton || !removeButton || !input || !preview || !emptyState || !context) {
            return;
        }

        function setSelectedState(selected) {
            preview.hidden = !selected;
            emptyState.hidden = selected;
            removeButton.hidden = !selected;
            pickerButton.textContent = selected
                ? pickerButton.getAttribute('data-change-label') || ''
                : pickerButton.getAttribute('data-select-label') || '';
            context.textContent = pickerButton.getAttribute('data-manual-context') || '';
        }

        pickerButton.addEventListener('click', function () {
            var media = window.wp && window.wp.media ? window.wp.media : null;
            if (!media) {
                return;
            }

            var frame = media({
                title: pickerButton.getAttribute('data-change-label') || '',
                button: { text: pickerButton.getAttribute('data-use-label') || '' },
                multiple: false,
                library: { type: 'image' }
            });

            frame.on('select', function () {
                var attachment = frame.state().get('selection').first().toJSON();
                input.value = String(attachment.id || 0);
                preview.src = attachment.sizes && attachment.sizes.medium && attachment.sizes.medium.url
                    ? attachment.sizes.medium.url
                    : attachment.url || '';
                setSelectedState(Boolean(preview.src));
            });

            frame.open();
        });

        removeButton.addEventListener('click', function () {
            input.value = '0';
            preview.src = '';
            setSelectedState(false);
        });
    })();
</script>
