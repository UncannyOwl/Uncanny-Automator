<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\Agent;

use UncannyPageBuilder\Application\Controls\ControlDefinition;
use UncannyPageBuilder\Application\Controls\ControlRegistry;

final class AgentToolRegistryAdapter
{
    public function __construct(
        private readonly ControlRegistry $registry,
        private readonly string $toolsDir,
    ) {}

    /**
     * @return array{schema_version: string, tools: list<array<string, mixed>>}
     */
    public function contract(string $schemaVersion): array
    {
        $manifestTools = $this->manifestToolsByName();
        $registryTools = $this->registryToolsByName($manifestTools);

        $tools = [];
        $used = [];

        foreach ($manifestTools as $name => $tool) {
            $tools[] = $registryTools[$name] ?? $tool;
            $used[$name] = true;
        }

        foreach ($registryTools as $name => $tool) {
            if (!isset($used[$name])) {
                $tools[] = $tool;
            }
        }

        return [
            'schema_version' => $schemaVersion,
            'tools'          => array_values($tools),
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function registryToolsByName(array $manifestTools): array
    {
        $tools = [];

        foreach ($this->registry->all() as $definition) {
            if ($definition->agentExposure() === 'hidden') {
                continue;
            }

            $name = $definition->agentName();
            if ($name === null || $name === '') {
                continue;
            }

            $tools[$name] = $this->toolFromControl($definition, $manifestTools[$name] ?? null);
        }

        return $tools;
    }

    /**
     * @param array<string, mixed>|null $manifest
     * @return array<string, mixed>
     */
    private function toolFromControl(ControlDefinition $definition, ?array $manifest): array
    {
        $tool = $manifest ?? [];
        $name = $definition->agentName();

        $tool['name'] = $name;
        $tool['description'] = $definition->agentDescription();
        $tool['parameters'] = $definition->agentInputSchema() ?? [];
        $tool['output'] = $definition->agentOutputSchema() ?? [];
        $tool['auto_approve'] = $definition->agentAutoApprove();
        // The control registry owns current exposure. Manifests supply transport
        // details and must not downgrade write-class controls.
        $tool['group'] = $definition->agentExposure();

        if ($definition->agentRequiresReadBeforeWrite()) {
            $tool['requires_read_before_write'] = true;
        }

        return $tool;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function manifestToolsByName(): array
    {
        $tools = [];

        foreach (glob(rtrim($this->toolsDir, '/') . '/*.json') ?: [] as $file) {
            $json = file_get_contents($file);
            if ($json === false) {
                continue;
            }

            $tool = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($tool) || !isset($tool['name']) || !is_string($tool['name'])) {
                continue;
            }

            if (($tool['agent_visible'] ?? null) === false) {
                continue;
            }

            $tools[$tool['name']] = $tool;
        }

        return $tools;
    }
}
