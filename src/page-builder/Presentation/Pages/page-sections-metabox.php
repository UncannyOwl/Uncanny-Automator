<?php
/**
 * Page sections meta box — section list with core row actions.
 *
 * Preview and Edit code reuse the existing ThickBox + CodeMirror modals; the
 * link classes and data attributes are the contract section-code-script.php
 * binds to.
 *
 * @var \WP_Post $post
 * @var array<int, array{id: string, index: int, name: string}> $sectionRows
 * @var array<int, array{html: string, css: string, name: string}> $sectionCodeData
 * @var array{
 *     enabled: bool,
 *     ownerId: int,
 *     javascript: string,
 *     source: array{loaded_source: string, working_generation: int, snapshot_id: int|null}
 * } $pageRuntimeData
 * @var string $sectionRewriteControlId
 * @var string $bootstrapUrl
 * @var string $tokenCss
 */

defined('ABSPATH') || exit;

$upbInlineJson = static function (mixed $value): string {
    $json = wp_json_encode($value);
    if (!is_string($json)) {
        return 'null';
    }

    return str_replace('</script', '<\/script', $json);
};

?>
<p class="description">
    <?php echo esc_html_x('Preview a section or open the code editor when you need a precise fix.', 'Page Builder', 'uncanny-automator'); ?>
</p>
<div id="upb-section-code-unavailable" class="notice notice-error inline" role="alert" hidden>
    <p><?php echo esc_html_x('Section code editor is unavailable. Reload this page and try again.', 'Page Builder', 'uncanny-automator'); ?></p>
</div>

<?php if ($sectionRows === []): ?>
    <p><?php echo esc_html_x('No generated sections yet.', 'Page Builder', 'uncanny-automator'); ?></p>
<?php else: ?>
    <ul class="upb-page-sections">
        <?php foreach ($sectionRows as $row): ?>
            <li class="upb-page-sections__row">
                <strong>
                    <?php echo esc_html(sprintf('%02d', $row['index'])); ?>
                    —
                    <?php echo esc_html($row['name']); ?>
                </strong>
                <div class="row-actions visible">
                    <span>
                        <a
                            href="#"
                            class="upb-section-preview-link"
                            data-section-id="<?php echo esc_attr($row['id']); ?>"
                        ><?php echo esc_html_x('Preview', 'Page Builder', 'uncanny-automator'); ?></a>
                        |
                    </span>
                    <span>
                        <a
                            href="#"
                            class="upb-section-edit-link"
                            data-section-id="<?php echo esc_attr($row['id']); ?>"
                        ><?php echo esc_html_x('Edit code', 'Page Builder', 'uncanny-automator'); ?></a>
                    </span>
                </div>
            </li>
        <?php endforeach; ?>
    </ul>
    <style>
        .upb-page-sections { margin: 0; }
        .upb-page-sections__row { border-bottom: 1px solid #f0f0f1; padding: 8px 0; margin: 0; }
        .upb-page-sections__row:last-child { border-bottom: 0; }
    </style>
<?php endif; ?>

<?php if ($pageRuntimeData['enabled']): ?>
<div class="upb-page-runtime-row" style="margin-top:12px;">
    <strong><?php echo esc_html_x('Page custom JavaScript', 'Page Builder', 'uncanny-automator'); ?></strong>
    <div class="row-actions visible">
        <span>
            <a href="#" class="upb-page-runtime-edit-link"><?php echo esc_html_x('Edit code', 'Page Builder', 'uncanny-automator'); ?></a>
        </span>
    </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/section-code-modal.php'; ?>
<?php if ($pageRuntimeData['enabled']): ?>
    <?php include __DIR__ . '/page-runtime-modal.php'; ?>
<?php endif; ?>

<div id="upb-section-preview-modal" style="display:none;">
    <div style="padding:0;">
        <iframe id="upb-section-preview-iframe" style="zoom:65%;width:100%;border:0;min-height:400px;" sandbox="allow-same-origin allow-scripts"></iframe>
    </div>
</div>

<script>
var upbSectionCodeData=<?php echo $upbInlineJson($sectionCodeData); ?>;
var upbPreviewMeta={
    bootstrapUrl:<?php echo $upbInlineJson($bootstrapUrl); ?>,
    lucideUrl:<?php echo $upbInlineJson(esc_url(UNCANNY_PB_URL . 'assets/js/lucide.min.js')); ?>,
    alpineUrl:<?php echo $upbInlineJson(esc_url(UNCANNY_PB_URL . 'assets/js/alpine.min.js')); ?>,
    tokenCss:<?php echo $upbInlineJson($tokenCss); ?>,
    pluginUrl:<?php echo $upbInlineJson(esc_url(UNCANNY_PB_URL)); ?>
};
var upbPageRuntimeData=<?php echo $upbInlineJson($pageRuntimeData); ?>;
var upbPageRuntimeMeta={
    commitUrl:<?php echo $upbInlineJson(esc_url_raw(rest_url('uncanny-page-builder/v1/editor/controls/page.manual_changes.commit/invoke'))); ?>,
    restNonce:<?php echo $upbInlineJson(wp_create_nonce('wp_rest')); ?>
};
</script>

<?php include __DIR__ . '/section-code-script.php'; ?>
<?php if ($pageRuntimeData['enabled']): ?>
    <?php include __DIR__ . '/page-runtime-script.php'; ?>
<?php endif; ?>
