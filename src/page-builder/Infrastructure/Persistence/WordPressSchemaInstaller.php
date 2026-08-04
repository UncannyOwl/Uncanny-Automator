<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\Persistence;

use UncannyPageBuilder\Application\System\SchemaInstallerInterface;

final class WordPressSchemaInstaller implements SchemaInstallerInterface
{
    public function ensureCurrentSite(): void
    {
        SchemaManager::ensureSchema();
    }

    public function installCurrentSite(): void
    {
        SchemaManager::install(false);
    }
}
