<?php
/**
 * Shared Page Builder source-package upload form.
 *
 * The Pages list and embedded canvas expose different triggers, but both must
 * submit through PageFactory so validation and draft creation stay identical.
 *
 * Inputs: $pageImportFormId, $pageImportFileInputId, $pageImportTarget,
 *         $pageImportReturnContext, $pageImportReturnPageId
 */

namespace UncannyPageBuilder\Infrastructure\WordPress;

defined('ABSPATH') || exit;

$pageImportFormTarget = is_string($pageImportTarget ?? null)
    ? $pageImportTarget
    : '';
$pageImportMaxUploadBytes = function_exists('wp_max_upload_size')
    ? max(0, (int) wp_max_upload_size())
    : 0;
?>
<form
    id="<?php echo esc_attr($pageImportFormId); ?>"
    method="post"
    enctype="multipart/form-data"
    action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
    <?php if ($pageImportFormTarget !== '') : ?>
        target="<?php echo esc_attr($pageImportFormTarget); ?>"
    <?php endif; ?>
    hidden
>
    <input type="hidden" name="action" value="<?php echo esc_attr(PageFactory::IMPORT_ACTION); ?>">
    <?php wp_nonce_field(PageFactory::IMPORT_ACTION); ?>
    <input
        type="hidden"
        name="return_context"
        value="<?php echo esc_attr(is_string($pageImportReturnContext ?? null) ? $pageImportReturnContext : 'pages'); ?>"
    >
    <input
        type="hidden"
        name="return_page_id"
        value="<?php echo esc_attr((string) max(0, (int) ($pageImportReturnPageId ?? 0))); ?>"
    >
    <input
        id="<?php echo esc_attr($pageImportFileInputId); ?>"
        type="file"
        name="source_package"
        accept="application/zip,application/json,.zip,.json"
        <?php if ($pageImportMaxUploadBytes > 0) : ?>
            data-max-upload-bytes="<?php echo esc_attr((string) $pageImportMaxUploadBytes); ?>"
        <?php endif; ?>
    >
</form>
<script>
(function() {
    var input = document.getElementById("<?php echo esc_js($pageImportFileInputId); ?>");
    if (!input) {
        return;
    }

    input.addEventListener('change', function() {
        if (!input.files || input.files.length === 0 || !input.form) {
            return;
        }

        var maxUploadBytes = parseInt(input.getAttribute('data-max-upload-bytes') || '0', 10);
        if (maxUploadBytes > 0 && input.files[0].size > maxUploadBytes) {
            window.alert('<?php echo esc_js(_x("That file is larger than this site's upload limit. Use a smaller export or ask your site administrator to increase the upload limit.", 'Page Builder', 'uncanny-automator')); ?>');
            input.value = '';
            return;
        }

        input.form.submit();
    });
})();
</script>
