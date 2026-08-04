<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\Canvas;

use UncannyPageBuilder\Application\Access\AgentAuthoringAvailabilityInterface;
use UncannyPageBuilder\Domain\Canvas\EmptyCanvasInvitation;

/**
 * Selects the invitation for an empty Page Builder canvas.
 */
final class ResolveEmptyCanvasInvitation
{
    public function __construct(
        private readonly AgentAuthoringAvailabilityInterface $agentAvailability,
    ) {}

    public function __invoke(bool $hasContent): EmptyCanvasInvitation
    {
        if ($hasContent) {
            return EmptyCanvasInvitation::None;
        }

        return $this->agentAvailability->isAvailable()
            ? EmptyCanvasInvitation::StartAgent
            : EmptyCanvasInvitation::SetupAgent;
    }
}
