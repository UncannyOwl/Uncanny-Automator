<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\WordPress;

/**
 * Tells a WordPress REST reader that Page Builder manages a post.
 *
 * A REST client cannot see Page Builder ownership, so it learns it from a
 * refused write. This read-only field states it on every edit-context read,
 * from the same gate the write guard uses, and lists the fields that guard
 * refuses. Null means the answer is unknown, never that the post is unmanaged.
 */
final class OwnedPageRestField
{
    public const NAME = 'uncanny_page_builder';

    public function __construct(
        private readonly OwnedPageRestWriteGuard $guard,
    ) {}

    public function register(string $postType): void
    {
        register_rest_field($postType, self::NAME, [
            'get_callback' => fn ($post = null): ?array => $this->read($post),
            'schema'       => [
                'description' => _x('How Uncanny Page Builder manages this post.', 'Page Builder', 'uncanny-automator'),
                'type'        => ['object', 'null'],
                'context'     => ['edit'],
                'readonly'    => true,
                'properties'  => [
                    'managed'        => ['type' => 'boolean'],
                    'managed_fields' => ['type' => 'array', 'items' => ['type' => 'string']],
                ],
            ],
        ]);
    }

    /**
     * @param mixed $post The prepared REST item. A _fields request can leave the ID out.
     * @return array{managed: bool, managed_fields: list<string>}|null
     */
    public function read(mixed $post): ?array
    {
        try {
            $postId = is_array($post) ? (int) ($post['id'] ?? 0) : 0;
            if ($postId <= 0) {
                return null;
            }

            $managed = $this->guard->isProtected($postId, (string) ($post['type'] ?? ''));

            return [
                'managed'        => $managed,
                'managed_fields' => $managed ? OwnedPageRestWriteGuard::managedFields() : [],
            ];
        } catch (\Throwable) {
            return null;
        }
    }
}
