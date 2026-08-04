<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\JavaScriptRuntime;

/**
 * Durable storage for Page Builder-owned custom JavaScript source.
 *
 * Pages and global parts both persist against a post-like owner id. The
 * repository does not decide ownership semantics; it only stores and loads the
 * raw source string for one owner.
 */
interface CustomJavaScriptRepositoryInterface
{
    public function readForPost(int $postId): string;

    public function writeForPost(int $postId, string $javascript): void;

    public function clearForPost(int $postId): void;
}
