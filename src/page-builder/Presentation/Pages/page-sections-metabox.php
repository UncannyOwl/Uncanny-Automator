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
 * @var bool $sectionOrderEnabled
 * @var int $workingGeneration
 */

defined('ABSPATH') || exit;

$sectionOrderEnabled = $sectionOrderEnabled ?? false;

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
    <?php if ($sectionOrderEnabled): ?>
        <?php include __DIR__ . '/section-order.php'; ?>
    <?php endif; ?>
    <ul class="upb-page-sections"<?php if ($sectionOrderEnabled): ?> id="upb-section-order"<?php endif; ?>>
        <?php foreach ($sectionRows as $row): ?>
            <li class="upb-page-sections__row" data-section-id="<?php echo esc_attr($row['id']); ?>">
                <?php if ($sectionOrderEnabled): ?>
                    <button type="button" class="upb-section-drag-handle" aria-describedby="upb-section-order-help" aria-label="<?php
                        /* translators: %s: Section name. */
                        echo esc_attr(sprintf(_x('Move %s', 'Page Builder', 'uncanny-automator'), $row['name']));
                    ?>"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" aria-hidden="true" focusable="false" style="fill: none" stroke="currentColor" stroke-width="1.5">
                        <path d="M9.25 5.75H8.75V6.25H9.25V5.75Z" fill="currentColor" stroke="none" />
                        <path d="M9.25 11.75H8.75V12.25H9.25V11.75Z" fill="currentColor" stroke="none" />
                        <path d="M9.25 17.75H8.75V18.25H9.25V17.75Z" fill="currentColor" stroke="none" />
                        <path d="M15.25 5.75H14.75V6.25H15.25V5.75Z" fill="currentColor" stroke="none" />
                        <path d="M15.25 11.75H14.75V12.25H15.25V11.75Z" fill="currentColor" stroke="none" />
                        <path d="M15.25 17.75H14.75V18.25H15.25V17.75Z" fill="currentColor" stroke="none" />
                        <path d="M9.25 5.75H8.75V6.25H9.25V5.75Z" vector-effect="non-scaling-stroke" />
                        <path d="M9.25 11.75H8.75V12.25H9.25V11.75Z" vector-effect="non-scaling-stroke" />
                        <path d="M9.25 17.75H8.75V18.25H9.25V17.75Z" vector-effect="non-scaling-stroke" />
                        <path d="M15.25 5.75H14.75V6.25H15.25V5.75Z" vector-effect="non-scaling-stroke" />
                        <path d="M15.25 11.75H14.75V12.25H15.25V11.75Z" vector-effect="non-scaling-stroke" />
                        <path d="M15.25 17.75H14.75V18.25H15.25V17.75Z" vector-effect="non-scaling-stroke" />
                    </svg></button>
                <?php endif; ?>
                <div>
                    <strong>
                        <span class="upb-section-index"><?php echo esc_html(sprintf('%02d', $row['index'])); ?></span>
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
                </div>
            </li>
        <?php endforeach; ?>
    </ul>
    <?php if ($sectionOrderEnabled): ?>
        <p id="upb-section-order-help" class="description"><?php echo esc_html_x('Drag sections to reorder, or use the arrow keys on a move button. Changes save when you update this page.', 'Page Builder', 'uncanny-automator'); ?></p>
        <p id="upb-section-order-status" class="description" role="status" aria-live="polite"></p>
    <?php endif; ?>
    <style>
        .upb-page-sections { margin: 0; }
        .upb-page-sections__row { display: flex; align-items: center; gap: 12px; border-bottom: 1px solid #f0f0f1; padding: 8px 0; margin: 0; background: #fff; }
        .upb-page-sections__row:last-child { border-bottom: 0; }
        /* Gutenberg uses a 24px drag icon and a 24px-wide toolbar handle. */
        .upb-section-drag-handle { display: flex; align-items: center; justify-content: center; flex: 0 0 24px; width: 24px; height: 40px; padding: 0; border: 0; border-radius: 2px; background: transparent; color: #1e1e1e; cursor: grab; }
        .upb-section-drag-handle svg { display: block; flex: 0 0 24px; }
        .upb-section-drag-handle:hover { background: #f0f0f0; }
        .upb-section-drag-handle:focus-visible { outline: 2px solid var(--wp-admin-theme-color, #3858e9); outline-offset: -2px; }
        .upb-section-drag-handle:active { cursor: grabbing; }
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
<?php if ($sectionOrderEnabled && count($sectionRows) > 1): ?>
    <?php include __DIR__ . '/section-order-script.php'; ?>
<?php endif; ?>
<?php if ($pageRuntimeData['enabled']): ?>
    <?php include __DIR__ . '/page-runtime-script.php'; ?>
<?php endif; ?>
