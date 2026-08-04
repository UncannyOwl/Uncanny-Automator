<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\Controls;

use UncannyPageBuilder\Domain\Controls\CanvasArea;
use UncannyPageBuilder\Domain\Controls\ControlClientHint;

final class ControlRegistry
{
    private const RESERVED_PREFIXES = [
        'nav',
        'page',
        'history',
        'section',
        'viewport',
        'tools',
        'shell',
        'design',
        'design_standards',
        'global_part',
        'branding',
        'binding',
    ];

    /** @var array<string, ControlDefinition> */
    private array $controls = [];

    public function register(ControlDefinition $definition): void
    {
        $this->registerDefinition($definition, false);
    }

    public function registerCore(ControlDefinition $definition): void
    {
        $this->registerDefinition($definition, true);
    }

    private function registerDefinition(ControlDefinition $definition, bool $core): void
    {
        if (isset($this->controls[$definition->id()])) {
            throw new \InvalidArgumentException(sprintf('Control "%s" is already registered.', $definition->id()));
        }

        $this->validateControlId($definition, $core);
        $this->validateCanvasArea($definition);
        $this->validateClientHint($definition);
        $this->validatePresentation($definition);
        $this->validateAgentExposure($definition);
        $this->validateHandler($definition);
        $this->validateStateResolver($definition);

        $this->controls[$definition->id()] = $definition;
    }

    public function has(string $id): bool
    {
        return isset($this->controls[$id]);
    }

    public function get(string $id): ?ControlDefinition
    {
        return $this->controls[$id] ?? null;
    }

    /** @return ControlDefinition[] */
    public function all(): array
    {
        $controls = array_values($this->controls);
        usort($controls, self::sorter(...));

        return $controls;
    }

    /** @return ControlDefinition[] */
    public function forContext(ControlContext $context): array
    {
        return array_values(array_filter(
            $this->all(),
            static fn (ControlDefinition $control): bool => $control->supportsContext($context),
        ));
    }

    private static function sorter(ControlDefinition $a, ControlDefinition $b): int
    {
        $zoneOrder = array_flip(\UncannyPageBuilder\Domain\Controls\ControlZone::orderedValues());
        $zoneCompare = ($zoneOrder[$a->zone()->value] ?? 999) <=> ($zoneOrder[$b->zone()->value] ?? 999);
        if ($zoneCompare !== 0) {
            return $zoneCompare;
        }

        $orderCompare = $a->order() <=> $b->order();
        return $orderCompare !== 0 ? $orderCompare : strcmp($a->id(), $b->id());
    }

    private function validateControlId(ControlDefinition $definition, bool $core): void
    {
        $prefix = strtok($definition->id(), '.');
        $reserved = is_string($prefix) && in_array($prefix, self::RESERVED_PREFIXES, true);

        if ($core) {
            if (!$reserved) {
                throw new \InvalidArgumentException(sprintf('Core control "%s" must use a reserved core prefix.', $definition->id()));
            }
            return;
        }

        if ($reserved) {
            throw new \InvalidArgumentException(sprintf('External control "%s" uses reserved core prefix "%s".', $definition->id(), $prefix));
        }

        if (preg_match('/^[a-z][a-z0-9_-]*\.[a-z][a-z0-9_-]*\.[a-z][a-z0-9_.-]*$/', $definition->id()) !== 1) {
            throw new \InvalidArgumentException(sprintf('External control "%s" must be vendor-prefixed, for example vendor.feature.action.', $definition->id()));
        }
    }

    private function validateCanvasArea(ControlDefinition $definition): void
    {
        $area = $definition->canvasArea();
        if (!in_array($area, CanvasArea::values(), true)) {
            throw new \InvalidArgumentException(sprintf('Control "%s" uses unsupported canvas_area "%s".', $definition->id(), $area));
        }
    }

    private function validateClientHint(ControlDefinition $definition): void
    {
        $hint = $definition->clientHint();
        if ($hint === null) {
            return;
        }

        if (!in_array($hint, ControlClientHint::values(), true)) {
            throw new \InvalidArgumentException(sprintf('Control "%s" uses unsupported client_hint "%s".', $definition->id(), $hint));
        }
    }

    private function validatePresentation(ControlDefinition $definition): void
    {
        $presentation = $definition->presentation();
        if ($presentation === []) {
            return;
        }

        $surface = $presentation['surface'] ?? null;
        if ($surface !== null && !in_array($surface, ['modal', 'panel', 'admin_header'], true)) {
            throw new \InvalidArgumentException(sprintf('Control "%s" uses unsupported presentation surface.', $definition->id()));
        }

        $component = $presentation['component'] ?? null;
        if ($component !== null && (!is_string($component) || $component === '')) {
            throw new \InvalidArgumentException(sprintf('Control "%s" uses an invalid presentation component.', $definition->id()));
        }

        $hint = $definition->clientHint();
        if (is_string($surface) && is_string($hint) && str_starts_with($hint, 'open_') && $hint !== 'open_' . $surface) {
            throw new \InvalidArgumentException(sprintf('Control "%s" has conflicting client_hint and presentation surface.', $definition->id()));
        }
    }

    private function validateAgentExposure(ControlDefinition $definition): void
    {
        $exposure = $definition->agentExposure();
        if (!in_array($exposure, ['hidden', 'read', 'write'], true)) {
            throw new \InvalidArgumentException(sprintf('Control "%s" uses unsupported agent_exposure "%s".', $definition->id(), $exposure));
        }

        if ($exposure === 'hidden') {
            return;
        }

        if (
            $definition->agentName() === null
            || $definition->agentName() === ''
            || $definition->agentDescription() === null
            || $definition->agentDescription() === ''
            || $definition->agentInputSchema() === null
            || $definition->agentOutputSchema() === null
        ) {
            throw new \InvalidArgumentException(sprintf('Agent-exposed control "%s" must declare agent metadata and schemas.', $definition->id()));
        }
    }

    private function validateHandler(ControlDefinition $definition): void
    {
        $handler = $definition->handler();
        if ($handler instanceof ControlHandlerInterface) {
            return;
        }

        if ($handler === null || is_callable($handler)) {
            return;
        }

        if (!is_string($handler) || !class_exists($handler)) {
            throw new \InvalidArgumentException(sprintf('Handler for control "%s" does not exist.', $definition->id()));
        }

        if (!is_a($handler, ControlHandlerInterface::class, true)) {
            throw new \InvalidArgumentException(sprintf('Handler for control "%s" must implement ControlHandlerInterface.', $definition->id()));
        }
    }

    private function validateStateResolver(ControlDefinition $definition): void
    {
        $resolver = $definition->stateResolver();
        if ($resolver instanceof ControlStateResolverInterface) {
            return;
        }

        if ($resolver === null || is_callable($resolver)) {
            return;
        }

        if (!is_string($resolver) || !class_exists($resolver)) {
            throw new \InvalidArgumentException(sprintf('State resolver for control "%s" does not exist.', $definition->id()));
        }

        if (!is_a($resolver, ControlStateResolverInterface::class, true)) {
            throw new \InvalidArgumentException(sprintf('State resolver for control "%s" must implement ControlStateResolverInterface.', $definition->id()));
        }
    }
}
