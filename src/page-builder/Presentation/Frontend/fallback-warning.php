<?php
/**
 * Admin-only warning when theme_composition page didn't render sections.
 */

defined('ABSPATH') || exit;
?>
<div style="position:fixed;bottom:20px;left:20px;right:20px;z-index:99999;background:#fff3cd;border:1px solid #ffc107;color:#856404;padding:12px 16px;border-radius:6px;font-size:14px;box-shadow:0 2px 8px rgba(0,0,0,0.1);">
    <?php echo esc_html_x('Uncanny Page Builder sections could not render because this page template does not use the standard content area. Switch this page to "Build the full page with Uncanny Page Builder" or use a different page template.', 'Page Builder', 'uncanny-automator'); ?>
</div>
