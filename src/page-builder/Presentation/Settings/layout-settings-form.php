<?php
/**
 * Plain WordPress layout settings form.
 *
 * @var bool $globalPartsUpdated
 * @var bool $globalPartsRejected
 * @var ?int $currentHeaderId
 * @var ?int $currentFooterId
 * @var array<int, array{id: int, title: string}> $headerOptions
 * @var array<int, array{id: int, title: string}> $footerOptions
 */

defined('ABSPATH') || exit;

?>
<form method="post">
    <?php wp_nonce_field('uncanny_page_builder_save_settings', 'uncanny_page_builder_settings_nonce'); ?>

    <div class="uap-settings-panel">
        <div class="uap-settings-panel-top">
            <div class="uap-settings-panel-title">
                <?php echo esc_html_x('Layout', 'Page Builder', 'uncanny-automator'); ?>
            </div>
            <div class="uap-settings-panel-content">
                <?php if ($globalPartsUpdated): ?>
                    <uo-alert type="success">
                        <?php echo esc_html_x('Layout settings saved.', 'Page Builder', 'uncanny-automator'); ?>
                    </uo-alert>
                <?php endif; ?>

                <?php if ($globalPartsRejected): ?>
                    <uo-alert type="error">
                        <?php echo esc_html_x('Layout settings were not saved because a selected header or footer is no longer available. Reload this page and choose again.', 'Page Builder', 'uncanny-automator'); ?>
                    </uo-alert>
                <?php endif; ?>

                <p class="uap-settings-panel-content-paragraph uap-settings-panel-content-paragraph--subtle">
                    <?php echo esc_html_x('Choose the default header and footer for pages built with Uncanny Page Builder. You can change them for any page later. Nothing else on your website will change.', 'Page Builder', 'uncanny-automator'); ?>
                </p>

                <?php
                /*
                 * Header and footer are a natural pair, so they share a row in
                 * Automator's flex field container with the label above each
                 * control, matching the typography cards.
                 */
                ?>
                <div class="uap-field-container uap-spacing-top">
                    <div class="uap-field uap-spacing-right">
                        <div>
                            <label for="default_page_header">
                                <strong><?php echo esc_html_x('Header', 'Page Builder', 'uncanny-automator'); ?></strong>
                            </label>
                        </div>
                        <select
                            class="uap-field-text uap-spacing-top--small"
                            id="default_page_header"
                            name="default_page_header"
                        >
                            <option value=""><?php echo esc_html_x('Do not add a saved header', 'Page Builder', 'uncanny-automator'); ?></option>
                            <?php foreach ($headerOptions as $option): ?>
                                <option value="<?php echo esc_attr((string) $option['id']); ?>" <?php selected($currentHeaderId, $option['id']); ?>>
                                    <?php echo esc_html($option['title']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="uap-field">
                        <div>
                            <label for="default_page_footer">
                                <strong><?php echo esc_html_x('Footer', 'Page Builder', 'uncanny-automator'); ?></strong>
                            </label>
                        </div>
                        <select
                            class="uap-field-text uap-spacing-top--small"
                            id="default_page_footer"
                            name="default_page_footer"
                        >
                            <option value=""><?php echo esc_html_x('Do not add a saved footer', 'Page Builder', 'uncanny-automator'); ?></option>
                            <?php foreach ($footerOptions as $option): ?>
                                <option value="<?php echo esc_attr((string) $option['id']); ?>" <?php selected($currentFooterId, $option['id']); ?>>
                                    <?php echo esc_html($option['title']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <div class="uap-settings-panel-bottom">
            <div class="uap-settings-panel-bottom-left">
                <uo-button type="submit">
                    <?php echo esc_html_x('Save layout settings', 'Page Builder', 'uncanny-automator'); ?>
                </uo-button>
            </div>
        </div>
    </div>
</form>
