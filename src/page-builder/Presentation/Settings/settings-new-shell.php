<?php
/**
 * Settings shell.
 *
 * Rendered with Automator's shared admin chrome (uap-settings + uas-tabs) so the
 * Page Builder settings page matches the Automator settings page exactly: a page
 * heading, a horizontal top menu, and a vertical left sidebar per tab. All of the
 * styling ships in Automator's admin bundle, enqueued in AdminMenu; this view adds
 * no CSS of its own. Navigation is reload-based: every tab carries its route in
 * `href`, and only the open tab and section have a panel, so the component follows
 * the `href` of any other tab.
 *
 * @var string $activeTab
 * @var array<int, array{id: string, label: string, url: string}> $tabs
 * @var array<int, array{id: string, label: string, url: string, icon: string}> $sidebarItems
 * @var string $activeSidebar
 * @var callable $contentRenderer
 */

defined('ABSPATH') || exit;
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Included inside a method; these variables are local to that call.

?>
<div class="uap uap-settings">
    <div class="uap-settings-header">
        <div class="uap-settings-header__title">
            <?php echo esc_html_x('Uncanny Page Builder', 'Page Builder', 'uncanny-automator'); ?>
        </div>
    </div>

    <div class="uap-settings-content">
        <uas-tabs class="uap-settings-content-main-tabs">
            <?php foreach ($tabs as $tab): ?>
                <uas-tab
                    value="<?php echo esc_attr($tab['id']); ?>"
                    href="<?php echo esc_url($tab['url']); ?>"
                    <?php if ($tab['id'] === $activeTab): ?>active<?php endif; ?>
                ><?php echo esc_html($tab['label']); ?></uas-tab>
            <?php endforeach; ?>

            <uas-tab-panel slot="panel" name="<?php echo esc_attr($activeTab); ?>" active>
                <uas-tabs orientation="vertical">
                    <?php foreach ($sidebarItems as $item): ?>
                        <uas-tab
                            value="<?php echo esc_attr($item['id']); ?>"
                            href="<?php echo esc_url($item['url']); ?>"
                            <?php if ($item['id'] === $activeSidebar): ?>active<?php endif; ?>
                        ><?php if (($item['icon'] ?? '') !== ''): ?><uas-icon code="<?php echo esc_attr($item['icon']); ?>"></uas-icon> <?php endif; ?><?php echo esc_html($item['label']); ?></uas-tab>
                    <?php endforeach; ?>

                    <uas-tab-panel slot="panel" name="<?php echo esc_attr($activeSidebar); ?>" active>
                        <?php $contentRenderer(); ?>
                    </uas-tab-panel>
                </uas-tabs>
            </uas-tab-panel>
        </uas-tabs>
    </div>
</div>
