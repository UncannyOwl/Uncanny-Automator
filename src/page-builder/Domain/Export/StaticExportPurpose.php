<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\Export;

/**
 * Selects whether an export must stand alone or can use WordPress at runtime.
 */
enum StaticExportPurpose
{
    case Portable;
    case Publication;
}
