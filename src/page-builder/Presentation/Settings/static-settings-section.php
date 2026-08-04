<?php
/**
 * Static placeholder for settings sections not migrated yet.
 *
 * @var array{title: string, description: string} $settingsSection
 */

defined('ABSPATH') || exit;

?>
<div class="uap-settings-panel">
    <div class="uap-settings-panel-top">
        <div class="uap-settings-panel-title"><?php echo esc_html($settingsSection['title']); ?></div>
        <div class="uap-settings-panel-content">
            <p class="uap-settings-panel-content-paragraph uap-settings-panel-content-paragraph--subtle">
                <?php echo esc_html($settingsSection['description']); ?>
            </p>
        </div>
    </div>
</div>
