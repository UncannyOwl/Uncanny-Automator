<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\WordPress;

/**
 * One-time admin notices for source-package imports.
 */
final class AdminImportNoticeStore
{
    public const QUERY_ARG = 'upb_import_notice';

    /**
     * @param 'error'|'success' $status
     */
    public static function remember(
        string $screen,
        string $status,
        string $message,
        string $linkUrl = '',
        string $linkLabel = '',
    ): void {
        set_transient(self::key($screen), [
            'status' => $status,
            'message' => $message,
            'link_url' => $linkUrl,
            'link_label' => $linkLabel,
        ], 60);
    }

    public static function url(string $url, string $screen): string
    {
        return add_query_arg(self::QUERY_ARG, rawurlencode($screen), $url);
    }

    public static function render(string $screen): void
    {
        $requestedScreen = is_string($_GET[self::QUERY_ARG] ?? null)
            ? sanitize_key(wp_unslash($_GET[self::QUERY_ARG]))
            : '';
        if ($requestedScreen !== $screen) {
            return;
        }

        $notice = get_transient(self::key($screen));
        delete_transient(self::key($screen));
        if (!is_array($notice)) {
            return;
        }

        $status = ($notice['status'] ?? '') === 'success' ? 'success' : 'error';
        $message = is_string($notice['message'] ?? null) ? $notice['message'] : '';
        $linkUrl = is_string($notice['link_url'] ?? null) ? $notice['link_url'] : '';
        $linkLabel = is_string($notice['link_label'] ?? null) ? $notice['link_label'] : '';

        if ($message === '') {
            return;
        }

        echo '<div class="notice notice-' . esc_attr($status) . ' is-dismissible"><p>';
        echo esc_html($message);
        if ($linkUrl !== '' && $linkLabel !== '') {
            echo ' <a href="' . esc_url($linkUrl) . '">' . esc_html($linkLabel) . '</a>';
        }
        echo '</p></div>';
    }

    private static function key(string $screen): string
    {
        // Scope by user so simultaneous admin imports cannot leak notices
        // between accounts before the redirect consumes the transient.
        return 'upb_import_notice_' . sanitize_key($screen) . '_' . get_current_user_id();
    }
}
