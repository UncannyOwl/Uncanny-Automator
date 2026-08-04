<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\Operation;

/**
 * Combines the five independent Page Builder operation questions.
 *
 * The policy deliberately knows no WordPress APIs, persistence mechanism,
 * request type, or lock implementation. Those boundaries supply facts; this
 * domain rule decides whether all requirements for the operation are met.
 */
final class PageBuilderOperationPolicy
{
    public function allows(
        PageBuilderOperation $operation,
        PageBuilderOperationFacts $facts,
    ): bool {
        $administratorAllows = !$operation->requiresAdministratorIntent()
            || $facts->administratorEnabledPostType();
        $runtimeAllows = !$operation->requiresRuntimeSupport()
            || $facts->runtimeSupportsPostType();

        return $administratorAllows
            && $runtimeAllows
            && $facts->hasRequiredAuthority()
            && $facts->actorIsAllowed()
            && $facts->isSafeNow();
    }
}
