<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\Controls;

use UncannyPageBuilder\Domain\Controls\CanvasArea;
use UncannyPageBuilder\Domain\Controls\ControlZone;
use UncannyPageBuilder\Kernel\Container;

final class ControlStateService
{
    private const SCHEMA_VERSION = 'editor-controls.v1';

    public function __construct(
        private readonly ControlRegistry $registry,
        private readonly Container $container,
    ) {}

    /** @return array<string, mixed> */
    public function build(ControlContext $context): array
    {
        $controls = [];

        foreach ($this->registry->forContext($context) as $definition) {
            $controls[] = $this->serializeControl($definition, $context);
        }

        $response = [
            'schema_version' => self::SCHEMA_VERSION,
            'context'        => $context->toArray(),
            'zones'          => ControlZone::orderedValues(),
            'controls'       => $controls,
        ];

        $filtered = apply_filters('uncanny_page_builder_controls_response', $response, $context);
        if (is_array($filtered)) {
            return $filtered;
        }

        return $response;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function controlsForArea(ControlContext $context, string|CanvasArea $area, bool $visibleOnly = true): array
    {
        $payload = $this->build($context);
        $controls = is_array($payload['controls'] ?? null) ? $payload['controls'] : [];

        return self::filterControlsForArea($controls, $area, $visibleOnly);
    }

    /**
     * @param array<int, array<string, mixed>> $controls
     * @return array<int, array<string, mixed>>
     */
    public static function filterControlsForArea(array $controls, string|CanvasArea $area, bool $visibleOnly = true): array
    {
        $areaValue = $area instanceof CanvasArea ? $area->value : $area;
        $filtered = [];

        foreach ($controls as $index => $control) {
            if (($control['canvas_area'] ?? null) !== $areaValue) {
                continue;
            }

            if ($visibleOnly && !(bool) ($control['visible'] ?? false)) {
                continue;
            }

            $filtered[] = [
                'control' => $control,
                'index' => $index,
            ];
        }

        usort(
            $filtered,
            static fn (array $a, array $b): int => ((int) (($a['control']['order'] ?? 0)) <=> (int) (($b['control']['order'] ?? 0)))
                ?: ((int) $a['index'] <=> (int) $b['index']),
        );

        return array_map(
            static fn (array $entry): array => $entry['control'],
            $filtered,
        );
    }

    /** @return array<string, mixed> */
    public function serializeControl(ControlDefinition $definition, ControlContext $context): array
    {
        $state = $this->resolveState($definition, $context);
        $control = array_merge($definition->toClientArray(), $state->toArray());

        $filtered = apply_filters('uncanny_page_builder_control_properties', $control, $definition->id(), $context);
        if (is_array($filtered)) {
            $filtered['id'] = $definition->id();
            unset($filtered['handler'], $filtered['capability'], $filtered['registered_capability'], $filtered['state_resolver']);
            return $filtered;
        }

        return $control;
    }

    public function resolveState(ControlDefinition $definition, ControlContext $context): ControlState
    {
        $resolver = $definition->stateResolver();
        if ($resolver === null) {
            return ControlState::defaults($definition);
        }

        if ($resolver instanceof ControlStateResolverInterface) {
            return $resolver->resolve($context, $definition);
        }

        if (is_callable($resolver)) {
            $state = $resolver($context, $definition);
            if (!$state instanceof ControlState) {
                throw new \RuntimeException(sprintf('State resolver for "%s" must return ControlState.', $definition->id()));
            }

            return $state;
        }

        if (!is_string($resolver)) {
            throw new \RuntimeException(sprintf('State resolver for "%s" is invalid.', $definition->id()));
        }

        $instance = $this->container->has($resolver)
            ? $this->container->typed($resolver)
            : new $resolver();

        if (!$instance instanceof ControlStateResolverInterface) {
            throw new \RuntimeException(sprintf('State resolver for "%s" must implement ControlStateResolverInterface.', $definition->id()));
        }

        return $instance->resolve($context, $definition);
    }
}
