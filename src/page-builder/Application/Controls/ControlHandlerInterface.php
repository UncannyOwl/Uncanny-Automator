<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\Controls;

interface ControlHandlerInterface
{
    public function __invoke(ControlInvokeRequest $request): ControlInvokeResult;
}
