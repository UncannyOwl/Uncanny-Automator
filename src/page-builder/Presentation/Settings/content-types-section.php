<?php
/**
 * Settings post types section.
 *
 * @var bool $updated
 * @var bool $rejected
 * @var string $fieldName
 * @var string $presentedFieldName
 * @var array{name: string, value: string} $nonce
 * @var list<array{slug: string, label: string, enabled: bool}> $contentTypes
 */

defined('ABSPATH') || exit;
?>
<form method="post" id="uncanny-page-builder-content-types-form">
    <input
        type="hidden"
        name="<?php echo esc_attr($nonce['name']); ?>"
        value="<?php echo esc_attr($nonce['value']); ?>"
    />

    <div class="uap-settings-panel upb-settings-content-types">
        <div class="uap-settings-panel-top">
            <div class="uap-settings-panel-title">
                <?php echo esc_html_x('Post types', 'Page Builder', 'uncanny-automator'); ?>
            </div>
            <div class="uap-settings-panel-content">
                <?php if ($updated): ?>
                    <uo-alert type="success">
                        <?php echo esc_html_x('Post type settings saved.', 'Page Builder', 'uncanny-automator'); ?>
                    </uo-alert>
                <?php endif; ?>

                <?php if ($rejected): ?>
                    <uo-alert type="error">
                        <?php echo esc_html_x('Post type settings were not saved. Uncanny Page Builder currently supports only WordPress Posts and Pages.', 'Page Builder', 'uncanny-automator'); ?>
                    </uo-alert>
                <?php endif; ?>

                <p class="uap-settings-panel-content-paragraph uap-settings-panel-content-paragraph--subtle">
                    <?php echo esc_html_x('Choose whether Uncanny Page Builder is offered for WordPress Posts and Pages.', 'Page Builder', 'uncanny-automator'); ?>
                </p>
                <p class="description">
                    <?php echo esc_html_x('Plugin-owned custom post types require dedicated integrations and are not available yet.', 'Page Builder', 'uncanny-automator'); ?>
                </p>
                <p class="description">
                    <strong><?php echo esc_html_x('Turning off a post type makes WordPress the active editor and renderer.', 'Page Builder', 'uncanny-automator'); ?></strong>
                    <?php echo esc_html_x('Page Builder work stays dormant in case the post type is enabled again.', 'Page Builder', 'uncanny-automator'); ?>
                </p>

                <?php if ($contentTypes === []): ?>
                    <p><?php echo esc_html_x('No compatible post types are currently registered.', 'Page Builder', 'uncanny-automator'); ?></p>
                <?php else: ?>
                    <fieldset>
                        <legend class="screen-reader-text"><?php echo esc_html_x('Post types', 'Page Builder', 'uncanny-automator'); ?></legend>

                        <?php
                        /*
                         * <uo-switch> posts one entry per control, keyed by slug,
                         * rather than the checkbox shape of a list containing only
                         * the enabled slugs. The page maps that back to a slug list
                         * before saving, so the domain use case is unchanged.
                         */
                        ?>
                        <?php
                        /*
                         * status-label takes the on and off text as a pair and
                         * renders it beside the switch, tracking the state, which
                         * is how the Automator settings toggles read.
                         */
                        $switchStatusLabel = sprintf(
                            '%s,%s',
                            _x('Enabled', 'Page Builder', 'uncanny-automator'),
                            _x('Disabled', 'Page Builder', 'uncanny-automator')
                        );
                        ?>
                        <?php foreach ($contentTypes as $contentType): ?>
                            <input
                                type="hidden"
                                name="<?php echo esc_attr($presentedFieldName); ?>[]"
                                value="<?php echo esc_attr($contentType['slug']); ?>"
                            />
                            <uo-switch
                                class="uap-spacing-top"
                                id="<?php echo esc_attr(sprintf('%s[%s]', $fieldName, $contentType['slug'])); ?>"
                                label="<?php echo esc_attr($contentType['label']); ?>"
                                helper="<?php echo esc_attr($contentType['slug']); ?>"
                                status-label="<?php echo esc_attr($switchStatusLabel); ?>"
                                <?php if ($contentType['enabled']): ?>checked<?php endif; ?>
                            ></uo-switch>
                        <?php endforeach; ?>
                    </fieldset>
                <?php endif; ?>
            </div>
        </div>

        <div class="uap-settings-panel-bottom">
            <div class="uap-settings-panel-bottom-left">
                <uo-button type="submit">
                    <?php echo esc_html_x('Save post types', 'Page Builder', 'uncanny-automator'); ?>
                </uo-button>
            </div>
        </div>
    </div>
</form>
