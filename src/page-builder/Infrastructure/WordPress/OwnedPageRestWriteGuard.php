<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\WordPress;

use UncannyPageBuilder\Application\ContentType\SupportsPostTypeUseCase;
use UncannyPageBuilder\Infrastructure\Persistence\DatabaseSectionRepository;

/**
 * Refuses a WordPress REST update that would change a field Page Builder manages.
 *
 * The wp_insert_post_data guard in EditorEnvironmentProvider keeps those fields
 * at their stored values for every writer, but WordPress still answers 200, so
 * a REST client reports a save that did not happen. This guard answers first
 * and names the refused fields. The save guard stays underneath for writers
 * that do not use the WordPress posts controller.
 */
final class OwnedPageRestWriteGuard
{
    public const ERROR_CODE = 'uncanny_page_builder_managed_fields';
    public const UNAVAILABLE_CODE = 'uncanny_page_builder_ownership_unavailable';

    /**
     * REST field => post column. A refusal lists REST fields in this order.
     */
    private const MANAGED_FIELDS = [
        'title'   => 'post_title',
        'content' => 'post_content',
        'slug'    => 'post_name',
        'status'  => 'post_status',
    ];

    public function __construct(
        private readonly DatabaseSectionRepository $sections,
        private readonly SupportsPostTypeUseCase $supportsPostType,
    ) {}

    /** @return list<string> REST field names, in refusal order. */
    public static function managedFields(): array
    {
        return array_keys(self::MANAGED_FIELDS);
    }

    /**
     * The one gate for both guards. Page Builder's own body write, a post type
     * the administrator disabled, and a WordPress-managed post are not protected.
     */
    public function isProtected(int $postId, string $postType): bool
    {
        if (WpOriginalPageContentStore::isWriting() || $postId <= 0) {
            return false;
        }

        if ($postType === '' && function_exists('get_post_type')) {
            $resolvedPostType = get_post_type($postId);
            $postType = is_string($resolvedPostType) ? $resolvedPostType : '';
        }

        return $this->supportsPostType->isEnabledByAdministrator($postType)
            && $this->sections->isOwnedPage($postId);
    }

    /**
     * Filter for rest_pre_insert_{post_type}. The prepared post holds only the
     * fields the request sent, still unslashed. WordPress returns a WP_Error
     * from this filter before it writes, so a refused request saves no field.
     *
     * A check that fails refuses the request. Passing the request on would save
     * a write that nothing validated.
     */
    public function refuseManagedFieldChanges(mixed $preparedPost): mixed
    {
        try {
            return $this->refusalOrRequest($preparedPost);
        } catch (\Throwable $failure) {
            error_log(sprintf(
                '[Uncanny Page Builder] WordPress REST write guard failed (%s).',
                $failure::class,
            ));

            return new \WP_Error(
                self::UNAVAILABLE_CODE,
                _x(
                    'Nothing was saved. Uncanny Page Builder could not confirm whether it manages this page. Try again.',
                    'Page Builder',
                    'uncanny-automator',
                ),
                [
                    'status'    => 503,
                    'retryable' => true,
                ],
            );
        }
    }

    private function refusalOrRequest(mixed $preparedPost): mixed
    {
        if (!$preparedPost instanceof \stdClass) {
            return $preparedPost;
        }

        $postId = (int) ($preparedPost->ID ?? 0);
        if (!$this->isProtected($postId, (string) ($preparedPost->post_type ?? ''))) {
            return $preparedPost;
        }

        $refused = [];
        foreach (self::MANAGED_FIELDS as $restField => $column) {
            if (isset($preparedPost->{$column}) && $this->changesStoredValue($postId, $column, (string) $preparedPost->{$column})) {
                $refused[] = $restField;
            }
        }
        if ($refused === []) {
            return $preparedPost;
        }

        return new \WP_Error(
            self::ERROR_CODE,
            _x(
                'Nothing was saved. Uncanny Page Builder manages the title, content, slug, and status of this page. Make this change in Uncanny Page Builder instead of WordPress.',
                'Page Builder',
                'uncanny-automator',
            ),
            [
                'status' => 409,
                'fields' => $refused,
            ],
        );
    }

    private function changesStoredValue(int $postId, string $column, string $requested): bool
    {
        $stored = get_post_field($column, $postId, 'raw');
        // The save guard cannot restore a value it cannot read; leave that case to it.
        if (!is_string($stored) || $stored === $requested) {
            return false;
        }

        // WordPress owns trash and restore, as it does in the save guard.
        return $column !== 'post_status' || ($stored !== 'trash' && $requested !== 'trash');
    }
}
