<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\Export;

use UncannyPageBuilder\Domain\Exception\DeactivationFallbackCompilationFailed;

/**
 * Proof that fallback compilation removed exactly the regions it detected.
 */
final class DynamicBindingOmissionProof
{
    /**
     * @param list<string> $detectedBindingIds
     * @param list<string> $removedBindingIds
     */
    public function __construct(
        private readonly array $detectedBindingIds,
        private readonly array $removedBindingIds,
    ) {
        if (
            !array_is_list($detectedBindingIds)
            || !array_is_list($removedBindingIds)
            || $detectedBindingIds !== $removedBindingIds
        ) {
            throw new DeactivationFallbackCompilationFailed(
                'Deactivation fallback dynamic binding removal evidence does not match detection.',
            );
        }
    }

    /** @return list<string> */
    public function bindingIds(): array
    {
        return $this->removedBindingIds;
    }

    public function count(): int
    {
        return count($this->removedBindingIds);
    }
}
