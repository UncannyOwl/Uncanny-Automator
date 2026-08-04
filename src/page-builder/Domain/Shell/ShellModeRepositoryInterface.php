<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\Shell;

interface ShellModeRepositoryInterface
{
    public function getSiteDefault(): ShellMode;

    public function setSiteDefault(ShellMode $mode): void;

    public function getForPage(int $pageId): ?ShellMode;

    public function setForPage(int $pageId, ShellMode $mode): void;

    public function clearPageOverride(int $pageId): void;
}
