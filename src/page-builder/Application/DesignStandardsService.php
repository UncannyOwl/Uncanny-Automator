<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application;

use UncannyPageBuilder\Application\Concurrency\PageSourceMutation;
use UncannyPageBuilder\Application\Publishing\WorkingCanvasRefreshScheduler;
use UncannyPageBuilder\Domain\Concurrency\SourceGenerationStoreInterface;
use UncannyPageBuilder\Domain\DesignStandards\BootstrapBreakpoints;
use UncannyPageBuilder\Domain\DesignStandards\BootstrapTokenProfile;
use UncannyPageBuilder\Domain\DesignStandards\CssTokenScanner;
use UncannyPageBuilder\Domain\DesignStandards\DesignStandardsProfile;
use UncannyPageBuilder\Domain\DesignStandards\DesignStandardsRepositoryInterface;
use UncannyPageBuilder\Domain\DesignStandards\PageDesignOverrides;
use UncannyPageBuilder\Domain\DesignStandards\PageOverrideResult;
use UncannyPageBuilder\Domain\DesignStandards\TypographyProfile;
use UncannyPageBuilder\Domain\Exception\StaleSourceGenerationException;

/**
 * Resolves the effective Bootstrap design standards for the current site.
 *
 * Single codepath for the token injector (browser CSS), the AI edit
 * context, and the REST API. Precedence:
 *
 *   1. platform defaults (Bootstrap 5 native values)
 *   2. persisted sitewide DesignStandardsProfile
 *   3. `uncanny_engine_bootstrap_theme` filter (profile-level)
 *   4. page-level overrides from `_uncanny_engine_theme_overrides`
 *      (tokens only — validated against sitewide keys)
 */
final class DesignStandardsService
{
    public function __construct(
        private readonly DesignStandardsRepositoryInterface $repository,
        private readonly ?WorkingCanvasRefreshScheduler $workingCanvasRefreshes = null,
        private readonly ?SourceGenerationStoreInterface $sourceGenerations = null,
        private readonly ?PageSourceMutation $pageSource = null,
    ) {}

    /**
     * Resolve the sitewide profile (no page overrides).
     */
    public function resolve(): DesignStandardsProfile
    {
        $profile = $this->repository->load() ?? DesignStandardsProfile::defaults();

        try {
            $filtered = $this->repository->applyFilter('uncanny_engine_bootstrap_theme', $profile->toArray());
            if (is_array($filtered)) {
                $profile = DesignStandardsProfile::fromArray($filtered);
            }
        } catch (\Throwable $e) {
            // Malformed filter return — fall back to unfiltered profile.
        }

        return $profile;
    }

    /**
     * Load the raw persisted sitewide profile, WITHOUT applying the
     * `uncanny_engine_bootstrap_theme` runtime filter.
     *
     * The filter is a runtime precedence layer, not persisted state. Callers that
     * intend to save a profile back to storage must build on this raw baseline so
     * a transient filter overlay is never baked into the stored option.
     */
    public function loadProfile(): DesignStandardsProfile
    {
        return $this->repository->load() ?? DesignStandardsProfile::defaults();
    }

    /**
     * Return the generation that must accompany a prepared sitewide write.
     *
     * Capture this before loading the profile. If another global write lands
     * during or after that read, commitGlobal() rejects the prepared snapshot
     * instead of allowing it to overwrite the newer source.
     */
    public function globalGeneration(): ?int
    {
        return $this->sourceGenerations?->globalGeneration();
    }

    /**
     * Persist the sitewide profile at the generation captured by its reader.
     *
     * The boolean reports only whether post-persistence working-preview
     * refreshes were queued. A false result still means the profile itself was
     * saved and must never be presented as an ordinary failed write.
     */
    public function save(DesignStandardsProfile $profile, ?int $expectedGeneration = null): bool
    {
        if ($this->sourceGenerations instanceof SourceGenerationStoreInterface) {
            $generation = $expectedGeneration ?? $this->sourceGenerations->globalGeneration();
            $this->sourceGenerations->commitGlobal(
                $generation,
                fn(): mixed => $this->repository->save($profile),
            );
        } else {
            $this->repository->save($profile);
        }

        if (!$this->workingCanvasRefreshes instanceof WorkingCanvasRefreshScheduler) {
            return true;
        }

        try {
            $this->workingCanvasRefreshes->enqueueAll();
        } catch (\Throwable) {
            return false;
        }

        return true;
    }

    /**
     * Resolve the effective profile for a specific page.
     */
    public function resolveForPage(int $pageId): DesignStandardsProfile
    {
        return $this->resolveForPageWithAudit($pageId)->resolved();
    }

    /**
     * Resolve the effective profile for a specific page with full audit data.
     */
    public function resolveForPageWithAudit(int $pageId, ?PageDesignOverrides $preloaded = null): PageOverrideResult
    {
        $sitewide = $this->resolve();

        if ($preloaded !== null) {
            $overrides = $preloaded;
        } else {
            $rawOverrides = $this->repository->loadPageOverrides($pageId);
            try {
                $overrides = is_array($rawOverrides)
                    ? PageDesignOverrides::fromArray($rawOverrides)
                    : new PageDesignOverrides();
            } catch (\Throwable $e) {
                $overrides = new PageDesignOverrides();
            }
        }

        return $this->applyOverrides($sitewide, $overrides);
    }

    /**
     * Validate/resolve page overrides against a caller-owned sitewide profile.
     *
     * The design stack batch use case prepares global before page. During that
     * same Save click, page overrides should be checked against the in-memory
     * global profile that is about to be saved, not the old persisted one.
     */
    public function resolveOverridesAgainstProfile(
        DesignStandardsProfile $sitewide,
        PageDesignOverrides $overrides,
    ): PageOverrideResult {
        return $this->applyOverrides($sitewide, $overrides);
    }

    /**
     * Save page-level design standards overrides.
     *
     * Only keys that pass validation (present in the sitewide vocabulary and not
     * locked) are persisted. Rejected and locked keys are reported in the result
     * audit but never written to storage — callers that need to treat them as
     * hard failures should inspect the result's rejected/locked buckets.
     */
    public function savePageOverrides(
        int $pageId,
        PageDesignOverrides $overrides,
        ?int $expectedGeneration = null,
    ): PageOverrideResult {
        $sitewide = $this->resolve();
        $result = $this->applyOverrides($sitewide, $overrides);

        // Persist only the applied subset. Locked/rejected keys are dropped so
        // they cannot silently land in storage.
        $applied = $this->filterToAppliedKeys($overrides, $result->appliedKeys());

        if ($this->sourceGenerations instanceof SourceGenerationStoreInterface) {
            $generation = $expectedGeneration ?? $this->sourceGenerations->pageGeneration($pageId);
            $appliedPayload = $applied->toArray();
            if ($this->pageOverridesMatch($pageId, $appliedPayload)) {
                $currentGeneration = $this->sourceGenerations->pageGeneration($pageId);
                if ($currentGeneration !== $generation) {
                    throw new StaleSourceGenerationException('page', $generation, $currentGeneration);
                }

                return $result;
            }

            $write = fn(): mixed => $this->repository->savePageOverrides($pageId, $appliedPayload);
            if ($this->pageSource instanceof PageSourceMutation) {
                $this->pageSource->runExpected($pageId, $generation, $write);
            } else {
                $this->sourceGenerations->commitPage($pageId, $generation, $write);
            }
        } else {
            $this->repository->savePageOverrides($pageId, $applied->toArray());
        }

        return $result;
    }

    /**
     * Compare canonical persisted page design state before entering the shared
     * mutation boundary. A semantic no-op must preserve generation and Redo.
     *
     * @param array<string, mixed> $expected
     */
    private function pageOverridesMatch(int $pageId, array $expected): bool
    {
        $raw = $this->repository->loadPageOverrides($pageId);
        if ($raw === null) {
            return (new PageDesignOverrides())->toArray() === $expected;
        }
        if (!is_array($raw)) {
            return false;
        }

        try {
            return PageDesignOverrides::fromArray($raw)->toArray() === $expected;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Return the generation that must accompany prepared overrides for a page.
     */
    public function pageGeneration(int $pageId): ?int
    {
        return $this->sourceGenerations?->pageGeneration($pageId);
    }

    /**
     * Stable warning contract for durable design source whose derived working
     * canvas could not be queued for refresh.
     *
     * @return array{code: string, message: string}
     */
    public static function workingCanvasRefreshWarning(): array
    {
        return [
            'code' => 'working_canvas_refresh_failed',
            'message' => 'The design source was saved, but working canvas CSS could not be refreshed. Reload and try again.',
        ];
    }

    /**
     * Build a PageDesignOverrides containing only the keys that were applied.
     *
     * @param array{tokens: string[], typography: string[]} $appliedKeys
     */
    private function filterToAppliedKeys(PageDesignOverrides $overrides, array $appliedKeys): PageDesignOverrides
    {
        $tokens = array_intersect_key(
            $overrides->tokens(),
            array_flip($appliedKeys['tokens'] ?? []),
        );

        $typography = [];
        foreach ($overrides->typography()->toRoleArray() as $role => $fields) {
            foreach ($fields as $field => $value) {
                $key = $this->typographyKey($role, $field);
                if (!in_array($key, $appliedKeys['typography'] ?? [], true)) {
                    continue;
                }

                $typography[$role][$field] = $value;
            }
        }

        return new PageDesignOverrides($tokens, TypographyProfile::fromRolesArray($typography));
    }

    /**
     * Load raw page overrides (for REST read endpoint).
     */
    public function loadPageOverrides(int $pageId): PageDesignOverrides
    {
        $raw = $this->repository->loadPageOverrides($pageId);
        if (!is_array($raw)) {
            return new PageDesignOverrides();
        }
        try {
            return PageDesignOverrides::fromArray($raw);
        } catch (\Throwable $e) {
            return new PageDesignOverrides();
        }
    }

    /**
     * Inspect which design tokens a section's CSS consumes.
     *
     * @return array{consumed_tokens: string[], resolved_values: array<string, ?string>}
     */
    public function getConsumedTokens(string $css, string $html, int $pageId): array
    {
        $consumedTokens = CssTokenScanner::scan($css, $html);

        $resolved = $this->resolveForPage($pageId);
        $allTokens = $resolved->tokens()->toArray();

        $consumedTokens = array_values(array_filter(
            $consumedTokens,
            static fn(string $key): bool => array_key_exists($key, $allTokens),
        ));

        $resolvedValues = [];
        foreach ($consumedTokens as $tokenKey) {
            $resolvedValues[$tokenKey] = $allTokens[$tokenKey];
        }

        return [
            'consumed_tokens' => $consumedTokens,
            'resolved_values' => $resolvedValues,
        ];
    }

    // ── Private ──────────────────────────────────────

    /**
     * Validate override keys against the sitewide profile and merge.
     *
     * Token and typography buckets. Locked keys are rejected into a separate
     * audit bucket.
     */
    private function applyOverrides(
        DesignStandardsProfile $sitewide,
        PageDesignOverrides $overrides,
    ): PageOverrideResult {
        $appliedKeys  = ['tokens' => [], 'typography' => []];
        $rejectedKeys = ['tokens' => [], 'typography' => []];
        $lockedKeys   = ['tokens' => [], 'typography' => []];

        $locked = $sitewide->lockedKeys();

        // ── Light-mode tokens ──
        $sitewideTokenKeys = array_keys($sitewide->tokens()->toArray());
        $mergedTokens = $sitewide->tokens()->toArray();

        foreach ($overrides->tokens() as $key => $value) {
            if (!in_array($key, $sitewideTokenKeys, true)) {
                $rejectedKeys['tokens'][] = $key;
            } elseif (in_array($key, $locked['tokens'], true)) {
                $lockedKeys['tokens'][] = $key;
            } else {
                $mergedTokens[$key] = $value;
                $appliedKeys['tokens'][] = $key;
            }
        }

        // ── Typography roles ──
        $sitewideTypography = $sitewide->typography()->toRoleArray();
        $mergedTypography = $sitewideTypography;

        foreach ($overrides->typography()->toRoleArray() as $role => $fields) {
            $sitewideFields = $sitewideTypography[$role] ?? null;

            foreach ($fields as $field => $value) {
                $key = $this->typographyKey($role, $field);

                if (!is_array($sitewideFields) || !array_key_exists($field, $sitewideFields)) {
                    $rejectedKeys['typography'][] = $key;
                } elseif (in_array($key, $locked['typography'], true)) {
                    $lockedKeys['typography'][] = $key;
                } else {
                    $mergedTypography[$role][$field] = $value;
                    $appliedKeys['typography'][] = $key;
                }
            }
        }

        $resolved = new DesignStandardsProfile(
            BootstrapTokenProfile::fromArray($mergedTokens),
            $sitewide->breakpoints(),
            TypographyProfile::fromRolesArray($mergedTypography),
            [
                'tokens' => $locked['tokens'] ?? [],
                'typography' => $locked['typography'] ?? [],
            ],
        );

        return new PageOverrideResult($resolved, $appliedKeys, $rejectedKeys, $lockedKeys);
    }

    private function typographyKey(string $role, string $field): string
    {
        return $role . '.' . $field;
    }
}
