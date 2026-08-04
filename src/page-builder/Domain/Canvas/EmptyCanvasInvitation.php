<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\Canvas;

/**
 * The primary invitation for an empty Page Builder canvas.
 */
enum EmptyCanvasInvitation: string
{
    case None = 'none';
    case StartAgent = 'start_agent';
    case SetupAgent = 'setup_agent';
}
