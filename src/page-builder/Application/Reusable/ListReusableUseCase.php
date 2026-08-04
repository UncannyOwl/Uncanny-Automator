<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\Reusable;

final class ListReusableUseCase
{
    public function __construct(private readonly ReusablePortInterface $reusablePort) {}

    /**
     * @return list<\UncannyPageBuilder\Domain\Reusable\Reusable>
     */
    public function __invoke(ListReusableQuery $query): array
    {
        return $this->reusablePort->list($query->type());
    }
}
