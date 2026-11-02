<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\Agent;

/** Records native callback identity for the selected external draft contract. */
interface SharedRestOperationRegistryInterface
{
    public function remember(string $route, string $method, callable $callback, callable $permission, string $tool): void;
}
