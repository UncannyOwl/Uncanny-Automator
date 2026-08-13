<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application;

use UncannyPageBuilder\Application\Concurrency\PageSourceMutation;
use UncannyPageBuilder\Application\Observability\FailureReporterInterface;
use UncannyPageBuilder\Application\Publishing\WorkingCanvasRefreshScheduler;
use UncannyPageBuilder\Domain\Concurrency\SourceGenerationStoreInterface;
use UncannyPageBuilder\Domain\GlobalPart\GlobalPartRepositoryInterface;
use UncannyPageBuilder\Domain\GlobalPart\GlobalPartType;
use UncannyPageBuilder\Domain\GlobalPart\PageGlobalPartResolverInterface;
use UncannyPageBuilder\Domain\Shell\ShellMode;
use UncannyPageBuilder\Domain\Shell\ShellModeContext;
use UncannyPageBuilder\Domain\Shell\ShellModeRepositoryInterface;
use UncannyPageBuilder\Domain\Shell\ShellProvider;
use UncannyPageBuilder\Domain\Shell\ShellSignals;
use UncannyPageBuilder\Infrastructure\WordPress\WpThemeEnvironment;

final class ShellModeService
{
    public function __construct(
        private readonly ShellModeRepositoryInterface $repository,
        private readonly GlobalPartRepositoryInterface $globalPartRepo,
        private readonly ?WpThemeEnvironment $themeEnv = null,
        private readonly ?WorkingCanvasRefreshScheduler $workingCanvasRefreshes = null,
        private readonly ?PageGlobalPartResolverInterface $pagePartResolver = null,
        private readonly ?SourceGenerationStoreInterface $sourceGenerations = null,
        private readonly ?PageSourceMutation $pageSource = null,
        private readonly ?FailureReporterInterface $failureReporter = null,
    ) {}

    /**
     * Resolve the effective shell mode for a page.
     *
     * Page-level override wins over site default. The header/footer flags
     * reflect what this page's shell slots actually resolve to (honoring
     * per-page overrides), not just sitewide part existence.
     */
    public function resolveForPage(int $pageId): ShellModeContext
    {
        $pageOverride = $this->repository->getForPage($pageId);

        return new ShellModeContext(
            mode: $pageOverride ?? $this->repository->getSiteDefault(),
            hasUncannyHeader: $this->pageSlotHasPart(GlobalPartType::Header, $pageId),
            hasUncannyFooter: $this->pageSlotHasPart(GlobalPartType::Footer, $pageId),
            isExplicit: $pageOverride !== null,
        );
    }

    /**
     * Whether the page's shell slot resolves to an actual part. Falls back
     * to sitewide existence when no per-page resolver is wired (tests).
     */
    private function pageSlotHasPart(GlobalPartType $type, int $pageId): bool
    {
        if ($this->pagePartResolver !== null) {
            return $this->pagePartResolver->resolveForPage($pageId, $type) !== null;
        }

        return $this->globalPartRepo->findByType($type) !== null;
    }

    /**
     * Detection hints for the mode chooser UI.
     */
    public function detectSignals(): ShellSignals
    {
        $hasHeader = $this->globalPartRepo->findByType(GlobalPartType::Header) !== null;
        $hasFooter = $this->globalPartRepo->findByType(GlobalPartType::Footer) !== null;

        return new ShellSignals(
            hasUncannyHeader: $hasHeader,
            hasUncannyFooter: $hasFooter,
            isBlockTheme: $this->themeEnv?->isBlockTheme() ?? false,
            activeThemeName: $this->themeEnv?->activeThemeName() ?? '',
            provider: $this->detectSiteLevelProvider($hasHeader, $hasFooter),
        );
    }

    /**
     * Detect who provides the shell for a specific page, considering its resolved mode.
     *
     * A page in theme_composition mode is shell-owned by the theme/builder,
     * even if Uncanny global parts exist sitewide for other pages.
     */
    public function detectProviderForPage(int $pageId): ShellProvider
    {
        $ctx = $this->resolveForPage($pageId);

        // In theme_composition, the theme/builder owns the shell — not Uncanny.
        if ($ctx->mode === ShellMode::ThemeComposition) {
            return $this->detectExternalProviderForPage($pageId);
        }

        // In uncanny_native the canvas template owns the whole document;
        // slots that resolve to no part render nothing — the theme never
        // provides them (CanvasHijacker only defers for theme_composition).
        if ($ctx->mode === ShellMode::UncannyNative) {
            return ShellProvider::Uncanny;
        }

        // Mode is none — no shell commitment yet; detect external.
        return $this->detectExternalProviderForPage($pageId);
    }

    /**
     * Site-level provider detection (no page context). Used by detectSignals()
     * for the mode chooser UI where we want to know the sitewide situation.
     */
    private function detectSiteLevelProvider(bool $hasHeader, bool $hasFooter): ShellProvider
    {
        if ($hasHeader && $hasFooter) {
            return ShellProvider::Uncanny;
        }

        return $this->detectSiteLevelExternalProvider();
    }

    /**
     * Site-level external provider detection. Signals that a builder or theme
     * is present on the site. Used only by detectSignals() for UI hints.
     */
    private function detectSiteLevelExternalProvider(): ShellProvider
    {
        if ($this->themeEnv?->isElementorActive() ?? false) {
            return ShellProvider::Elementor;
        }

        if ($this->themeEnv?->themeExists() ?? false) {
            return ShellProvider::Theme;
        }

        return ShellProvider::UnknownExternal;
    }

    /**
     * Per-page external provider detection.
     *
     * Only claims Elementor if THIS page was built with Elementor
     * (_elementor_edit_mode postmeta), not just because the plugin is installed.
     * Engine-owned pages in theme_composition get their shell from the WP
     * template hierarchy, so the default answer is Theme.
     */
    private function detectExternalProviderForPage(int $pageId): ShellProvider
    {
        if ($this->themeEnv?->isElementorPage($pageId) ?? false) {
            return ShellProvider::Elementor;
        }

        if ($this->themeEnv?->themeExists() ?? false) {
            return ShellProvider::Theme;
        }

        return ShellProvider::UnknownExternal;
    }

    public function getSiteDefault(): ShellMode
    {
        return $this->repository->getSiteDefault();
    }

    /**
     * The return value reports only whether the derived canvas refresh was queued.
     * A false value means that the site default was saved.
     */
    public function setSiteDefault(ShellMode $mode): bool
    {
        $generation = $this->sourceGenerations?->globalGeneration();
        $previous = $this->repository->getSiteDefault();
        if ($previous === $mode) {
            return true;
        }

        if ($this->sourceGenerations instanceof SourceGenerationStoreInterface) {
            if ($generation === null) {
                throw new \LogicException('A global source generation is required for a guarded shell-mode write.');
            }

            $this->sourceGenerations->commitGlobal(
                $generation,
                fn(): mixed => $this->repository->setSiteDefault($mode),
            );
        } else {
            $this->repository->setSiteDefault($mode);
        }

        // Refresh after advancing the generation. The queue only rebuilds
        // editor-derived output and cannot change a published pointer.
        if (!$this->workingCanvasRefreshes instanceof WorkingCanvasRefreshScheduler) {
            return true;
        }

        try {
            $this->workingCanvasRefreshes->enqueueAll();
        } catch (\Throwable $failure) {
            try {
                $this->failureReporter?->report(
                    'site shell mode',
                    0,
                    'working_canvas.enqueue',
                    $failure,
                );
            } catch (\Throwable) {
                // A report failure cannot change the completed setting result.
            }
            return false;
        }

        return true;
    }

    public function setForPage(int $pageId, ShellMode $mode): void
    {
        $generation = $this->sourceGenerations?->pageGeneration($pageId);
        if ($this->repository->getForPage($pageId) === $mode) {
            return;
        }

        if ($this->sourceGenerations instanceof SourceGenerationStoreInterface) {
            if ($generation === null) {
                throw new \LogicException('A page source generation is required for a guarded shell-mode write.');
            }

            $this->commitPage(
                $pageId,
                $generation,
                fn(): mixed => $this->repository->setForPage($pageId, $mode),
            );

            return;
        }

        $this->repository->setForPage($pageId, $mode);
    }

    public function clearPageOverride(int $pageId): void
    {
        $generation = $this->sourceGenerations?->pageGeneration($pageId);
        if ($this->repository->getForPage($pageId) === null) {
            return;
        }

        if ($this->sourceGenerations instanceof SourceGenerationStoreInterface) {
            if ($generation === null) {
                throw new \LogicException('A page source generation is required for a guarded shell-mode write.');
            }

            $this->commitPage(
                $pageId,
                $generation,
                fn(): mixed => $this->repository->clearPageOverride($pageId),
            );

            return;
        }

        $this->repository->clearPageOverride($pageId);
    }

    /**
     * @param callable(): mixed $write
     */
    private function commitPage(int $pageId, int $expectedGeneration, callable $write): mixed
    {
        if ($this->pageSource instanceof PageSourceMutation) {
            return $this->pageSource->runExpected($pageId, $expectedGeneration, $write);
        }

        if (!$this->sourceGenerations instanceof SourceGenerationStoreInterface) {
            throw new \LogicException('A page source generation store is required.');
        }

        return $this->sourceGenerations->commitPage($pageId, $expectedGeneration, $write);
    }
}
