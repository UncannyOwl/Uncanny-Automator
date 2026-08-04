<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\Binding;

/**
 * Static artifact safety declared by each dynamic binding.
 */
enum BindingStaticSafety: string
{
    case StaticSafe = 'static_safe';
    case PublicRequestSafe = 'public_request_safe';
    case RequestSensitive = 'request_sensitive';
    case NotStatic = 'not_static';

    public function canFreeze(): bool
    {
        return $this === self::StaticSafe || $this === self::PublicRequestSafe;
    }
}
