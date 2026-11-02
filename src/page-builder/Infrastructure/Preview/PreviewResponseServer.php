<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\Preview;

final class PreviewResponseServer
{
    public function register(): void
    {
        add_filter('rest_pre_serve_request', [$this, 'serve'], 12, 2);
    }

    public function serve($served = null, $result = null): bool
    {
        if ($served) {
            return true;
        }
        if (
            !$result instanceof \WP_HTTP_Response
            || ($result->get_headers()['X-UPB-Preview'] ?? null) !== '1'
            || !is_string($result->get_data())
        ) {
            return false;
        }
        try {
            // Cookie-authenticated visitors get the same anonymous snapshot.
            header_remove('X-WP-Nonce');
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Stored standalone preview HTML snapshot built by the Page Builder renderer; it is served under a sandboxing Content-Security-Policy and escaping would show it as text.
            echo $result->get_data();
            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
