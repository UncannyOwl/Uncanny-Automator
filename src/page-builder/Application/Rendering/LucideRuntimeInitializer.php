<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\Rendering;

/**
 * One load-safe initializer shared by portable and public artifact rendering.
 */
final class LucideRuntimeInitializer
{
    public static function script(): string
    {
        return '(function(){'
            . 'function renderLucideIcons(){'
            . 'if(window.lucide&&typeof window.lucide.createIcons==="function"){'
            . 'window.lucide.createIcons({icons:window.lucide.icons});'
            . '}'
            . '}'
            . 'renderLucideIcons();'
            . 'window.addEventListener("load",renderLucideIcons,{once:true});'
            . '})();';
    }
}
