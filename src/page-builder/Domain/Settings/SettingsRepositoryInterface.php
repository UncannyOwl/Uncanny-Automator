<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\Settings;

/**
 * Repository for the sitewide Settings aggregate.
 */
interface SettingsRepositoryInterface
{
    public function load(): Settings;

    public function save(Settings $settings): void;

    /**
     * Apply one settings-row mutation against the current stored aggregate.
     *
     * @param callable(Settings): Settings $mutator
     */
    public function mutate(callable $mutator): Settings;
}
