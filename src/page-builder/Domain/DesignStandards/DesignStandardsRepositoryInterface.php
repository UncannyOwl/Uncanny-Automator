<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\DesignStandards;

/**
 * Persistence contract for sitewide design standards.
 */
interface DesignStandardsRepositoryInterface
{
    /**
     * Load the sitewide design standards profile, or null if none persisted.
     */
    public function load(): ?DesignStandardsProfile;

    /**
     * Persist the sitewide design standards profile.
     */
    public function save(DesignStandardsProfile $profile): void;

    /**
     * Load raw page-level design standards overrides.
     */
    public function loadPageOverrides(int $pageId): ?array;

    /**
     * Save page-level design standards overrides.
     */
    public function savePageOverrides(int $pageId, array $data): void;

    /**
     * Apply a WP filter to the given value and return the result.
     *
     * @param mixed $value
     * @return mixed
     */
    public function applyFilter(string $filterName, mixed $value, mixed ...$args): mixed;
}
