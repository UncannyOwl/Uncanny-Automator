<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\Controls\Resolvers;

use UncannyPageBuilder\Application\Controls\ControlContext;
use UncannyPageBuilder\Application\Controls\ControlDefinition;
use UncannyPageBuilder\Application\Controls\ControlState;
use UncannyPageBuilder\Application\Controls\ControlStateResolverInterface;

final class SectionActionControlStateResolver implements ControlStateResolverInterface
{
    public function resolve(ControlContext $context, ControlDefinition $definition): ControlState
    {
        $state = ControlState::defaults($definition);
        $allowed = $context->canEdit();
        $isGlobalPart = $context->scope() === 'global_part' && $context->globalPartId() > 0;

        if ($definition->id() === 'section.save_as_reusable' && $isGlobalPart) {
            return $state->withPatch([
                'visible' => false,
                'enabled' => false,
                'reason'  => 'Reusable sections cannot be re-saved as a new reusable from reusable scope.',
            ]);
        }

        return $state->withPatch([
            'visible' => $allowed,
            'enabled' => $allowed,
            'reason'  => $allowed ? null : $this->deniedReason($isGlobalPart),
        ]);
    }

    private function deniedReason(bool $isGlobalPart): string
    {
        return $isGlobalPart
            ? 'You do not have permission to edit sections in this reusable.'
            : 'You do not have permission to edit sections on this page.';
    }
}
