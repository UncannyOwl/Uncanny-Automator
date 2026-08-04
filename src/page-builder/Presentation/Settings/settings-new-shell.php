<?php
/**
 * Settings shell.
 *
 * Rendered with Automator's shared admin chrome (uap-settings + uo-tabs) so the
 * Page Builder settings page matches the Automator settings page exactly: a page
 * heading, a horizontal top menu, and a vertical left sidebar per tab. All of the
 * styling ships in Automator's admin bundle, enqueued in AdminMenu; this view adds
 * no CSS of its own. Navigation is reload-based: the active tab/section carries the
 * `active` attribute and every other tab carries an `href` the component follows.
 *
 * @var string $activeTab
 * @var array<int, array{id: string, label: string, url: string}> $tabs
 * @var array<int, array{id: string, label: string, url: string, icon: string}> $sidebarItems
 * @var string $activeSidebar
 * @var string $sidebarParameter
 * @var callable $contentRenderer
 */

defined('ABSPATH') || exit;

?>
<div class="uap uap-settings">
    <div class="uap-settings-header">
        <div class="uap-settings-header__title">
            <?php echo esc_html_x('Uncanny Page Builder', 'Page Builder', 'uncanny-automator'); ?>
        </div>
    </div>

    <div class="uap-settings-content">
        <uo-tabs class="uap-settings-content-main-tabs">
            <?php foreach ($tabs as $tab): ?>
                <?php $isActiveTab = $tab['id'] === $activeTab; ?>
                <?php
                /*
                 * aria-current names the open tab for assistive technology. The
                 * component marks the active tab visually through its own active
                 * attribute, which carries no meaning on its own.
                 *
                 * The component renders its own anchor without an address, so the
                 * tab is neither reachable nor announced as a link. tabindex and
                 * role supply both, and the handler at the end of this file
                 * carries the key press. A nested anchor is avoided, since the
                 * component's own anchor already wraps this content.
                 */
                ?>
                <uo-tab
                    id="<?php echo esc_attr($tab['id']); ?>"
                    href="<?php echo esc_url($tab['url']); ?>"
                    role="link"
                    tabindex="0"
                    <?php if ($isActiveTab): ?>active aria-current="page"<?php endif; ?>
                ><?php echo esc_html($tab['label']); ?></uo-tab>
            <?php endforeach; ?>

            <uo-tab-panel id="<?php echo esc_attr($activeTab); ?>" active>
                <uo-tabs direction="column" parameter="<?php echo esc_attr($sidebarParameter); ?>">
                    <?php foreach ($sidebarItems as $item): ?>
                        <?php $isActiveItem = $item['id'] === $activeSidebar; ?>
                        <uo-tab
                            id="<?php echo esc_attr($item['id']); ?>"
                            href="<?php echo esc_url($item['url']); ?>"
                            role="link"
                            tabindex="0"
                            <?php if ($isActiveItem): ?>active aria-current="page"<?php endif; ?>
                        ><?php if (($item['icon'] ?? '') !== ''): ?><uo-icon id="<?php echo esc_attr($item['icon']); ?>"></uo-icon> <?php endif; ?><?php echo esc_html($item['label']); ?></uo-tab>
                    <?php endforeach; ?>

                    <uo-tab-panel id="<?php echo esc_attr($activeSidebar); ?>" active>
                        <?php $contentRenderer(); ?>
                    </uo-tab-panel>
                </uo-tabs>
            </uo-tab-panel>
        </uo-tabs>
    </div>
</div>

<script>
    (function () {
        var root = document.querySelector('.uap-settings');
        if (!root) {
            return;
        }

        /*
         * The component moves its tabs into its own shadow root, and it carries a
         * click to the address itself. A key press has to be carried here. The
         * event crosses the shadow boundary, so the path names the tab that the
         * listener cannot see directly.
         */
        root.addEventListener('keydown', function (event) {
            if (event.key !== 'Enter' && event.key !== ' ') {
                return;
            }

            var tab = event.composedPath().filter(function (node) {
                return node.tagName && node.tagName.toLowerCase() === 'uo-tab';
            })[0];

            if (!tab) {
                return;
            }

            var href = tab.getAttribute('href');
            if (!href) {
                return;
            }

            event.preventDefault();
            window.location.href = href;
        });
    })();
</script>
