<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Kernel\Contracts;

use UncannyPageBuilder\Kernel\Container;

interface ServiceProviderInterface
{
    /**
     * Register services into the container.
     */
    public function register(Container $container): void;

    /**
     * Boot the services, register hooks, etc.
     */
    public function boot(Container $container): void;
}
