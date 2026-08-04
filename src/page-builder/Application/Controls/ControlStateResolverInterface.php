<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\Controls;

interface ControlStateResolverInterface
{
    public function resolve(ControlContext $context, ControlDefinition $definition): ControlState;
}
