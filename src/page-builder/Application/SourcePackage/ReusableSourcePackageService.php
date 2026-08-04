<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\SourcePackage;

use UncannyPageBuilder\Application\GlobalPartService;
use UncannyPageBuilder\Application\PageJavaScriptRuntimeService;
use UncannyPageBuilder\Domain\GlobalPart\GlobalPartCreationCleanupInterface;
use UncannyPageBuilder\Domain\GlobalPart\GlobalPartType;
use UncannyPageBuilder\Domain\SourcePackage\ReusableSourcePackage;

/**
 * Imports Page Builder-owned reusable/global-part source packages.
 */
final class ReusableSourcePackageService
{
    public function __construct(
        private readonly GlobalPartService $globalParts,
        private readonly PageJavaScriptRuntimeService $javaScriptRuntime,
        private readonly ?GlobalPartCreationCleanupInterface $creationCleanup = null,
    ) {}

    /**
     * @param array<string, mixed> $payload
     */
    public function validateReusable(array $payload, ?GlobalPartType $requiredType = null): ReusableSourcePackage
    {
        return ReusableSourcePackage::fromImportPayload($payload, $requiredType);
    }

    /**
     * Create a new reusable from an uploaded package.
     *
     * Reusable imports are always create-only. Updating an existing reusable
     * would affect every page that uses it, so replacement needs a separate,
     * explicit conflict UX.
     *
     * @param array<string, mixed> $payload
     * @return array{id: int, title: string, type: string, warnings: string[]}
     */
    public function importReusable(array $payload, ?GlobalPartType $requiredType = null): array
    {
        $package = $this->validateReusable($payload, $requiredType);
        $result = $this->globalParts->create(
            $package->title(),
            $package->section(),
            $package->type()->value,
        );

        $globalPartId = (int) $result['id'];

        try {
            if ($package->customJavaScript() !== '') {
                $this->javaScriptRuntime->replaceForGlobalPart($globalPartId, $package->customJavaScript());
            }
        } catch (\Throwable $failure) {
            $this->rethrowAfterCreationCleanup($globalPartId, $failure);
        }

        return $result;
    }

    /**
     * Custom JavaScript is part of the imported reusable package. If that
     * final write fails, compensate the already-created global part so the
     * failed import remains safe to retry.
     */
    private function rethrowAfterCreationCleanup(int $globalPartId, \Throwable $failure): never
    {
        if (!$this->creationCleanup instanceof GlobalPartCreationCleanupInterface) {
            throw $failure;
        }

        try {
            $this->creationCleanup->removeCreatedGlobalPart($globalPartId);
        } catch (\Throwable $cleanupFailure) {
            throw new \RuntimeException(
                "Reusable import failed, and its incomplete global part could not be removed: {$cleanupFailure->getMessage()}",
                0,
                $failure,
            );
        }

        throw $failure;
    }
}
