<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\Export;

final class StaticRenderingResult
{
    public function __construct(
        private readonly string $html,
        private readonly StaticRenderingReport $report,
    ) {
    }

    public function html(): string
    {
        return $this->html;
    }

    public function report(): StaticRenderingReport
    {
        return $this->report;
    }
}
