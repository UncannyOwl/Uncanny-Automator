<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\Reusable;

use UncannyPageBuilder\Domain\Reusable\Reusable;

final class CreateReusableUseCase
{
    public function __construct(private readonly ReusablePortInterface $reusablePort) {}

    public function __invoke(CreateReusableCommand $command): Reusable
    {
        return $this->reusablePort->create($command->title(), $command->type());
    }
}
