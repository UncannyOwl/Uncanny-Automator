<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\Controls;

enum ControlType: string
{
    case Trigger = 'trigger';
    case Toggle = 'toggle';
    case Select = 'select';
    case Input = 'input';
    case Display = 'display';
    case Group = 'group';
}
