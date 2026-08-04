<?php
/**
 * Section code editor modal (ThickBox inline content).
 * Uses native WordPress postbox accordion pattern.
 */

defined('ABSPATH') || exit;
?>
<div id="upb-section-code-modal" style="display:none;">
    <div style="padding: 16px;">
        <div style="background: #fff3cd; border: 1px solid #ffc107; color: #856404; padding: 10px 14px; border-radius: 4px; margin-bottom: 12px; font-size: 13px;">
            <strong><?php echo esc_html_x('Caution:', 'Page Builder', 'uncanny-automator'); ?></strong>
            <?php echo esc_html_x('Editing code directly can break this section, including dynamic content and editable elements.', 'Page Builder', 'uncanny-automator'); ?>
        </div>

        <div style="margin-bottom:12px;display:flex;align-items:center;gap:10px;">
            <button type="button" class="button button-primary" id="upb-section-code-save"><?php echo esc_html_x('Save draft', 'Page Builder', 'uncanny-automator'); ?></button>
            <span id="upb-section-code-status" style="font-size:13px;"></span>
        </div>

        <div class="upb-accordion">
            <button type="button" class="upb-accordion-toggle" aria-expanded="true" data-target="upb-acc-html">
                <span class="dashicons dashicons-arrow-down-alt2 upb-accordion-icon"></span>
                HTML
            </button>
            <div id="upb-acc-html" class="upb-accordion-panel">
                <textarea id="upb-section-code-html" rows="12" style="width:100%;font-family:monospace;font-size:12px;tab-size:2;"></textarea>
            </div>
        </div>

        <div class="upb-accordion">
            <button type="button" class="upb-accordion-toggle" aria-expanded="false" data-target="upb-acc-css">
                <span class="dashicons dashicons-arrow-down-alt2 upb-accordion-icon"></span>
                CSS
            </button>
            <div id="upb-acc-css" class="upb-accordion-panel" style="display:none;">
                <textarea id="upb-section-code-css" rows="8" style="width:100%;font-family:monospace;font-size:12px;tab-size:2;"></textarea>
            </div>
        </div>
    </div>
</div>
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
