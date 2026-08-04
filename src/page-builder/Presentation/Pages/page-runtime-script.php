<?php
/**
 * Page custom JavaScript editor inline script.
 */

defined('ABSPATH') || exit;
?>
<script>
jQuery(function($) {
    var cmRuntime = null;

    // Section: Editor lifecycle
    function ensureRuntimeEditor() {
        if (cmRuntime) return;
        if (typeof window.upbInitCodeMirror !== 'function') return;

        cmRuntime = window.upbInitCodeMirror(
            document.getElementById('upb-page-runtime-javascript'),
            'javascript'
        );
    }

    $(document).on('click', '.upb-page-runtime-edit-link', function(e) {
        e.preventDefault();

        tb_show(
            '<?php echo esc_js(_x('Edit page JavaScript', 'Page Builder', 'uncanny-automator')); ?>',
            '#TB_inline?width=900&height=700&inlineId=upb-page-runtime-modal'
        );

        setTimeout(function() {
            ensureRuntimeEditor();
            if (cmRuntime) {
                cmRuntime.dispatch({
                    changes: {
                        from: 0,
                        to: cmRuntime.state.doc.length,
                        insert: upbPageRuntimeData.javascript
                    }
                });
            }
        }, 50);

        $('#upb-page-runtime-status').text('');
    });

    // Section: Save path
    $('#upb-page-runtime-save').on('click', function() {
        var $btn = $(this);
        var $status = $('#upb-page-runtime-status');
        var javascriptValue = cmRuntime
            ? cmRuntime.state.doc.toString()
            : $('#upb-page-runtime-javascript').val();
        $btn.prop('disabled', true);
        $status.text('<?php echo esc_js(_x('Saving...', 'Page Builder', 'uncanny-automator')); ?>').css('color', '#2271b1');

        $.ajax({
            url: upbPageRuntimeMeta.commitUrl,
            method: 'POST',
            headers: { 'X-WP-Nonce': upbPageRuntimeMeta.restNonce },
            contentType: 'application/json',
            data: JSON.stringify({
                page_id: upbPageRuntimeData.ownerId,
                value: {
                    base: upbPageRuntimeData.source,
                    design_changes: [],
                    content_changes: [],
                    custom_javascript: javascriptValue,
                    draft_resume_policy: 'parked'
                }
            }),
            success: function(resp) {
                var result = resp && resp.data ? resp.data : {};
                var generation = parseInt(result.working_generation, 10);
                if (!Number.isInteger(generation) || generation < 0) {
                    $status.text('<?php echo esc_js(_x('Save failed', 'Page Builder', 'uncanny-automator')); ?>').css('color', '#d63638');
                    $btn.prop('disabled', false);
                    return;
                }

                upbPageRuntimeData.javascript = javascriptValue;
                upbPageRuntimeData.source = {
                    loaded_source: 'working',
                    working_generation: generation,
                    snapshot_id: null
                };
                $status
                    .text('<?php echo esc_js(_x('Draft saved', 'Page Builder', 'uncanny-automator')); ?>')
                    .css('color', '#00a32a');
                $btn.prop('disabled', false);
            },
            error: function(xhr) {
                var msg = '<?php echo esc_js(_x('Save failed', 'Page Builder', 'uncanny-automator')); ?>';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    msg = xhr.responseJSON.message;
                } else if (typeof xhr.responseText === 'string' && xhr.responseText.trim() !== '') {
                    msg = xhr.responseText.split('\n')[0];
                }

                $status.text(msg).css('color', '#d63638');
                $btn.prop('disabled', false);
            }
        });
    });
});
</script>
