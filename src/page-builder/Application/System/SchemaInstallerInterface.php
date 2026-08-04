<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\System;

/**
 * Inward-facing persistence readiness port.
 *
 * Kernel and WordPress lifecycle code can require the Page Builder schema
 * without depending on dbDelta, wpdb, or multisite switching details.
 */
interface SchemaInstallerInterface
{
    public function ensureCurrentSite(): void;

    public function installCurrentSite(): void;
}
