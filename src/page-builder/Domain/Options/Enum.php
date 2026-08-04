<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\Options;

/**
 * Canonical inventory of plugin-owned wp_options keys.
 *
 * Product settings now live in one serialized row. Operational/plugin-state
 * rows stay separate because they are not part of the user-managed Settings
 * aggregate.
 */
enum Enum: string
{
    case ShellMode = 'uncanny_page_builder_shell_mode';
    case WorkingCanvasRefreshQueue = 'uncanny_page_builder_working_canvas_refresh_queue';
    case WorkingCanvasInputVersion = 'uncanny_page_builder_working_canvas_input_version';
    case DatabaseVersion = 'uncanny_page_builder_db_version';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(
            static fn(self $option): string => $option->value,
            self::cases(),
        );
    }
}
