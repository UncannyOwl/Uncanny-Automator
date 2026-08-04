<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\Reusable;

use UncannyPageBuilder\Domain\Exception\ReusableNotFoundException;
use UncannyPageBuilder\Domain\Reusable\Reusable;

final class DeleteReusableUseCase
{
    public function __construct(private readonly ReusablePortInterface $reusablePort) {}

    public function __invoke(DeleteReusableCommand $command): DeleteReusableResult
    {
        $reusable = $this->reusablePort->find($command->reusableId());
        if (!$reusable instanceof Reusable) {
            throw new ReusableNotFoundException($command->reusableId());
        }

        return $this->reusablePort->delete(
            $reusable->id(),
            $command->forceDelete(),
        );
    }
}
