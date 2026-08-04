<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\Canvas;

interface CanvasGlobalPartsProviderInterface
{
    /**
     * @return array{
     *     header: array{post_id: int, type: string, html: string, css: string}|null,
     *     footer: array{post_id: int, type: string, html: string, css: string}|null
     * }
     */
    public function forPage(int $pageId): array;

    /**
     * Resolve current reusable content through page-owned selections captured
     * in an immutable published source snapshot.
     *
     * @param array<string, mixed> $source
     * @return array{
     *     header: array{post_id: int, type: string, html: string, css: string}|null,
     *     footer: array{post_id: int, type: string, html: string, css: string}|null
     * }
     */
    public function forPageSource(int $pageId, array $source): array;
}
