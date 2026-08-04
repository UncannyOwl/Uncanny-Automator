<?php
/**
 * Page custom JavaScript editor modal (ThickBox inline content).
 *
 * Uses the same wp-admin accordion/editor idiom as the section source editor,
 * but writes to the page runtime lane instead of a section source row.
 */

defined('ABSPATH') || exit;
?>
<div id="upb-page-runtime-modal" style="display:none;">
    <div style="padding: 16px;">
        <div style="background: #fff3cd; border: 1px solid #ffc107; color: #856404; padding: 10px 14px; border-radius: 4px; margin-bottom: 12px; font-size: 13px;">
            <strong><?php echo esc_html_x('Caution:', 'Page Builder', 'uncanny-automator'); ?></strong>
            <?php echo esc_html_x('Page JavaScript runs after this draft is published. Prefer classes or data attributes for hooks because section IDs are system-owned.', 'Page Builder', 'uncanny-automator'); ?>
        </div>

        <div style="margin-bottom:12px;display:flex;align-items:center;gap:10px;">
            <button type="button" class="button button-primary" id="upb-page-runtime-save"><?php echo esc_html_x('Save draft', 'Page Builder', 'uncanny-automator'); ?></button>
            <span id="upb-page-runtime-status" style="font-size:13px;"></span>
        </div>

        <div class="upb-accordion">
            <button type="button" class="upb-accordion-toggle" aria-expanded="true" data-target="upb-page-runtime-acc-javascript">
                <span class="dashicons dashicons-arrow-down-alt2 upb-accordion-icon"></span>
                JavaScript
            </button>
            <div id="upb-page-runtime-acc-javascript" class="upb-accordion-panel">
                <textarea id="upb-page-runtime-javascript" rows="12" style="width:100%;font-family:monospace;font-size:12px;tab-size:2;"></textarea>
            </div>
        </div>
    </div>
</div>
