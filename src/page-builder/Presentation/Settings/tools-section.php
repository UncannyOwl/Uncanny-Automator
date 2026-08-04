<?php
/**
 * Settings tools section.
 *
 * @var array{class: string, message: string}|null $notice
 * @var array{pending: int, terminal_failures: array<int, array{page_id: int, attempts: int, next_run_at: int, claimed_at: int|null, last_error_code: string, last_error_message: string, terminal_at: int|null}>} $queueStatus
 * @var string $refreshAllUrl
 */

defined('ABSPATH') || exit;
?>
<div class="uap-settings-panel upb-settings-tools">
    <div class="uap-settings-panel-top">
        <div class="uap-settings-panel-title">
            <?php echo esc_html_x('Tools', 'Page Builder', 'uncanny-automator'); ?>
        </div>
        <div class="uap-settings-panel-content">
            <p class="uap-settings-panel-content-paragraph uap-settings-panel-content-paragraph--subtle">
                <?php echo esc_html_x('Recovery tools for Uncanny Page Builder pages.', 'Page Builder', 'uncanny-automator'); ?>
            </p>

            <?php if (is_array($notice)): ?>
                <uo-alert type="<?php echo false !== strpos($notice['class'], 'error') ? 'error' : 'success'; ?>">
                    <?php echo esc_html($notice['message']); ?>
                </uo-alert>
            <?php endif; ?>

            <?php
            /*
             * A separator opens the tool. It carries the space that sets the tool
             * apart from the section intro above it.
             */
            ?>
            <div class="uap-settings-panel-content-separator"></div>

            <div class="upb-settings-tools__card">
                <div class="upb-settings-tools__copy">
                    <div class="uap-settings-panel-content-subtitle"><?php echo esc_html_x('Refresh working previews', 'Page Builder', 'uncanny-automator'); ?></div>
                    <p class="uap-settings-panel-content-paragraph uap-settings-panel-content-paragraph--subtle">
                        <?php echo esc_html_x('Use this only when saved draft source and the Page Builder editor preview disagree.', 'Page Builder', 'uncanny-automator'); ?>
                    </p>
                    <p class="uap-settings-panel-content-paragraph uap-settings-panel-content-paragraph--subtle">
                        <?php echo esc_html_x('This recovery tool recompiles editor-only working previews from saved draft source. It cannot update a live page or move a published artifact pointer.', 'Page Builder', 'uncanny-automator'); ?>
                    </p>
                    <p class="uap-settings-panel-content-paragraph uap-settings-panel-content-paragraph--subtle">
                        <?php echo esc_html_x('The first batch is refreshed immediately. Any remaining pages continue in the background through WordPress cron.', 'Page Builder', 'uncanny-automator'); ?>
                    </p>

                    <?php if (($queueStatus['pending'] ?? 0) > 0): ?>
                        <p>
                            <?php
                            echo esc_html(sprintf(
                                /* translators: %d: number of queued working-preview refreshes. */
                                _x('Working previews waiting to refresh: %d.', 'Page Builder', 'uncanny-automator'),
                                (int) $queueStatus['pending']
                            ));
                            ?>
                        </p>
                    <?php endif; ?>

                    <?php if (!empty($queueStatus['terminal_failures'])): ?>
                        <uo-alert
                            type="error"
                            heading="<?php
                            echo esc_attr(sprintf(
                                /* translators: %d: number of failed working-preview refreshes. */
                                _x('Working previews that could not be refreshed: %d.', 'Page Builder', 'uncanny-automator'),
                                count($queueStatus['terminal_failures'])
                            ));
                            ?>"
                        >
                            <ul>
                                <?php foreach (array_slice($queueStatus['terminal_failures'], 0, 5) as $failure): ?>
                                    <li>
                                        <?php
                                        echo esc_html(sprintf(
                                            /* translators: 1: page id, 2: error code, 3: error message. */
                                            _x('Page %1$d: %2$s %3$s', 'Page Builder', 'uncanny-automator'),
                                            (int) ($failure['page_id'] ?? 0),
                                            (string) ($failure['last_error_code'] ?? ''),
                                            (string) ($failure['last_error_message'] ?? '')
                                        ));
                                        ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </uo-alert>
                    <?php endif; ?>
                </div>

                <div class="upb-settings-tools__actions">
                    <uo-button size="small" href="<?php echo esc_url($refreshAllUrl); ?>">
                        <?php echo esc_html_x('Refresh all working previews', 'Page Builder', 'uncanny-automator'); ?>
                    </uo-button>
                </div>
            </div>
        </div>
    </div>
</div>
