<?php
/**
 * Shared color token behavior script.
 *
 * @var string $containerId
 * @var string $resetConfirmMessage
 */

defined('ABSPATH') || exit;

$resetConfirmMessage = $resetConfirmMessage ?? _x('Restore the default brand colors?', 'Page Builder', 'uncanny-automator');

?>
<script>
    jQuery(function ($) {
        $('.upb-color-control').wpColorPicker();

        var root = document.getElementById('<?php echo esc_js($containerId); ?>');
        if (!root) {
            return;
        }

        $(root).find('[data-upb-colors-reset="true"]').on('click', function () {
            if (!window.confirm('<?php echo esc_js($resetConfirmMessage); ?>')) {
                return;
            }

            $(root).find('.upb-color-control').each(function () {
                var nextValue = $(this).attr('data-default-value') || '';
                $(this).wpColorPicker('color', nextValue);
                $(this).val(nextValue);
            });
        });
    });
</script>
