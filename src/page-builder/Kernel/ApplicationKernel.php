<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Kernel;

use UncannyPageBuilder\Kernel\Contracts\ServiceProviderInterface;

final class ApplicationKernel
{
    private Container $container;

    /** @var ServiceProviderInterface[] */
    private array $providers = [];

    public function __construct()
    {
        $this->container = new Container();
    }

    /**
     * @param class-string<ServiceProviderInterface>[] $providers
     */
    public function bootstrap(array $providers): void
    {
        // Instantiate providers
        foreach ($providers as $providerClass) {
            $this->providers[] = new $providerClass();
        }

        // 1. Register all services into the container
        foreach ($this->providers as $provider) {
            $provider->register($this->container);
        }

        // 2. Boot all services (e.g. register hooks, start processes)
        foreach ($this->providers as $provider) {
            $provider->boot($this->container);
        }
    }

    public function getContainer(): Container
    {
        return $this->container;
    }
}
