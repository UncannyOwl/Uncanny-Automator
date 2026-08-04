<?php
/**
 * Settings JavaScript section.
 *
 * @var bool $updated
 * @var \UncannyPageBuilder\Domain\Settings\ToolSettings $toolSettings
 * @var array{name: string, value: string} $nonce
 * @var array{page: string, global_part: string} $customJavaScriptFields
 * @var string $approvedLibraryField
 * @var list<array{slug: string, label: string, description: string, enabled: bool}> $approvedLibraries
 */

defined('ABSPATH') || exit;
?>
<form method="post" id="uncanny-page-builder-javascript-form">
    <input
        type="hidden"
        name="<?php echo esc_attr($nonce['name']); ?>"
        value="<?php echo esc_attr($nonce['value']); ?>"
    />

    <div class="uap-settings-panel upb-settings-javascript">
        <div class="uap-settings-panel-top">
            <div class="uap-settings-panel-title">
                <?php echo esc_html_x('JavaScript', 'Page Builder', 'uncanny-automator'); ?>
            </div>
            <div class="uap-settings-panel-content">
                <?php if ($updated): ?>
                    <uo-alert type="success">
                        <?php echo esc_html_x('JavaScript settings saved.', 'Page Builder', 'uncanny-automator'); ?>
                    </uo-alert>
                <?php endif; ?>

                <p class="uap-settings-panel-content-paragraph uap-settings-panel-content-paragraph--subtle">
                    <?php echo esc_html_x('Choose which JavaScript features Page Builder can use and review which libraries are approved for runtime work.', 'Page Builder', 'uncanny-automator'); ?>
                </p>

                <?php
                /*
                 * A separator opens each group. It carries the space that sets the
                 * groups apart, so the gap between them stays wider than the gap
                 * between the switches inside one.
                 */
                ?>
                <div class="uap-settings-panel-content-separator"></div>

                <div class="uap-settings-panel-content-subtitle"><?php echo esc_html_x('Custom JavaScript', 'Page Builder', 'uncanny-automator'); ?></div>
                <p class="uap-settings-panel-content-paragraph uap-settings-panel-content-paragraph--subtle">
                    <?php echo esc_html_x('These switches make the JavaScript lane explicit. Turn them off if you do not want Page Builder to load custom runtime code in that area.', 'Page Builder', 'uncanny-automator'); ?>
                </p>

                <?php
                /*
                 * <uo-switch> posts through a hidden field it names after its own
                 * id, and always submits "1" or "0". The id therefore carries the
                 * POST key, and the save path reads the value rather than testing
                 * for the key's presence.
                 */
                ?>
                <?php
                /*
                 * status-label takes the on and off text as a pair and renders it
                 * beside the switch, tracking the state, which is how the Automator
                 * settings toggles read.
                 */
                $switchStatusLabel = sprintf(
                    '%s,%s',
                    _x('Enabled', 'Page Builder', 'uncanny-automator'),
                    _x('Disabled', 'Page Builder', 'uncanny-automator')
                );
                ?>
                <uo-switch
                    class="uap-spacing-top"
                    id="<?php echo esc_attr($customJavaScriptFields['page']); ?>"
                    label="<?php echo esc_attr_x('Enable page custom JavaScript', 'Page Builder', 'uncanny-automator'); ?>"
                    status-label="<?php echo esc_attr($switchStatusLabel); ?>"
                    <?php if ($toolSettings->pageCustomJavaScriptEnabled()) : ?>checked<?php endif; ?>
                ></uo-switch>

                <uo-switch
                    class="uap-spacing-top"
                    id="<?php echo esc_attr($customJavaScriptFields['global_part']); ?>"
                    label="<?php echo esc_attr_x('Enable reusable custom JavaScript', 'Page Builder', 'uncanny-automator'); ?>"
                    status-label="<?php echo esc_attr($switchStatusLabel); ?>"
                    <?php if ($toolSettings->globalPartCustomJavaScriptEnabled()) : ?>checked<?php endif; ?>
                ></uo-switch>

                <div class="uap-settings-panel-content-separator"></div>

                <div class="uap-settings-panel-content-subtitle"><?php echo esc_html_x('Approved libraries', 'Page Builder', 'uncanny-automator'); ?></div>
                <p class="uap-settings-panel-content-paragraph uap-settings-panel-content-paragraph--subtle">
                    <?php echo esc_html_x('This is your approved library list for JavaScript work. Each description explains what the library is best at before you decide to allow it.', 'Page Builder', 'uncanny-automator'); ?>
                </p>

                <?php foreach ($approvedLibraries as $library): ?>
                    <uo-switch
                        class="uap-spacing-top"
                        id="<?php echo esc_attr(sprintf('%s[%s]', $approvedLibraryField, $library['slug'])); ?>"
                        label="<?php echo esc_attr($library['label']); ?>"
                        helper="<?php echo esc_attr($library['description']); ?>"
                        status-label="<?php echo esc_attr($switchStatusLabel); ?>"
                        <?php if ($library['enabled']) : ?>checked<?php endif; ?>
                    ></uo-switch>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="uap-settings-panel-bottom">
            <div class="uap-settings-panel-bottom-left">
                <uo-button type="submit">
                    <?php echo esc_html_x('Save JavaScript settings', 'Page Builder', 'uncanny-automator'); ?>
                </uo-button>
            </div>
        </div>
    </div>
</form>
