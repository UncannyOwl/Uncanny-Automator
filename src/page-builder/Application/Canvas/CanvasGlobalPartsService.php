<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\Canvas;

use UncannyPageBuilder\Application\GlobalPartDefaultsResolverInterface;
use UncannyPageBuilder\Application\ShellModeService;
use UncannyPageBuilder\Domain\GlobalPart\GlobalPartRepositoryInterface;
use UncannyPageBuilder\Domain\GlobalPart\GlobalPartType;
use UncannyPageBuilder\Domain\GlobalPart\PageGlobalPartResolverInterface;
use UncannyPageBuilder\Domain\Shell\ShellMode;

/**
 * Builds the global-part projection returned by dynamic canvas refreshes.
 */
final class CanvasGlobalPartsService implements CanvasGlobalPartsProviderInterface
{
    public function __construct(
        private readonly ShellModeService $shellModes,
        private readonly PageGlobalPartResolverInterface $parts,
        private readonly CanvasGlobalPartRendererInterface $renderer,
        private readonly GlobalPartRepositoryInterface $globalParts,
        private readonly GlobalPartDefaultsResolverInterface $defaults,
    ) {}

    public function forPage(int $pageId): array
    {
        if ($this->shellModes->resolveForPage($pageId)->mode !== ShellMode::UncannyNative) {
            return ['header' => null, 'footer' => null];
        }

        return [
            'header' => $this->render($pageId, GlobalPartType::Header),
            'footer' => $this->render($pageId, GlobalPartType::Footer),
        ];
    }

    public function headerForPage(int $pageId): ?array
    {
        return $this->partForPage($pageId, GlobalPartType::Header);
    }

    public function footerForPage(int $pageId): ?array
    {
        return $this->partForPage($pageId, GlobalPartType::Footer);
    }

    public function forPageSource(int $pageId, array $source): array
    {
        $shellMode = ShellMode::tryFrom((string) ($source['shell_mode'] ?? ''));
        if ($shellMode !== ShellMode::UncannyNative) {
            return ['header' => null, 'footer' => null];
        }

        return [
            'header' => $this->renderSourcePart(
                GlobalPartType::Header,
                $source['header_override_id'] ?? null,
            ),
            'footer' => $this->renderSourcePart(
                GlobalPartType::Footer,
                $source['footer_override_id'] ?? null,
            ),
        ];
    }

    public function headerForPageSource(int $pageId, array $source): ?array
    {
        return $this->partForPageSource($source, GlobalPartType::Header);
    }

    public function footerForPageSource(int $pageId, array $source): ?array
    {
        return $this->partForPageSource($source, GlobalPartType::Footer);
    }

    /**
     * @return array{post_id: int, type: string, html: string, css: string}|null
     */
    private function partForPage(int $pageId, GlobalPartType $type): ?array
    {
        if ($this->shellModes->resolveForPage($pageId)->mode !== ShellMode::UncannyNative) {
            return null;
        }

        return $this->render($pageId, $type);
    }

    /**
     * @param array<string, mixed> $source
     * @return array{post_id: int, type: string, html: string, css: string}|null
     */
    private function partForPageSource(array $source, GlobalPartType $type): ?array
    {
        if (ShellMode::tryFrom((string) ($source['shell_mode'] ?? '')) !== ShellMode::UncannyNative) {
            return null;
        }

        $overrideKey = $type === GlobalPartType::Header
            ? 'header_override_id'
            : 'footer_override_id';

        return $this->renderSourcePart($type, $source[$overrideKey] ?? null);
    }

    /**
     * @return array{post_id: int, type: string, html: string, css: string}|null
     */
    private function render(int $pageId, GlobalPartType $type): ?array
    {
        $part = $this->parts->resolveForPage($pageId, $type);

        return is_array($part) ? $this->renderer->renderGlobalPartSnapshot($part) : null;
    }

    /**
     * Shared reusable content stays current; only the page-owned selection is
     * restored from the snapshot.
     *
     * @return array{post_id: int, type: string, html: string, css: string}|null
     */
    private function renderSourcePart(GlobalPartType $type, mixed $overrideId): ?array
    {
        if (is_int($overrideId) && $overrideId === -1) {
            return null;
        }
        if (is_int($overrideId) && $overrideId > 0) {
            $part = $this->globalParts->findById($overrideId);

            return is_array($part) && ($part['type'] ?? null) === $type->value
                ? $this->renderer->renderGlobalPartSnapshot($part)
                : null;
        }

        $part = $this->defaults->resolveForType($type);

        return is_array($part) ? $this->renderer->renderGlobalPartSnapshot($part) : null;
    }
}
