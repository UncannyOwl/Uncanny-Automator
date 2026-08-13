<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\Canvas;

interface CanvasGlobalPartsProviderInterface
{
    /** @return array{post_id: int, type: string, html: string, css: string}|null */
    public function headerForPage(int $pageId): ?array;

    /** @return array{post_id: int, type: string, html: string, css: string}|null */
    public function footerForPage(int $pageId): ?array;

    /**
     * @param array<string, mixed> $source
     * @return array{post_id: int, type: string, html: string, css: string}|null
     */
    public function headerForPageSource(int $pageId, array $source): ?array;

    /**
     * @param array<string, mixed> $source
     * @return array{post_id: int, type: string, html: string, css: string}|null
     */
    public function footerForPageSource(int $pageId, array $source): ?array;
}
