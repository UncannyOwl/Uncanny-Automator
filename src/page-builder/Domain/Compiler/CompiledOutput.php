<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\Compiler;

final class CompiledOutput
{
    public function __construct(
        private readonly string $seoHtml,
        private readonly string $minifiedCss,
    ) {}

    public function seoHtml(): string     { return $this->seoHtml; }
    public function minifiedCss(): string { return $this->minifiedCss; }
}
