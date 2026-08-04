<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\WordPress;

use UncannyPageBuilder\Application\GlobalPartDefaultsService;
use UncannyPageBuilder\Domain\GlobalPart\GlobalPartRepositoryInterface;
use UncannyPageBuilder\Domain\GlobalPart\GlobalPartType;
use UncannyPageBuilder\Domain\GlobalPart\PageGlobalPartSelectionRepositoryInterface;
use UncannyPageBuilder\Infrastructure\Persistence\WpPageGlobalPartSelectionRepository;
use UncannyPageBuilder\Domain\GlobalPart\PageGlobalPartResolverInterface;

/**
 * Single source of truth for which global part a page's shell slot renders.
 *
 * CanvasRenderer, ShellModeService, and the static export all resolve
 * through this class so display, context flags, and export cannot drift.
 */
final class WpPageGlobalPartResolver implements PageGlobalPartResolverInterface
{
    public function __construct(
        private readonly GlobalPartRepositoryInterface $globalParts,
        private readonly GlobalPartDefaultsService $defaults,
        private readonly ?PageGlobalPartSelectionRepositoryInterface $pageSelections = null,
    ) {}

    public function resolveForPage(int $pageId, GlobalPartType $type): ?array
    {
        // Per-page override: -1 means "none", >0 means specific part.
        $selection = ($this->pageSelections ?? new WpPageGlobalPartSelectionRepository())->loadForPage($pageId);
        $overrideId = match ($type) {
            GlobalPartType::Header => $selection->headerOverrideId(),
            GlobalPartType::Footer => $selection->footerOverrideId(),
            default => null,
        };

        if ($overrideId === -1) {
            return null;
        }

        if ($overrideId !== null && $overrideId > 0) {
            $override = $this->globalParts->findById($overrideId);
            if ($override !== null && ($override['type'] ?? null) === $type->value) {
                return $override;
            }
        }

        return $this->defaults->resolveForType($type);
    }
}
