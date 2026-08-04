<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\Reusable;

use UncannyPageBuilder\Domain\Exception\ReusableNotFoundException;
use UncannyPageBuilder\Domain\Reusable\Reusable;

final class UpdateReusableUseCase
{
    public function __construct(private readonly ReusablePortInterface $reusablePort) {}

    public function __invoke(UpdateReusableCommand $command): Reusable
    {
        $reusable = $this->reusablePort->find($command->reusableId());
        if (!$reusable instanceof Reusable) {
            throw new ReusableNotFoundException($command->reusableId());
        }

        return $this->reusablePort->update(
            $reusable->id(),
            $command->title(),
            $command->type(),
        );
    }
}
