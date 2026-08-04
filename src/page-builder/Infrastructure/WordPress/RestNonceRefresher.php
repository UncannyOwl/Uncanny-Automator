<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\WordPress;

use UncannyPageBuilder\Application\Access\GetPageBuilderAllowedCapabilities;

final class RestNonceRefresher
{
    public const ACTION = 'uncanny_page_builder_refresh_rest_nonce';

    public function __construct(
        private readonly GetPageBuilderAllowedCapabilities $allowedCapabilities,
    ) {}

    public function register(): void
    {
        add_action('wp_ajax_' . self::ACTION, [$this, 'refresh']);
    }

    public function refresh(): void
    {
        if (!$this->allowedCapabilities->currentUserHasAllowedCapability()) {
            wp_send_json_error([
                'message' => _x('You do not have permission to edit with Uncanny Page Builder.', 'Page Builder', 'uncanny-automator'),
            ], 403);
        }

        wp_send_json_success([
            'nonce' => wp_create_nonce('wp_rest'),
        ]);
    }
}
