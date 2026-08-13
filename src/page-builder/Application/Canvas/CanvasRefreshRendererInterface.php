<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\Canvas;

/**
 * Builds the rendered projection used by an in-place canvas refresh.
 */
interface CanvasRefreshRendererInterface
{
    /**
     * Run one refresh projection in the host render state of its owner.
     *
     * Dynamic bindings can depend on owner-specific state that is absent from
     * an API request. The adapter must establish that state for the complete
     * page, header, and footer projection.
     *
     * @template T
     * @param callable(): T $projection
     * @return T
     */
    public function withOwnerRenderContext(int $ownerId, callable $projection): mixed;

    /**
     * Keep editable source separate from rendered HTML. Dynamic bindings can
     * replace or repeat authored nodes, so rendered output cannot become the
     * source used by later Manual editor writes.
     *
     * @param array<int, array<string, mixed>> $sections
     * @return list<array{id: int, html: string}>
     */
    public function renderSections(array $sections, int $ownerId): array;

    /**
     * Report whether the current working projection has runtime JavaScript.
     *
     * @param array<string, mixed>|null $header
     * @param array<string, mixed>|null $footer
     */
    public function hasCurrentJavaScript(int $ownerId, ?array $header = null, ?array $footer = null): bool;

    /**
     * Report whether one immutable page-source projection has runtime JavaScript.
     *
     * @param array<string, mixed>|null $header
     * @param array<string, mixed>|null $footer
     */
    public function hasPageSourceJavaScript(
        int $pageId,
        string $javaScript,
        ?array $header = null,
        ?array $footer = null,
    ): bool;
}
