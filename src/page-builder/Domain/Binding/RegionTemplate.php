<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\Binding;

/**
 * Whether a dynamic region's first child element is an authored template
 * the renderer consumes (card loops, wp_menu's <ul>).
 */
enum RegionTemplate: string
{
    case None = 'none';
    case FirstChild = 'first_child';
}
