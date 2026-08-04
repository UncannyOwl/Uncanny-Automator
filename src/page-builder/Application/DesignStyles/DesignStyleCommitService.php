<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\DesignStyles;

use UncannyPageBuilder\Application\DesignStandardsService;
use UncannyPageBuilder\Application\Publishing\WorkingCanvasRefresherInterface;
use UncannyPageBuilder\Domain\DesignStandards\DesignStandardsProfile;
use UncannyPageBuilder\Domain\DesignStandards\PageDesignOverrides;
use UncannyPageBuilder\Domain\DesignStandards\TypographyProfile;
use UncannyPageBuilder\Domain\DesignStyles\DesignStyleValue;
use UncannyPageBuilder\Domain\DesignStyles\DesignWriteScope;

/**
 * Routes a design style commit to the correct ownership layer.
 *
 *   global  -> DesignStandardsProfile (sitewide, manage permission)
 *   page    -> PageDesignOverrides    (per-page, edit permission)
 *   element -> owner CSS              (section or global part source)
 *
 * Page Builder owns persistence here. Design Lens never reaches this service.
 */
final class DesignStyleCommitService implements CommitsDesignStyles
{
    public function __construct(
        private readonly DesignStandardsService $designStandards,
        private readonly ElementStyleCommitterInterface $elementCommitter,
        private readonly ?WorkingCanvasRefresherInterface $workingCanvas = null,
        private readonly ?GlobalPartElementStyleCommitterInterface $globalPartElementCommitter = null,
    ) {}

    public function commit(DesignStyleCommitRequest $request): DesignStyleCommitResult
    {
        return match ($request->scope()) {
            DesignWriteScope::Global => $this->commitGlobal($request),
            DesignWriteScope::Page => $this->commitPage($request),
            DesignWriteScope::Element => $this->commitElement($request),
        };
    }

    public function commitBatch(DesignStyleBatchCommitRequest $request): DesignStyleBatchCommitResult
    {
        $preparedItems = [];
        $preparedGlobalProfile = null;

        foreach ($this->batchGroups($request) as $group) {
            $resultOrPlan = $this->prepareBatchGroup($group, $request, $preparedGlobalProfile);
            if ($resultOrPlan instanceof DesignStyleCommitResult) {
                if (!$resultOrPlan->isSuccess()) {
                    $results = [];
                    foreach ($preparedItems as $preparedItem) {
                        if (($preparedItem['item'] ?? null) instanceof DesignStyleCommitResult) {
                            $results[] = [
                                'result'     => $preparedItem['item'],
                                'change_ids' => $preparedItem['change_ids'] ?? [],
                            ];
                        }
                    }
                    $results[] = ['result' => $resultOrPlan, 'change_ids' => $group['change_ids'] ?? []];

                    return DesignStyleBatchCommitResult::errorGroups($resultOrPlan->message(), $results);
                }
            }

            $preparedItems[] = [
                'item'       => $resultOrPlan,
                'change_ids' => $group['change_ids'] ?? [],
                'group'      => $group,
            ];
            if (
                is_array($resultOrPlan)
                && ($resultOrPlan['scope'] ?? null) === DesignWriteScope::Global
                && is_array($resultOrPlan['profile'] ?? null)
            ) {
                $preparedGlobalProfile = DesignStandardsProfile::fromArray($resultOrPlan['profile']);
            }
        }

        $results = [];
        $pageSourceCommitted = false;
        $globalSourceCommitted = false;

        foreach ($preparedItems as $item) {
            $prepared = $item['item'];

            /*
             * Page overrides and section element styles share one page source
             * generation. Sitewide design and every global-part source share
             * the global generation. Rebuild a later element plan after an
             * earlier group in this same Save advanced its generation; otherwise
             * a valid mixed Save deterministically rejects its own stale plan
             * after partially persisting the first group.
             *
             * Every group was already validated before the first write. This
             * second preparation only refreshes the exact source snapshot used
             * by the optimistic write. A concurrent write after this refresh is
             * still rejected by the normal repository CAS.
             */
            if (
                ($prepared instanceof ElementStyleCommitPlan && $pageSourceCommitted)
                || ($prepared instanceof GlobalPartElementStyleCommitPlan && $globalSourceCommitted)
            ) {
                $group = is_array($item['group'] ?? null) ? $item['group'] : [];
                $prepared = $this->prepareBatchGroup($group, $request, $preparedGlobalProfile);
            }

            $result = $prepared instanceof DesignStyleCommitResult
                ? $prepared
                : $this->applyPreparedBatchGroup($prepared);

            $results[] = ['result' => $result, 'change_ids' => $item['change_ids'] ?? []];
            if (!$result->isSuccess()) {
                return DesignStyleBatchCommitResult::errorGroups($result->message(), $results);
            }

            if (is_array($prepared) && ($prepared['scope'] ?? null) === DesignWriteScope::Page) {
                $pageSourceCommitted = true;
            }
            if (
                (is_array($prepared) && ($prepared['scope'] ?? null) === DesignWriteScope::Global)
                || $prepared instanceof GlobalPartElementStyleCommitPlan
            ) {
                $globalSourceCommitted = true;
            }
        }

        return DesignStyleBatchCommitResult::successGroups($results);
    }

    private function commitElement(DesignStyleCommitRequest $request): DesignStyleCommitResult
    {
        $owner = $request->owner();
        if (
            $owner instanceof DesignStyleSourceOwner
            && $owner->isGlobalPart()
            && $this->globalPartElementCommitter instanceof GlobalPartElementStyleCommitterInterface
        ) {
            return $this->globalPartElementCommitter->commit($request);
        }

        return $this->elementCommitter->commit($request);
    }

    /**
     * @return array<int, array{
     *     scope: DesignWriteScope,
     *     owner?: DesignStyleSourceOwner,
     *     section_id?: int,
     *     changes: DesignStyleChange[],
     *     batch_changes: DesignStyleBatchChange[],
     *     change_ids: string[]
     * }>
     */
    private function batchGroups(DesignStyleBatchCommitRequest $request): array
    {
        $groups = [];
        $index = [];

        foreach ($request->changes() as $batchChange) {
            $scope = $batchChange->scope();
            $key = $this->batchGroupKey($batchChange);
            if (!isset($index[$key])) {
                $index[$key] = count($groups);
                $group = [
                    'scope'         => $scope,
                    'order'         => count($groups),
                    'changes'       => [],
                    'batch_changes' => [],
                    'change_ids'    => [],
                ];

                $owner = $batchChange->owner();
                if ($owner instanceof DesignStyleSourceOwner) {
                    $group['owner'] = $owner;
                }
                if ($batchChange->sectionId() > 0) {
                    $group['section_id'] = $batchChange->sectionId();
                }

                $groups[] = $group;
            }

            $groups[$index[$key]]['changes'][] = $batchChange->change();
            $groups[$index[$key]]['batch_changes'][] = $batchChange;
            $groups[$index[$key]]['change_ids'][] = $batchChange->id();
        }

        usort($groups, function (array $a, array $b): int {
            $scopeOrder = $this->batchScopeOrder($a['scope']) <=> $this->batchScopeOrder($b['scope']);

            return $scopeOrder !== 0 ? $scopeOrder : ((int) ($a['order'] ?? 0) <=> (int) ($b['order'] ?? 0));
        });

        return $groups;
    }

    private function batchGroupKey(DesignStyleBatchChange $change): string
    {
        if ($change->scope() !== DesignWriteScope::Element) {
            return $change->scope()->value;
        }

        $owner = $change->owner();
        if ($owner instanceof DesignStyleSourceOwner && $owner->isGlobalPart()) {
            return sprintf('element:global_part:%d', $owner->id());
        }

        return 'element:sections';
    }

    /** @param array<string, mixed> $group */
    private function isGlobalPartElementGroup(array $group): bool
    {
        $owner = $group['owner'] ?? null;

        return ($group['scope'] ?? null) === DesignWriteScope::Element
            && $owner instanceof DesignStyleSourceOwner
            && $owner->isGlobalPart();
    }

    private function batchScopeOrder(DesignWriteScope $scope): int
    {
        return match ($scope) {
            DesignWriteScope::Global => 0,
            DesignWriteScope::Page => 1,
            DesignWriteScope::Element => 2,
        };
    }

    /**
     * @param array{
     *     scope: DesignWriteScope,
     *     owner?: DesignStyleSourceOwner,
     *     section_id?: int,
     *     changes: DesignStyleChange[],
     *     batch_changes: DesignStyleBatchChange[],
     *     change_ids: string[]
     * } $group
     */
    private function prepareBatchGroup(
        array $group,
        DesignStyleBatchCommitRequest $batch,
        ?DesignStandardsProfile $preparedGlobalProfile = null,
    ): array|ElementStyleCommitPlan|GlobalPartElementStyleCommitPlan|DesignStyleCommitResult {
        if (($group['scope'] ?? null) === DesignWriteScope::Element && !$this->isGlobalPartElementGroup($group)) {
            return $this->elementCommitter->prepareBatch(
                $batch->pageId(),
                $group['batch_changes'],
                $batch->capabilities(),
            );
        }
        $request = new DesignStyleCommitRequest(
            scope: $group['scope'],
            pageId: $batch->pageId(),
            changes: $group['changes'],
            capabilities: $batch->capabilities(),
            sectionId: (int) ($group['section_id'] ?? 0),
            owner: $group['owner'] ?? null,
        );

        return match ($group['scope']) {
            DesignWriteScope::Global => $this->prepareGlobal($request),
            DesignWriteScope::Page => $this->preparePage($request, $preparedGlobalProfile),
            DesignWriteScope::Element => $this->prepareElement($request),
        };
    }

    private function prepareElement(DesignStyleCommitRequest $request): ElementStyleCommitPlan|GlobalPartElementStyleCommitPlan|DesignStyleCommitResult
    {
        $owner = $request->owner();
        if (
            $owner instanceof DesignStyleSourceOwner
            && $owner->isGlobalPart()
            && $this->globalPartElementCommitter instanceof GlobalPartElementStyleCommitterInterface
        ) {
            return $this->globalPartElementCommitter->prepare($request);
        }

        return $this->elementCommitter->prepare($request);
    }

    private function applyPreparedBatchGroup(array|ElementStyleCommitPlan|GlobalPartElementStyleCommitPlan $plan): DesignStyleCommitResult
    {
        if ($plan instanceof ElementStyleCommitPlan) {
            return $this->elementCommitter->apply($plan);
        }
        if ($plan instanceof GlobalPartElementStyleCommitPlan && $this->globalPartElementCommitter instanceof GlobalPartElementStyleCommitterInterface) {
            return $this->globalPartElementCommitter->apply($plan);
        }

        return match ($plan['scope']) {
            DesignWriteScope::Global => $this->applyGlobal($plan),
            DesignWriteScope::Page => $this->applyPage($plan),
            default => DesignStyleCommitResult::error(DesignWriteScope::Element, 'Unable to apply this design change.'),
        };
    }

    // ── Global ──────────────────────────────────────────

    private function commitGlobal(DesignStyleCommitRequest $request): DesignStyleCommitResult
    {
        $plan = $this->prepareGlobal($request);

        return $plan instanceof DesignStyleCommitResult
            ? $plan
            : $this->applyGlobal($plan);
    }

    /**
     * @return array{
     *     scope: DesignWriteScope,
     *     generation: ?int,
     *     profile: array<string, mixed>,
     *     applied: array<int, array{property: string, value: string}>
     * }|DesignStyleCommitResult
     */
    private function prepareGlobal(DesignStyleCommitRequest $request): array|DesignStyleCommitResult
    {
        if (!$request->canManage()) {
            return DesignStyleCommitResult::error(
                DesignWriteScope::Global,
                'You do not have permission to edit site design settings.',
            );
        }

        // Capture before reading. The same generation travels with this plan so
        // apply cannot replace a concurrent global write with this older profile.
        $generation = $this->designStandards->globalGeneration();

        // Build on the RAW persisted profile, never the filter-resolved one — the
        // uncanny_engine_bootstrap_theme filter is a runtime precedence layer and
        // must not be baked into the stored option.
        $current = $this->designStandards->loadProfile();
        $profileArray = $current->toArray();
        $tokens = $this->toMutableArray($profileArray['tokens'] ?? []);
        $typography = $current->typography()->toRoleArray();
        $lockedKeys = $current->lockedKeys();

        $applied = [];
        $rejected = [];
        $ignoredClears = [];

        foreach ($request->changes() as $change) {
            $property = $change->property();
            $value = $change->value();

            if ($change->bucket() === 'typography') {
                $typographyProperty = $this->normalizeTypographyProperty($property);
                if ($typographyProperty === null) {
                    $rejected[] = ['property' => $property, 'reason' => 'invalid_typography'];
                    continue;
                }

                if (in_array($typographyProperty, $lockedKeys['typography'] ?? [], true)) {
                    $rejected[] = ['property' => $typographyProperty, 'reason' => 'locked'];
                    continue;
                }
                if ($value === '') {
                    $ignoredClears[] = ['property' => $typographyProperty, 'value' => $value];
                    continue;
                }

                try {
                    $typography = $this->applyTypographyValue($typography, $typographyProperty, $value);
                } catch (\InvalidArgumentException) {
                    $rejected[] = ['property' => $typographyProperty, 'reason' => 'unsafe_value'];
                    continue;
                }

                $applied[] = ['property' => $typographyProperty, 'value' => $value];
                continue;
            }

            if (!DesignStyleValue::isValidTokenName($property)) {
                $rejected[] = ['property' => $property, 'reason' => 'invalid_token'];
                continue;
            }
            if (in_array($property, $lockedKeys['tokens'] ?? [], true)) {
                $rejected[] = ['property' => $property, 'reason' => 'locked'];
                continue;
            }
            if ($value === '') {
                $ignoredClears[] = ['property' => $property, 'value' => $value];
                continue;
            }
            if (!DesignStyleValue::isSafeValue($value)) {
                $rejected[] = ['property' => $property, 'reason' => 'unsafe_value'];
                continue;
            }

            $tokens[$property] = $value;
            $applied[] = ['property' => $property, 'value' => $value];
        }

        if ($rejected !== []) {
            return DesignStyleCommitResult::error(
                DesignWriteScope::Global,
                $this->hasRejectionReason($rejected, 'locked')
                    ? 'Some Brand styles values are protected.'
                    : 'Some Brand styles changes could not be saved.',
                $rejected,
            );
        }

        if ($applied === []) {
            if ($ignoredClears !== []) {
                return DesignStyleCommitResult::success(
                    DesignWriteScope::Global,
                    'Brand styles unchanged.',
                    [],
                    ['global_profile' => $this->designStandards->resolve()->toArray()],
                );
            }

            return DesignStyleCommitResult::error(
                DesignWriteScope::Global,
                'No valid Brand styles changes to save.',
                $rejected,
            );
        }

        $profileArray['tokens'] = $tokens;
        $profileArray['typography'] = ['roles' => $typography];

        return [
            'scope'      => DesignWriteScope::Global,
            'generation' => $generation,
            'profile'    => $profileArray,
            'applied'    => $applied,
        ];
    }

    /**
     * @param array{
     *     scope: DesignWriteScope,
     *     generation: ?int,
     *     profile: array<string, mixed>,
     *     applied: array<int, array{property: string, value: string}>
     * } $plan
     */
    private function applyGlobal(array $plan): DesignStyleCommitResult
    {
        $canvasRefreshQueued = $this->designStandards->save(
            DesignStandardsProfile::fromArray($plan['profile']),
            $plan['generation'],
        );
        $refreshed = ['global_profile' => $this->designStandards->resolve()->toArray()];

        if (!$canvasRefreshQueued) {
            $refreshed['rebuild_warning'] = $this->workingCanvasRefreshWarning();
        }

        return DesignStyleCommitResult::success(
            DesignWriteScope::Global,
            $canvasRefreshQueued
                ? 'Brand styles updated.'
                : 'Brand styles updated, but working canvas CSS could not be queued for refresh.',
            $plan['applied'],
            $refreshed,
        );
    }

    // ── Page ────────────────────────────────────────────

    private function commitPage(DesignStyleCommitRequest $request): DesignStyleCommitResult
    {
        $plan = $this->preparePage($request);

        return $plan instanceof DesignStyleCommitResult
            ? $plan
            : $this->applyPage($plan);
    }

    /**
     * @return array{
     *     scope: DesignWriteScope,
     *     generation: ?int,
     *     page_id: int,
     *     overrides: PageDesignOverrides,
     *     tokens: array<string, string>,
     *     typography: array<string, array<string, string>>,
     *     requested: array{tokens: array<string, bool>, typography: array<string, bool>},
     *     cleared: array{tokens: array<string, bool>, typography: array<string, bool>}
     * }|DesignStyleCommitResult
     */
    private function preparePage(
        DesignStyleCommitRequest $request,
        ?DesignStandardsProfile $sitewideValidationProfile = null,
    ): array|DesignStyleCommitResult {
        if (!$request->canEdit() || $request->pageId() <= 0) {
            return DesignStyleCommitResult::error(
                DesignWriteScope::Page,
                'You do not have permission to edit this page.',
            );
        }

        // Capture before reading. The prepared overrides must be committed
        // against this exact page generation, not whichever generation exists
        // later when the batch begins applying its plans.
        $generation = $this->designStandards->pageGeneration($request->pageId());
        $existing = $this->designStandards->loadPageOverrides($request->pageId());
        $tokens = $existing->tokens();
        $typography = $existing->typography()->toRoleArray();

        // Track only the properties THIS request touched, so the applied audit
        // reports what changed rather than every pre-existing override. Empty
        // page values are deliberate clears: delete the sparse override and let
        // the page inherit the global token again.
        $requested = ['tokens' => [], 'typography' => []];
        $cleared = ['tokens' => [], 'typography' => []];

        $rejected = [];
        foreach ($request->changes() as $change) {
            $property = $change->property();
            $value = $change->value();

            if ($change->bucket() === 'typography') {
                $typographyProperty = $this->normalizeTypographyProperty($property);
                if ($typographyProperty === null) {
                    $rejected[] = ['property' => $property, 'reason' => 'invalid_typography'];
                    continue;
                }

                if ($value === '') {
                    $typography = $this->clearTypographyValue($typography, $typographyProperty);
                    $requested['typography'][$typographyProperty] = true;
                    $cleared['typography'][$typographyProperty] = true;
                    continue;
                }

                try {
                    $typography = $this->applyTypographyValue($typography, $typographyProperty, $value);
                } catch (\InvalidArgumentException) {
                    $rejected[] = ['property' => $typographyProperty, 'reason' => 'unsafe_value'];
                    continue;
                }

                $requested['typography'][$typographyProperty] = true;
                continue;
            }

            if (!DesignStyleValue::isValidTokenName($property)) {
                $rejected[] = ['property' => $property, 'reason' => 'invalid_token'];
                continue;
            }

            if ($value === '') {
                unset($tokens[$property]);
                $requested['tokens'][$property] = true;
                $cleared['tokens'][$property] = true;
                continue;
            }

            if (!DesignStyleValue::isSafeValue($value)) {
                $rejected[] = ['property' => $property, 'reason' => 'unsafe_value'];
                continue;
            }

            $tokens[$property] = $value;
            $requested['tokens'][$property] = true;
        }

        if ($rejected !== []) {
            return DesignStyleCommitResult::error(
                DesignWriteScope::Page,
                'Some page design settings could not be saved.',
                $rejected,
            );
        }

        $overrides = new PageDesignOverrides($tokens, TypographyProfile::fromRolesArray($typography));
        $result = $sitewideValidationProfile instanceof DesignStandardsProfile
            ? $this->designStandards->resolveOverridesAgainstProfile($sitewideValidationProfile, $overrides)
            : $this->designStandards->resolveForPageWithAudit($request->pageId(), $overrides);

        // Locked or rejected keys reported by the service are commit failures —
        // validate them before writing so a mixed group cannot partially save
        // while the client keeps the whole scope dirty.
        $serviceRejected = $this->flattenAudit($result->rejectedKeys(), 'rejected');
        $serviceLocked = $this->flattenAudit($result->lockedKeys(), 'locked');
        $allRejected = array_merge($serviceRejected, $serviceLocked);

        if ($allRejected !== []) {
            $message = $serviceLocked !== []
                ? 'Some page design settings are protected by site settings.'
                : 'Some page design settings could not be saved.';

            return DesignStyleCommitResult::error(DesignWriteScope::Page, $message, $allRejected);
        }

        return [
            'scope'       => DesignWriteScope::Page,
            'generation'  => $generation,
            'page_id'     => $request->pageId(),
            'overrides'   => $overrides,
            'tokens'      => $tokens,
            'typography'  => $typography,
            'requested'   => $requested,
            'cleared'     => $cleared,
        ];
    }

    /**
     * @param array{
     *     scope: DesignWriteScope,
     *     generation: ?int,
     *     page_id: int,
     *     overrides: PageDesignOverrides,
     *     tokens: array<string, string>,
     *     typography: array<string, array<string, string>>,
     *     requested: array{tokens: array<string, bool>, typography: array<string, bool>},
     *     cleared: array{tokens: array<string, bool>, typography: array<string, bool>}
     * } $plan
     */
    private function applyPage(array $plan): DesignStyleCommitResult
    {
        $result = $this->designStandards->savePageOverrides(
            $plan['page_id'],
            $plan['overrides'],
            $plan['generation'],
        );

        $canvasRefreshed = $this->refreshWorkingCanvas($plan['page_id']);
        $appliedKeys = $result->appliedKeys();
        $applied = $this->appliedPairs(
            $appliedKeys,
            $plan['tokens'],
            $plan['typography'],
            $plan['requested'],
            $plan['cleared'],
        );

        $refreshed = ['page_overrides' => $result->toArray()];
        if (!$canvasRefreshed) {
            $refreshed['rebuild_warning'] = $this->workingCanvasRefreshWarning();
        }

        return DesignStyleCommitResult::success(
            DesignWriteScope::Page,
            $canvasRefreshed
                ? 'Page design overrides updated.'
                : 'Page design overrides updated, but working canvas CSS could not be refreshed.',
            $applied,
            $refreshed,
        );
    }

    // ── Helpers ─────────────────────────────────────────

    /**
     * @param mixed $value
     * @return array<string, string>
     */
    private function toMutableArray(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $out = [];
        foreach ($value as $key => $tokenValue) {
            if (is_string($key)) {
                $out[$key] = (string) $tokenValue;
            }
        }

        return $out;
    }

    /**
     * @param array{tokens: string[], typography: string[]} $audit
     * @return array<int, array{property: string, reason: string}>
     */
    private function flattenAudit(array $audit, string $reason): array
    {
        $out = [];
        foreach ($audit['tokens'] ?? [] as $property) {
            $out[] = ['property' => (string) $property, 'reason' => $reason];
        }
        foreach ($audit['typography'] ?? [] as $property) {
            $out[] = ['property' => (string) $property, 'reason' => $reason];
        }

        return $out;
    }

    /**
     * @param array<int, array{property: string, reason: string}> $rejected
     */
    private function hasRejectionReason(array $rejected, string $reason): bool
    {
        foreach ($rejected as $entry) {
            if (($entry['reason'] ?? '') === $reason) {
                return true;
            }
        }

        return false;
    }

    /**
     * Applied pairs limited to keys the current request actually changed. The
     * service's appliedKeys() spans the whole persisted override set; intersecting
     * with $requested keeps the audit honest (only what this commit touched).
     *
     * @param array{tokens: string[], typography: string[]} $appliedKeys
     * @param array<string, string> $tokens
     * @param array<string, array<string, string>> $typography
     * @param array{tokens: array<string, bool>, typography: array<string, bool>} $requested
     * @param array{tokens: array<string, bool>, typography: array<string, bool>} $cleared
     * @return array<int, array{property: string, value: string}>
     */
    private function appliedPairs(
        array $appliedKeys,
        array $tokens,
        array $typography,
        array $requested,
        array $cleared,
    ): array {
        $out = [];
        foreach ($appliedKeys['tokens'] ?? [] as $property) {
            if (isset($tokens[$property]) && isset($requested['tokens'][$property])) {
                $out[] = ['property' => (string) $property, 'value' => $tokens[$property]];
            }
        }
        foreach (array_keys($cleared['tokens']) as $property) {
            $out[] = ['property' => (string) $property, 'value' => ''];
        }
        foreach ($appliedKeys['typography'] ?? [] as $property) {
            $parts = $this->normalizeTypographyProperty((string) $property);
            if ($parts === null || !isset($requested['typography'][$parts])) {
                continue;
            }

            [$role, $field] = explode('.', $parts, 2);
            if (isset($typography[$role][$field])) {
                $out[] = ['property' => $parts, 'value' => $typography[$role][$field]];
            }
        }
        foreach (array_keys($cleared['typography']) as $property) {
            $out[] = ['property' => (string) $property, 'value' => ''];
        }

        return $out;
    }

    private function normalizeTypographyProperty(string $property): ?string
    {
        $parts = explode('.', $property, 2);
        $role = trim((string) ($parts[0] ?? ''));
        $field = trim((string) ($parts[1] ?? ''));

        if ($role === '' || $field === '') {
            return null;
        }

        try {
            $profile = TypographyProfile::fromRolesArray([
                $role => [$field => 'inherit'],
            ])->toRoleArray();
        } catch (\InvalidArgumentException) {
            return null;
        }

        $normalizedRole = array_key_first($profile);
        $normalizedField = $normalizedRole !== null ? array_key_first($profile[$normalizedRole] ?? []) : null;

        if (!is_string($normalizedRole) || !is_string($normalizedField) || $normalizedRole === '' || $normalizedField === '') {
            return null;
        }

        return $normalizedRole . '.' . $normalizedField;
    }

    /**
     * @param array<string, array<string, string>> $typography
     * @return array<string, array<string, string>>
     */
    private function applyTypographyValue(array $typography, string $property, string $value): array
    {
        $parts = explode('.', $property, 2);
        $role = $parts[0] ?? '';
        $field = $parts[1] ?? '';
        $profile = TypographyProfile::fromRolesArray([
            $role => [$field => $value],
        ])->toRoleArray();

        $normalizedRole = array_key_first($profile);
        $fields = $normalizedRole !== null ? ($profile[$normalizedRole] ?? []) : [];
        $normalizedField = array_key_first($fields);
        $normalizedValue = $normalizedField !== null ? ($fields[$normalizedField] ?? null) : null;

        if (!is_string($normalizedRole) || !is_string($normalizedField) || !is_string($normalizedValue)) {
            throw new \InvalidArgumentException('Invalid typography property.');
        }

        if (!isset($typography[$normalizedRole])) {
            $typography[$normalizedRole] = [];
        }

        $typography[$normalizedRole][$normalizedField] = $normalizedValue;

        return $typography;
    }

    /**
     * @param array<string, array<string, string>> $typography
     * @return array<string, array<string, string>>
     */
    private function clearTypographyValue(array $typography, string $property): array
    {
        $parts = explode('.', $property, 2);
        $role = $parts[0] ?? '';
        $field = $parts[1] ?? '';

        if (isset($typography[$role][$field])) {
            unset($typography[$role][$field]);
            if ($typography[$role] === []) {
                unset($typography[$role]);
            }
        }

        return $typography;
    }

    private function refreshWorkingCanvas(int $pageId): bool
    {
        if (!$this->workingCanvas instanceof WorkingCanvasRefresherInterface) {
            return true;
        }

        try {
            $this->workingCanvas->refresh($pageId);
        } catch (\Throwable) {
            return false;
        }

        return true;
    }

    /** @return array{code: string, message: string} */
    private function workingCanvasRefreshWarning(): array
    {
        return DesignStandardsService::workingCanvasRefreshWarning();
    }
}
