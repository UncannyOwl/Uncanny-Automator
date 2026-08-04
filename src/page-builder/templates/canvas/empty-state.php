<?php
/**
 * Empty canvas state.
 *
 * The "start building" invitation shown when an editor opens a page or
 * reusable with no sections yet.
 *
 * Inputs: $emptyCanvasHeading, $emptyCanvasBody, $emptyCanvasActionKind,
 *         $emptyCanvasActionLabel, $emptyCanvasActionUrl
 */

defined('ABSPATH') || exit;
?>
<div
    class="upb-empty-canvas-state"
    data-upb-editor-empty-state="true"
    data-upb-empty-state-action-kind="<?php echo esc_attr($emptyCanvasActionKind); ?>"
    data-upb-empty-state-action-label="<?php echo esc_attr($emptyCanvasActionLabel); ?>"
    data-upb-empty-state-action-url="<?php echo esc_url($emptyCanvasActionUrl); ?>"
    data-upb-empty-state-body="<?php echo esc_attr($emptyCanvasBody); ?>"
    data-upb-empty-state-heading="<?php echo esc_attr($emptyCanvasHeading); ?>"
    data-upb-empty-state-mascot-url="<?php echo esc_url(UNCANNY_PB_URL . 'assets/images/uncanny-automator-mascot.svg'); ?>"
    aria-live="polite"
>
    <div class="upb-empty-canvas-state__inner">
        <div data-upb-empty-state-root="true"></div>
        <div class="upb-empty-canvas-state__actions" data-upb-empty-state-actions="true"></div>
    </div>
</div>
