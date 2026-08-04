<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\Persistence;

use UncannyPageBuilder\Domain\JavaScriptRuntime\CustomJavaScriptRepositoryInterface;

final class WpPostMetaCustomJavaScriptRepository implements CustomJavaScriptRepositoryInterface
{
    public const META_KEY = '_uncanny_page_builder_custom_javascript';

    public function readForPost(int $postId): string
    {
        if ($postId <= 0) {
            return '';
        }

        $value = get_post_meta($postId, self::META_KEY, true);

        return is_string($value) ? $value : '';
    }

    public function writeForPost(int $postId, string $javascript): void
    {
        update_post_meta($postId, self::META_KEY, $this->slashForWordPress($javascript));

        if ($this->freshReadForPost($postId) !== $javascript) {
            throw new WordPressWriteVerificationException('WordPress could not persist the custom JavaScript source.');
        }
    }

    public function clearForPost(int $postId): void
    {
        delete_post_meta($postId, self::META_KEY);

        if ($this->freshReadForPost($postId) !== '') {
            throw new WordPressWriteVerificationException('WordPress could not clear the custom JavaScript source.');
        }
    }

    /**
     * WordPress metadata writes update object cache independently from the
     * surrounding Page Builder transaction. Evict it before the durability
     * readback so a failed write cannot advance the source generation behind a
     * cached success-shaped value.
     */
    private function freshReadForPost(int $postId): string
    {
        if (\function_exists('wp_cache_delete')) {
            \wp_cache_delete($postId, 'post_meta');
        }

        return $this->readForPost($postId);
    }

    private function slashForWordPress(string $javascript): string
    {
        return \function_exists('wp_slash') ? (string) \wp_slash($javascript) : $javascript;
    }
}
