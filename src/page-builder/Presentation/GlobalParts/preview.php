<?php
/**
 * Global part content preview meta box template.
 *
 * @var string $body       Combined section HTML.
 * @var string $previewUrl Canonical preview URL for the reusable canvas.
 */

defined('ABSPATH') || exit;

if ($body === '') : ?>
    <p style="color: #787c82; font-style: italic;">
        <?php echo esc_html_x('No preview available.', 'Page Builder', 'uncanny-automator'); ?>
    </p>
<?php else : ?>
    <iframe src="<?php echo esc_url($previewUrl); ?>"
        style="zoom: 65%; width:100%;border:1px solid #c3c4c7;border-radius:4px;background:#fff;height:320px;"
        scrolling="auto"></iframe>
<?php endif; ?>
