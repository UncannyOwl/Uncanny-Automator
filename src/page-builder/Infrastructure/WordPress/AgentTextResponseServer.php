<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\WordPress;

/**
 * Serves Agent tool responses that deliberately use a plain-text body.
 *
 * WordPress installs its REST CORS callback at priority 10. This adapter runs
 * afterwards so the response body cannot make those headers fail with
 * "headers already sent" warnings.
 */
final class AgentTextResponseServer
{
    public const FILTER_PRIORITY = 11;

    public function register(): void
    {
        add_filter('rest_pre_serve_request', [$this, 'serve'], self::FILTER_PRIORITY, 2);
    }

    public function serve(bool $served, \WP_HTTP_Response $result): bool
    {
        if ($served) {
            return true;
        }

        $data = $result->get_data();
        if (!is_string($data) || !($result->get_headers()['X-UE-Text-Response'] ?? null)) {
            return false;
        }

        header('Content-Type: text/plain; charset=utf-8');
        echo $data;

        return true;
    }
}
