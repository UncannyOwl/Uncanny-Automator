<?php
/**
 * Section code editor inline script.
 *
 * @var object $post
 * @var string $sectionRewriteControlId
 */

defined('ABSPATH') || exit;
?>
<script>
(function() {
    document.addEventListener('click', function(event) {
        var target = event.target instanceof Element ? event.target : null;
        var action = target ? target.closest('.upb-section-edit-link') : null;
        if (!action) return;

        var sectionId = parseInt(action.getAttribute('data-section-id') || '', 10);
        var sectionData = window.upbSectionCodeData && window.upbSectionCodeData[sectionId];
        if (typeof window.jQuery === 'function' && typeof window.tb_show === 'function' && sectionData) {
            return;
        }

        event.preventDefault();
        event.stopImmediatePropagation();
        action.setAttribute('aria-disabled', 'true');
        var notice = document.getElementById('upb-section-code-unavailable');
        if (notice) {
            notice.hidden = false;
        }
    }, true);
})();

if (typeof window.jQuery === 'function') {
jQuery(function($) {
    var activeSectionId = 0;
    var cmHtml = null;
    var cmCss  = null;

    function ensureEditors() {
        if (cmHtml) return;
        if (typeof window.upbInitCodeMirror !== 'function') return;
        cmHtml = window.upbInitCodeMirror(document.getElementById('upb-section-code-html'), 'html');
        cmCss  = window.upbInitCodeMirror(document.getElementById('upb-section-code-css'), 'css');
    }

    $(document).on('click', '.upb-section-edit-link', function(e) {
        e.preventDefault();
        activeSectionId = parseInt($(this).data('section-id'), 10);
        var data = upbSectionCodeData[activeSectionId];
        if (!data) return;

        tb_show(
            '<?php echo esc_js(_x('Edit section code', 'Page Builder', 'uncanny-automator')); ?>: ' + data.name,
            '#TB_inline?width=900&height=700&inlineId=upb-section-code-modal'
        );

        // Init CodeMirror after ThickBox makes the container visible.
        setTimeout(function() {
            ensureEditors();
            if (cmHtml) {
                cmHtml.dispatch({ changes: { from: 0, to: cmHtml.state.doc.length, insert: data.html } });
            }
            if (cmCss) {
                cmCss.dispatch({ changes: { from: 0, to: cmCss.state.doc.length, insert: data.css } });
            }
        }, 50);

        $('#upb-section-code-status').text('');
    });

    $(document).on('click', '.upb-section-preview-link', function(e) {
        e.preventDefault();
        var id = parseInt($(this).data('section-id'), 10);
        var data = upbSectionCodeData[id];
        if (!data) return;

        var o = upbPreviewMeta.pluginUrl;
        var srcdoc = '<!DOCTYPE html><html><head><meta charset="utf-8">'
            + '<meta http-equiv="Content-Security-Policy" content="default-src \'none\'; style-src ' + o + ' \'unsafe-inline\'; script-src ' + o + ' \'unsafe-inline\'; img-src ' + o + ' data: https:; font-src ' + o + ' data:;">'
            + '<link rel="stylesheet" href="' + upbPreviewMeta.bootstrapUrl + '">'
            + '<style>:root{' + upbPreviewMeta.tokenCss + '}body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;overflow:hidden}'
            + data.css + '</style></head><body>' + data.html
            + '<script src="' + upbPreviewMeta.lucideUrl + '"><\/script>'
            + '<script src="' + upbPreviewMeta.alpineUrl + '" defer><\/script>'
            + '<script>document.addEventListener("DOMContentLoaded",function(){if(window.lucide)lucide.createIcons()});<\/script>'
            + '</body></html>';

        var $iframe = $('#upb-section-preview-iframe');
        $iframe.attr('srcdoc', srcdoc);
        $iframe.off('load').on('load', function() {
            try { this.style.height = this.contentDocument.documentElement.scrollHeight + 'px'; } catch(e) {}
        });

        tb_show(
            data.name,
            '#TB_inline?width=1200&height=800&inlineId=upb-section-preview-modal'
        );
    });

    $(document).on('click', '.upb-accordion-toggle', function() {
        var $btn = $(this);
        var expanded = $btn.attr('aria-expanded') === 'true';
        $btn.attr('aria-expanded', String(!expanded));
        $('#' + $btn.data('target')).slideToggle(150);
    });

    $('#upb-section-code-save').on('click', function() {
        if (!activeSectionId) return;
        var $btn = $(this);
        var $status = $('#upb-section-code-status');
        var htmlValue = cmHtml ? cmHtml.state.doc.toString() : $('#upb-section-code-html').val();
        var cssValue = cmCss ? cmCss.state.doc.toString() : $('#upb-section-code-css').val();

        $btn.prop('disabled', true);
        $status.text('<?php echo esc_js(_x('Saving...', 'Page Builder', 'uncanny-automator')); ?>').css('color', '#2271b1');
        $.ajax({
            url: upbPageRuntimeMeta.commitUrl,
            method: 'POST',
            headers: { 'X-WP-Nonce': upbPageRuntimeMeta.restNonce },
            contentType: 'application/json',
            data: JSON.stringify({
                page_id: <?php echo (int) $post->ID; ?>,
                value: {
                    base: upbPageRuntimeData.source,
                    design_changes: [],
                    content_changes: [{
                        command_id: 'section.rewrite_source',
                        value: {
                            section_id: activeSectionId,
                            name: upbSectionCodeData[activeSectionId].name,
                            html: htmlValue,
                            css: cssValue
                        }
                    }],
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

                upbSectionCodeData[activeSectionId].html = htmlValue;
                upbSectionCodeData[activeSectionId].css = cssValue;
                upbPageRuntimeData.source = {
                    loaded_source: 'working',
                    working_generation: generation,
                    snapshot_id: null
                };
                $status.text('<?php echo esc_js(_x('Draft saved', 'Page Builder', 'uncanny-automator')); ?>').css('color', '#00a32a');
                $btn.prop('disabled', false);
            },
            error: function(xhr) {
                var msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : '<?php echo esc_js(_x('Save failed', 'Page Builder', 'uncanny-automator')); ?>';
                $status.text(msg).css('color', '#d63638');
                $btn.prop('disabled', false);
            }
        });
    });
});
}
</script>
