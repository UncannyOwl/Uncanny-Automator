<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\DesignStyles;

/**
 * Commits element-scoped style changes into header/footer/global-part source.
 */
interface GlobalPartElementStyleCommitterInterface
{
    public function commit(DesignStyleCommitRequest $request): DesignStyleCommitResult;

    public function prepare(DesignStyleCommitRequest $request): GlobalPartElementStyleCommitPlan|DesignStyleCommitResult;

    public function apply(GlobalPartElementStyleCommitPlan $plan): DesignStyleCommitResult;
}
