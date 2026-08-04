<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\Controls;

enum ControlClientHint: string
{
    case OpenPanel = 'open_panel';
    case OpenModal = 'open_modal';
    case MediaPicker = 'media_picker';
    case Contenteditable = 'contenteditable';
    case DragReorder = 'drag_reorder';
    case ViewportFrame = 'viewport_frame';
    case ExternalUrl = 'external_url';
    case DownloadArtifacts = 'download_artifacts';
    case DownloadSourcePackage = 'download_source_package';
    case ImportSourcePackage = 'import_source_package';
    case SwitchToWordPress = 'switch_to_wordpress';

    /** @return string[] */
    public static function values(): array
    {
        return array_map(static fn (self $hint): string => $hint->value, self::cases());
    }
}
