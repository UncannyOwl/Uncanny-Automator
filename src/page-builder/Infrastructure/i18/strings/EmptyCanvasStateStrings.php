<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\i18\strings;

final class EmptyCanvasStateStrings
{
    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return [
            'start_chatting' => _x('Start chatting', 'Page Builder', 'uncanny-automator'),
        ];
    }
}
