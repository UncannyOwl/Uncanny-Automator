<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\Controls;

enum CanvasArea: string
{
    case TopBarLeft = 'top_bar.left';
    case TopBarCenter = 'top_bar.center';
    case TopBarRight = 'top_bar.right';
    case HistoryBar = 'history_bar';
    case PageIdentity = 'page_identity';
    case ViewportSwitcher = 'viewport_switcher';
    case SectionBadge = 'section.badge';
    case SectionContextMenu = 'section.context_menu';
    case SectionFloatingToolbar = 'section.floating_toolbar';
    case InspectorPanel = 'inspector.panel';
    case ModalHost = 'modal.host';
    case Hidden = 'hidden';

    /** @return string[] */
    public static function values(): array
    {
        return array_map(static fn (self $area): string => $area->value, self::cases());
    }
}
