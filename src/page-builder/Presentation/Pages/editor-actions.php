<?php
/**
 * Owned-page actions row — core buttons under the title.
 *
 * @var \WP_Post $post
 * @var string   $viewUrl
 * @var array<int, array{
 *     id: string,
 *     label: string,
 *     enabled: bool,
 *     url: string,
 *     pageId: string,
 *     restNonce: string,
 *     busyLabel: string,
 *     successLabel: string,
 *     downloadName: string
 * }> $adminControls
 * @var string $switchField
 * @var string $switchNonceField
 * @var string $switchNonceAction
 * @var string $switchConfirmMessage
 * @var bool   $canSwitchToWordPress
 * @var bool   $showEditor
 */

defined('ABSPATH') || exit;
$showEditor = $showEditor ?? true;

?>
<div class="upb-editor-actions" style="margin: 12px 0 4px; display: flex; align-items: center; gap: 8px;">
    <?php if ($showEditor): ?>
        <a href="<?php echo esc_url($viewUrl); ?>" class="button button-primary">
            <?php echo esc_html_x('Open editor', 'Page Builder', 'uncanny-automator'); ?>
        </a>
    <?php endif; ?>

    <?php foreach ($adminControls as $control): ?>
        <button
            type="button"
            class="button"
            data-upb-admin-control-id="<?php echo esc_attr($control['id']); ?>"
            data-upb-admin-control-url="<?php echo esc_url($control['url']); ?>"
            data-upb-page-id="<?php echo esc_attr($control['pageId']); ?>"
            data-upb-rest-nonce="<?php echo esc_attr($control['restNonce']); ?>"
            data-upb-busy-label="<?php echo esc_attr($control['busyLabel']); ?>"
            data-upb-success-label="<?php echo esc_attr($control['successLabel']); ?>"
            data-upb-download-name="<?php echo esc_attr($control['downloadName']); ?>"
            <?php disabled(!$control['enabled']); ?>
        >
            <?php echo esc_html($control['label']); ?>
        </button>
    <?php endforeach; ?>

    <?php if ($canSwitchToWordPress): ?>
        <?php wp_nonce_field($switchNonceAction, $switchNonceField, false); ?>
        <button
            type="submit"
            class="button"
            name="<?php echo esc_attr($switchField); ?>"
            value="1"
            onclick="return window.confirm(<?php echo esc_attr((string) wp_json_encode($switchConfirmMessage)); ?>);"
        >
            <?php echo esc_html_x('Switch to WordPress editor', 'Page Builder', 'uncanny-automator'); ?>
        </button>
    <?php endif; ?>

    <span class="upb-admin-control-status description"></span>
</div>

<?php include __DIR__ . '/static-export-script.php'; ?>
