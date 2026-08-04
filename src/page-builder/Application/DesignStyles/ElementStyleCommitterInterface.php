<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\DesignStyles;

/**
 * Commits element-scoped style changes as section CSS.
 *
 * Seam between the scope router (DesignStyleCommitService) and the section
 * persistence machinery, so the router can be tested without the section stack.
 */
interface ElementStyleCommitterInterface
{
    public function commit(DesignStyleCommitRequest $request): DesignStyleCommitResult;

    public function prepare(DesignStyleCommitRequest $request): ElementStyleCommitPlan|DesignStyleCommitResult;

    /**
     * @param DesignStyleBatchChange[] $changes
     * @param array{can_edit: bool, can_manage: bool, can_upload: bool, can_publish?: bool} $capabilities
     */
    public function prepareBatch(int $pageId, array $changes, array $capabilities): ElementStyleCommitPlan|DesignStyleCommitResult;

    public function apply(ElementStyleCommitPlan $plan): DesignStyleCommitResult;
}
