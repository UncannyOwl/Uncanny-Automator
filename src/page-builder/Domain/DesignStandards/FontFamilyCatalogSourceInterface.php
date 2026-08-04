<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\DesignStandards;

interface FontFamilyCatalogSourceInterface
{
    /** @return list<string> */
    public function googleFontFamilies(): array;

    /** @return list<string> */
    public function customFontFamilies(): array;
}
