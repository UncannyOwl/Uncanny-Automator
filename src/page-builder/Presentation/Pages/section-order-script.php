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

    function recordOrder() {
        var ids = [];
        $list.children('li').each(function(index) {
            ids.push($(this).attr('data-section-id'));
            $(this).find('.upb-section-index').text(String(index + 1).padStart(2, '0'));
        });
        $order.val(ids.join(','));
        $changed.val('1');
        $status.text('<?php echo esc_js(_x('Not saved yet. Update the page to save this order.', 'Page Builder', 'uncanny-automator')); ?>');
    }

    // Both input methods stage the same fields. Neither writes before Update.
    $list.on('keydown', '.upb-section-drag-handle', function(event) {
        if (event.key !== 'ArrowUp' && event.key !== 'ArrowDown') return;
        event.preventDefault();
        var $row = $(this).closest('li');
        var $neighbor = event.key === 'ArrowUp' ? $row.prev('li') : $row.next('li');
        if (!$neighbor.length) return;
        if (event.key === 'ArrowUp') $row.insertBefore($neighbor);
        else $row.insertAfter($neighbor);
        this.focus();
        recordOrder();
    });

    if (typeof $list.sortable !== 'function') return;

    $list.sortable({
        axis: 'y',
        handle: '.upb-section-drag-handle',
        // A button is focusable for keyboard reordering. jQuery UI normally
        // excludes buttons, so exclude only controls that belong to code actions.
        cancel: 'a,input,textarea,select,option',
        cursor: 'grabbing',
        placeholder: 'upb-sortable-placeholder',
        forcePlaceholderSize: true,
        update: recordOrder
    });
});
</script>
<style>
    .upb-sortable-placeholder {
        background: #e8f0fe;
        border: 2px dashed #2271b1;
        border-radius: 4px;
    }
    #upb-section-order li:active { cursor: grabbing; }
</style>
