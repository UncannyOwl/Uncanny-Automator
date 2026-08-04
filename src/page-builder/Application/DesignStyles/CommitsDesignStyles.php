<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\DesignStyles;

/**
 * Commits design style changes through the application layer.
 *
 * The control handler depends on this contract so parsing stays separate from
 * validation, grouping, and persistence.
 */
interface CommitsDesignStyles
{
    public function commit(DesignStyleCommitRequest $request): DesignStyleCommitResult;

    public function commitBatch(DesignStyleBatchCommitRequest $request): DesignStyleBatchCommitResult;
}
