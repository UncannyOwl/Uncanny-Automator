<?php
/**
 * Global part source code editor meta box template.
 *
 * Renders the canonical source unit of the reusable part.
 *
 * @var \UncannyPageBuilder\Domain\Section\Section $sourceSection
 * @var int    $legacySourceRowCount Stored source rows; more than one is a legacy shape.
 * @var object $post                 WP_Post object.
 * @var bool $runtimeJavaScriptEnabled
 * @var string $runtimeJavaScript    Custom runtime source stored for this reusable part.
 */

defined('ABSPATH') || exit;

$canEditCode = isset($canEditCode) ? (bool) $canEditCode : false;

$html = $sourceSection->content()->html();
$css  = $sourceSection->content()->css();
$name = $sourceSection->name();
$runtimeJavaScriptEnabled = isset($runtimeJavaScriptEnabled) ? (bool) $runtimeJavaScriptEnabled : true;
$javascript = isset($runtimeJavaScript) && is_string($runtimeJavaScript) ? $runtimeJavaScript : '';

if ($canEditCode) {
    wp_nonce_field('upb_save_global_part_code_' . $post->ID, 'upb_gp_code_nonce');
}
?>

<style>
    .upb-accordion { border: 1px solid #c3c4c7; border-radius: 4px; margin-bottom: 8px; }
    .upb-accordion-toggle {
        display: flex; align-items: center; gap: 6px; width: 100%; padding: 10px 12px;
        background: #f6f7f7; border: 0; border-bottom: 1px solid #c3c4c7; cursor: pointer;
        font-size: 13px; font-weight: 600; color: #1d2327; text-align: left;
    }
    .upb-accordion-toggle:hover { background: #f0f0f1; }
    .upb-accordion-toggle[aria-expanded="false"] { border-bottom: 0; }
    .upb-accordion-icon { transition: transform .2s; font-size: 16px; width: 16px; height: 16px; }
    .upb-accordion-toggle[aria-expanded="false"] .upb-accordion-icon { transform: rotate(-90deg); }
    .upb-accordion-panel { padding: 12px; }
</style>

<?php if ($legacySourceRowCount > 1) : ?>
    <div class="notice notice-warning inline" style="margin: 0 0 12px;">
        <p>
            <?php
            printf(
                /* translators: %d: number of stored content rows. */
                esc_html_x('This reusable uses an older format with %d saved content rows. Only the main content is shown here. The other stored rows will be preserved unchanged when you save.', 'Page Builder', 'uncanny-automator'),
                (int) $legacySourceRowCount
            );
            ?>
        </p>
    </div>
<?php endif; ?>

<h4 style="margin: 12px 0 4px;"><?php echo esc_html($name); ?></h4>

<?php if ($canEditCode) : ?>
    <div class="upb-accordion">
        <button type="button" class="upb-accordion-toggle" aria-expanded="true" data-target="upb-gp-acc-html">
            <span class="dashicons dashicons-arrow-down-alt2 upb-accordion-icon"></span>
            HTML
        </button>
        <div id="upb-gp-acc-html" class="upb-accordion-panel">
            <textarea name="upb_gp_html" rows="12" data-upb-lang="html" style="width:100%;font-family:monospace;font-size:12px;tab-size:2;"><?php echo esc_textarea($html); ?></textarea>
        </div>
    </div>
    <div class="upb-accordion">
        <button type="button" class="upb-accordion-toggle" aria-expanded="false" data-target="upb-gp-acc-css">
            <span class="dashicons dashicons-arrow-down-alt2 upb-accordion-icon"></span>
            CSS
        </button>
        <div id="upb-gp-acc-css" class="upb-accordion-panel" style="display:none;">
            <textarea name="upb_gp_css" rows="8" data-upb-lang="css" style="width:100%;font-family:monospace;font-size:12px;tab-size:2;"><?php echo esc_textarea($css); ?></textarea>
        </div>
    </div>
    <?php if ($runtimeJavaScriptEnabled) : ?>
    <div class="upb-accordion">
        <button type="button" class="upb-accordion-toggle" aria-expanded="false" data-target="upb-gp-acc-javascript">
            <span class="dashicons dashicons-arrow-down-alt2 upb-accordion-icon"></span>
            JavaScript
        </button>
        <div id="upb-gp-acc-javascript" class="upb-accordion-panel" style="display:none;">
            <textarea name="upb_gp_javascript" rows="10" data-upb-lang="javascript" style="width:100%;font-family:monospace;font-size:12px;tab-size:2;"><?php echo esc_textarea($javascript); ?></textarea>
        </div>
    </div>
    <?php endif; ?>
    <input type="hidden" name="upb_gp_name" value="<?php echo esc_attr($name); ?>" />
<?php else : ?>
    <div class="upb-accordion">
        <button type="button" class="upb-accordion-toggle" aria-expanded="true" data-target="upb-gp-acc-html">
            <span class="dashicons dashicons-arrow-down-alt2 upb-accordion-icon"></span>
            HTML
        </button>
        <div id="upb-gp-acc-html" class="upb-accordion-panel">
            <pre style="background:#f6f7f7;padding:8px;border:1px solid #ddd;border-radius:3px;font-size:12px;max-height:200px;overflow:auto;white-space:pre-wrap;"><?php echo esc_html($html); ?></pre>
        </div>
    </div>
    <?php if ($css !== '') : ?>
    <div class="upb-accordion">
        <button type="button" class="upb-accordion-toggle" aria-expanded="false" data-target="upb-gp-acc-css">
            <span class="dashicons dashicons-arrow-down-alt2 upb-accordion-icon"></span>
            CSS
        </button>
        <div id="upb-gp-acc-css" class="upb-accordion-panel" style="display:none;">
            <pre style="background:#f6f7f7;padding:8px;border:1px solid #ddd;border-radius:3px;font-size:12px;max-height:150px;overflow:auto;white-space:pre-wrap;"><?php echo esc_html($css); ?></pre>
        </div>
    </div>
    <?php endif; ?>
    <?php if ($javascript !== '') : ?>
    <div class="upb-accordion">
        <button type="button" class="upb-accordion-toggle" aria-expanded="false" data-target="upb-gp-acc-javascript">
            <span class="dashicons dashicons-arrow-down-alt2 upb-accordion-icon"></span>
            JavaScript
        </button>
        <div id="upb-gp-acc-javascript" class="upb-accordion-panel" style="display:none;">
            <pre style="background:#f6f7f7;padding:8px;border:1px solid #ddd;border-radius:3px;font-size:12px;max-height:150px;overflow:auto;white-space:pre-wrap;"><?php echo esc_html($javascript); ?></pre>
        </div>
    </div>
    <?php endif; ?>
<?php endif; ?>

<?php if (!$runtimeJavaScriptEnabled) : ?>
<p class="description" style="margin-top: 8px;">
    <?php echo esc_html_x('Reusable custom JavaScript is disabled in Settings -> JavaScript.', 'Page Builder', 'uncanny-automator'); ?>
</p>
<?php endif; ?>

<?php if (!$canEditCode) : ?>
<p class="description" style="margin-top: 8px;">
    <?php echo esc_html_x('You can view this code, but only administrators can edit it directly.', 'Page Builder', 'uncanny-automator'); ?>
</p>
<?php endif; ?>

<script>
jQuery(function($) {
    $(document).on('click', '.upb-accordion-toggle', function() {
        var $btn = $(this);
        var expanded = $btn.attr('aria-expanded') === 'true';
        $btn.attr('aria-expanded', String(!expanded));
        $('#' + $btn.data('target')).slideToggle(150);
    });
});
</script>
