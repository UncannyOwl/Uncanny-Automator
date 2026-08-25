<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\DesignStyles;

/**
 * Commits page-owned design changes inside an existing page-source mutation.
 *
 * The caller must refresh the working canvas after the outer mutation commits.
 * This prevents the refresh adapter from opening its own database transaction
 * while the shared page-source transaction is still active.
 */
interface CommitsDesignStylesWithinPageMutation
{
    public function commitBatchWithinPageMutation(
        DesignStyleBatchCommitRequest $request,
    ): DesignStyleBatchCommitResult;
}
