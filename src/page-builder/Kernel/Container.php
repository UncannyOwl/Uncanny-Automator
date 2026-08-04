<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Kernel;

final class Container
{
    /** @var array<string, object> */
    private array $services = [];

    /** @var array<string, \Closure> */
    private array $factories = [];

    public function set(string $id, object $service): void
    {
        $this->services[$id] = $service;
    }

    /**
     * Register a lazy factory. The closure receives this Container and
     * is called at most once — on first get().
     */
    public function factory(string $id, \Closure $factory): void
    {
        $this->factories[$id] = $factory;
    }

    public function get(string $id): object
    {
        if (!isset($this->services[$id])) {
            if (isset($this->factories[$id])) {
                $this->services[$id] = ($this->factories[$id])($this);
                unset($this->factories[$id]);
            } else {
                throw new \RuntimeException(sprintf('Service %s not found in container.', $id));
            }
        }

        return $this->services[$id];
    }

    /**
     * Typed get — identical to get() but with a generic return for IDE support.
     *
     * @template T of object
     * @param class-string<T> $id
     * @return T
     */
    public function typed(string $id): object
    {
        /** @var T */
        return $this->get($id);
    }

    public function has(string $id): bool
    {
        return isset($this->services[$id]) || isset($this->factories[$id]);
    }
}
