<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\WordPress;

/**
 * Reads the slug decision from the registered WordPress save-field contract.
 *
 * A collection query uses different normalization. This field validates and
 * sanitizes a proposed slug without dispatching a save or running save hooks.
 */
final class RestPostSlugValidation
{
    public const FIELD = 'uncanny_page_builder_slug_validation';
    public const PARAMETER = 'uncanny_page_builder_requested_slug';

    public function register(string $postType): void
    {
        register_rest_field($postType, self::FIELD, [
            'get_callback' => fn ($post = null, $field = null, $request = null): ?array => $this->read($post, $request),
            'schema' => [
                'description' => _x('WordPress save validation for a requested slug.', 'Page Builder', 'uncanny-automator'),
                'type' => ['object', 'null'],
                'context' => ['edit'],
                'readonly' => true,
                'properties' => [
                    'requested' => ['type' => 'string'],
                    'normalized' => ['type' => 'string'],
                    'matches' => ['type' => 'boolean'],
                ],
            ],
        ]);
    }

    /** @return array{requested: string, normalized: string, matches: bool}|null */
    public function read(mixed $post, mixed $request): ?array
    {
        try {
            if (!$request instanceof \WP_REST_Request || !is_array($post)) {
                return null;
            }
            $requested = $request->get_param(self::PARAMETER);
            $postId = (int) ($post['id'] ?? 0);
            if (!is_string($requested) || $postId <= 0 || !current_user_can('edit_post', $postId)) {
                return null;
            }

            $route = rest_get_route_for_post($postId);
            if (!is_string($route) || $route === '') {
                return null;
            }

            // Use the actual POST argument, including rest_endpoints filters
            // and custom controllers. Do not duplicate sanitize_title rules.
            foreach (rest_get_server()->get_routes() as $pattern => $handlers) {
                if (!preg_match('@^' . $pattern . '$@i', $route, $matches)) {
                    continue;
                }
                foreach ($handlers as $handler) {
                    if (empty($handler['methods']['POST'])) {
                        continue;
                    }
                    $slugArgument = $handler['args']['slug'] ?? null;
                    if (!is_array($slugArgument)) {
                        return null;
                    }
                    $validation = new \WP_REST_Request('POST', $route);
                    $validation->set_url_params(array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY));
                    $validation->set_query_params(['context' => 'edit']);
                    $validation->set_body_params(['slug' => $requested]);
                    $validation->set_attributes(['args' => ['slug' => $slugArgument]]);
                    if (is_wp_error($validation->has_valid_params()) || is_wp_error($validation->sanitize_params())) {
                        return null;
                    }
                    $normalized = $validation->get_param('slug');
                    if (!is_string($normalized)) {
                        return null;
                    }

                    return [
                        'requested' => $requested,
                        'normalized' => $normalized,
                        'matches' => $normalized === get_post_field('post_name', $postId, 'raw'),
                    ];
                }
            }
        } catch (\Throwable) {
            // Unknown validation must never become permission to write. The
            // caller must stop a pre-check or mark a saved result unverified.
            return null;
        }

        return null;
    }
}
