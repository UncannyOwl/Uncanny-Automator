<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\i18\strings;

final class RequestProgressStrings
{
    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return [
            'loading' => _x('Loading…', 'Page Builder', 'uncanny-automator'),
        ];
    }
}
