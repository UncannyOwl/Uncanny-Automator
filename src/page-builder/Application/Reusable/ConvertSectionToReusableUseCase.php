<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\Reusable;

use UncannyPageBuilder\Domain\Reusable\Reusable;

final class ConvertSectionToReusableUseCase
{
    public function __construct(private readonly ReusablePortInterface $reusablePort) {}

    public function __invoke(ConvertSectionToReusableCommand $command): Reusable
    {
        return $this->reusablePort->convertSection(
            $command->sectionId(),
            $command->title(),
            $command->type(),
        );
    }
}
