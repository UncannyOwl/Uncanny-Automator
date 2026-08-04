<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\Canvas;

use UncannyPageBuilder\Application\Access\PageBuilderAvailabilityInterface;
use UncannyPageBuilder\Application\Access\PageBuilderDisabledException;
use UncannyPageBuilder\Application\Controls\PageDetailsPortInterface;
use UncannyPageBuilder\Application\Publishing\WorkingCanvasRefresherInterface;
use UncannyPageBuilder\Application\ShellModeService;
use UncannyPageBuilder\Domain\Canvas\PageOwnershipRepositoryInterface;
use UncannyPageBuilder\Domain\Section\SectionRepositoryInterface;
use UncannyPageBuilder\Domain\Shell\ShellMode;

/**
 * Makes an existing WordPress page a Page Builder-owned canvas.
 *
 * WordPress-specific validation stays in infrastructure. Adoption preserves
 * the exact WordPress body and keeps it active until Page Builder has durable
 * source to publish.
 */
final class AdoptPageUseCase
{
    public function __construct(
        private readonly PageOwnershipRepositoryInterface $ownership,
        private readonly OriginalPageContentStoreInterface $originalContent,
        private readonly SectionRepositoryInterface $sections,
        private readonly ShellModeService $shellModes,
        private readonly WorkingCanvasRefresherInterface $workingCanvas,
        private readonly PageDetailsPortInterface $pageDetails,
        private readonly PageBuilderAvailabilityInterface $availability,
    ) {}

    /**
     * @return bool True when the page was newly adopted; false when it was already owned.
     */
    public function __invoke(int $pageId): bool
    {
        if ($pageId <= 0) {
            throw new \InvalidArgumentException('A positive page ID is required.');
        }

        if ($this->ownership->isOwned($pageId)) {
            // Schema migration covers existing pages. This idempotent boundary
            // also repairs a newly owned page if adoption previously stopped
            // after ownership was stored but before its draft details existed.
            $this->pageDetails->initialize($pageId, 0);

            return false;
        }

        if (!$this->availability->allowsNewPages()) {
            throw new PageBuilderDisabledException();
        }

        $this->originalContent->preserve($pageId);
        $shellChanged = false;
        $hasSections = $this->sections->hasSections($pageId);

        try {
            // A returned page keeps its Page Builder source dormant. Preserve
            // its shell when that source is resumed; only new source starts
            // from the neutral mode chooser state.
            if (!$hasSections) {
                $this->shellModes->setForPage($pageId, ShellMode::None);
                $shellChanged = true;
            }
            $this->ownership->markOwned($pageId);
            if ($hasSections) {
                // Re-adoption resumes dormant working source in the editor, but
                // the WordPress body stays intact as the no-pointer and plugin-
                // deactivation fallback until a human publishes explicitly.
                $this->workingCanvas->refresh($pageId);
            }

            // Initialize last so any failure before this point cannot leave a
            // Page Builder state row attached to a WordPress-managed page.
            $this->pageDetails->initialize($pageId, 0);
        } catch (\Throwable $error) {
            $rollbackError = $this->rollback($pageId, $shellChanged);
            if ($rollbackError !== null) {
                throw new \RuntimeException(
                    'Page Builder could not take over the page, and its ownership changes could not be rolled back automatically: '
                    . $rollbackError->getMessage(),
                    0,
                    $error,
                );
            }

            throw $error;
        }

        return true;
    }

    // Section: Failed transition recovery

    private function rollback(int $pageId, bool $shellChanged): ?\Throwable
    {
        $firstError = null;

        /*
         * Adoption never writes post_content. Restoring the preserved body
         * here would overwrite a legitimate WordPress edit made concurrently
         * after preserve(), so rollback owns metadata only.
         */
        $rollbacks = [];
        if ($shellChanged) {
            $rollbacks[] = fn() => $this->shellModes->clearPageOverride($pageId);
        }
        $rollbacks[] = fn() => $this->ownership->markWordPressManaged($pageId);
        $rollbacks[] = fn() => $this->originalContent->discardBackup($pageId);

        foreach ($rollbacks as $rollback) {
            try {
                $rollback();
            } catch (\Throwable $error) {
                $firstError ??= $error;
            }
        }

        // Any incomplete rollback remains Page Builder-owned so a stale
        // backup cannot be mistaken for the current WordPress body later.
        if ($firstError !== null) {
            try {
                $this->ownership->markOwned($pageId);
            } catch (\Throwable) {
                // Preserve the first rollback failure for the caller.
            }
        }

        return $firstError;
    }
}
