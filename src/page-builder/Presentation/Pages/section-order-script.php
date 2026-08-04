<?php
/**
 * Section order sortable script + styles.
 */

defined('ABSPATH') || exit;
?>
<script>
jQuery(function($) {
    var $list = $('#upb-section-order');
    var $status = $('#upb-section-order-status');
    var $order = $('#upb-section-order-ids');
    var $changed = $('#upb-section-order-changed');

    $list.sortable({
        axis: 'y',
        cursor: 'grabbing',
        placeholder: 'upb-sortable-placeholder',
        update: function() {
            var ids = [];
            $list.find('li').each(function() {
                var id = parseInt($(this).data('section-id'), 10);
                ids.push(id);
            });

            $order.val(ids.join(','));
            $changed.val('1');
            $status.html('<span style="color: #996800;"><?php echo esc_js(_x('Not saved yet. Update the page to save this order.', 'Page Builder', 'uncanny-automator')); ?></span>');
        }
    });
});
</script>
<style>
    .upb-sortable-placeholder {
        height: 36px;
        margin: 4px 0;
        background: #e8f0fe;
        border: 2px dashed #2271b1;
        border-radius: 4px;
    }
    #upb-section-order li:active { cursor: grabbing; }
</style>
