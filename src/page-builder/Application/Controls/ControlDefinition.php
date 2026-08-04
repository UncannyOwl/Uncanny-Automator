<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\Controls;

use UncannyPageBuilder\Domain\Controls\CanvasArea;
use UncannyPageBuilder\Domain\Controls\ControlType;
use UncannyPageBuilder\Domain\Controls\ControlZone;

final class ControlDefinition
{
    /** @var string[] */
    private readonly array $contexts;
    private readonly string $canvasArea;
    /** @var array<string, mixed> */
    private readonly array $presentation;

    /**
     * @param string[] $contexts
     * @param class-string<ControlHandlerInterface>|callable|null $handler
     * @param class-string<ControlStateResolverInterface>|callable|null $stateResolver
     * @param array<int, array<string, mixed>>|null $options
     * @param array<string, mixed> $presentation
     * @param array<string, mixed>|null $agentInputSchema
     * @param array<string, mixed>|null $agentOutputSchema
     */
    public function __construct(
        private readonly string $id,
        private readonly string $label,
        private readonly string $description,
        private readonly ControlZone $zone,
        private readonly int $order,
        private readonly ControlType $type,
        string|CanvasArea $canvasArea = CanvasArea::Hidden,
        private readonly ?string $icon = null,
        private readonly string $variant = 'secondary',
        private readonly ?string $capability = null,
        array $contexts = ['canvas'],
        private readonly ?string $clientHint = null,
        private readonly string $agentExposure = 'hidden',
        private readonly mixed $handler = null,
        private readonly mixed $stateResolver = null,
        private readonly bool $local = false,
        private readonly ?string $keybinding = null,
        private readonly ?string $confirm = null,
        private readonly mixed $defaultValue = null,
        private readonly ?array $options = null,
        array $presentation = [],
        private readonly ?string $agentName = null,
        private readonly ?string $agentDescription = null,
        private readonly ?array $agentInputSchema = null,
        private readonly ?array $agentOutputSchema = null,
        private readonly bool $agentAutoApprove = false,
        private readonly bool $agentRequiresReadBeforeWrite = false,
        private readonly bool $writesEditorState = false,
        private readonly bool $editorStateClassificationExplicit = false,
    ) {
        if ($id === '' || preg_match('/^[a-z][a-z0-9_.-]*$/', $id) !== 1) {
            throw new \InvalidArgumentException('Control id must use lowercase letters, numbers, dots, dashes, or underscores.');
        }

        $this->contexts = array_values($contexts);
        $this->canvasArea = $canvasArea instanceof CanvasArea ? $canvasArea->value : $canvasArea;
        $this->presentation = $presentation;
    }

    /** @param array<string, mixed> $data */
    public static function make(array $data): self
    {
        return new self(
            id: (string) $data['id'],
            label: (string) $data['label'],
            description: (string) ($data['description'] ?? ''),
            zone: $data['zone'] instanceof ControlZone ? $data['zone'] : ControlZone::from((string) $data['zone']),
            canvasArea: $data['canvas_area'] ?? CanvasArea::Hidden,
            order: (int) ($data['order'] ?? 100),
            type: $data['type'] instanceof ControlType ? $data['type'] : ControlType::from((string) $data['type']),
            icon: isset($data['icon']) ? (string) $data['icon'] : null,
            variant: (string) ($data['variant'] ?? 'secondary'),
            capability: isset($data['capability']) ? (string) $data['capability'] : null,
            contexts: is_array($data['contexts'] ?? null) ? $data['contexts'] : ['canvas'],
            clientHint: isset($data['client_hint']) ? (string) $data['client_hint'] : null,
            agentExposure: (string) ($data['agent_exposure'] ?? 'hidden'),
            handler: $data['handler'] ?? null,
            stateResolver: $data['state_resolver'] ?? null,
            local: (bool) ($data['local'] ?? false),
            keybinding: isset($data['keybinding']) ? (string) $data['keybinding'] : null,
            confirm: isset($data['confirm']) ? (string) $data['confirm'] : null,
            defaultValue: $data['default_value'] ?? null,
            options: isset($data['options']) && is_array($data['options']) ? $data['options'] : null,
            presentation: isset($data['presentation']) && is_array($data['presentation']) ? $data['presentation'] : [],
            agentName: isset($data['agent_name']) ? (string) $data['agent_name'] : null,
            agentDescription: isset($data['agent_description']) ? (string) $data['agent_description'] : null,
            agentInputSchema: isset($data['agent_input_schema']) && is_array($data['agent_input_schema']) ? $data['agent_input_schema'] : null,
            agentOutputSchema: isset($data['agent_output_schema']) && is_array($data['agent_output_schema']) ? $data['agent_output_schema'] : null,
            agentAutoApprove: (bool) ($data['agent_auto_approve'] ?? false),
            agentRequiresReadBeforeWrite: (bool) ($data['agent_requires_read_before_write'] ?? false),
            writesEditorState: (bool) ($data['writes_editor_state'] ?? false),
            editorStateClassificationExplicit: array_key_exists('writes_editor_state', $data),
        );
    }

    public function id(): string
    {
        return $this->id;
    }

    public function label(): string
    {
        return $this->label;
    }

    public function description(): string
    {
        return $this->description;
    }

    public function zone(): ControlZone
    {
        return $this->zone;
    }

    public function canvasArea(): string
    {
        return $this->canvasArea;
    }

    public function order(): int
    {
        return $this->order;
    }

    public function capability(): ?string
    {
        return $this->capability;
    }

    public function clientHint(): ?string
    {
        return $this->clientHint;
    }

    /** @return array<string, mixed> */
    public function presentation(): array
    {
        return $this->presentation;
    }

    public function agentExposure(): string
    {
        return $this->agentExposure;
    }

    public function agentName(): ?string
    {
        return $this->agentName;
    }

    public function agentDescription(): ?string
    {
        return $this->agentDescription;
    }

    /** @return array<string, mixed>|null */
    public function agentInputSchema(): ?array
    {
        return $this->agentInputSchema;
    }

    /** @return array<string, mixed>|null */
    public function agentOutputSchema(): ?array
    {
        return $this->agentOutputSchema;
    }

    public function agentAutoApprove(): bool
    {
        return $this->agentAutoApprove;
    }

    public function agentRequiresReadBeforeWrite(): bool
    {
        return $this->agentRequiresReadBeforeWrite;
    }

    public function writesEditorState(): bool
    {
        return $this->writesEditorState;
    }

    public function hasExplicitEditorStateClassification(): bool
    {
        return $this->editorStateClassificationExplicit;
    }

    /** @return string[] */
    public function contexts(): array
    {
        return $this->contexts;
    }

    public function supportsContext(ControlContext $context): bool
    {
        return in_array($context->surface(), $this->contexts, true);
    }

    /** @return class-string<ControlHandlerInterface>|callable|null */
    public function handler(): mixed
    {
        return $this->handler;
    }

    /** @return class-string<ControlStateResolverInterface>|callable|null */
    public function stateResolver(): mixed
    {
        return $this->stateResolver;
    }

    public function defaultValue(): mixed
    {
        return $this->defaultValue;
    }

    /** @return array<int, array<string, mixed>>|null */
    public function options(): ?array
    {
        return $this->options;
    }

    /** @return array<string, mixed> */
    public function toClientArray(): array
    {
        return [
            'id'             => $this->id,
            'label'          => $this->label,
            'description'    => $this->description,
            'zone'           => $this->zone->value,
            'canvas_area'    => $this->canvasArea,
            'order'          => $this->order,
            'type'           => $this->type->value,
            'icon'           => $this->icon,
            'variant'        => $this->variant,
            'contexts'       => $this->contexts,
            'client_hint'    => $this->clientHint,
            'presentation'   => $this->presentation,
            'agent_exposure' => $this->agentExposure,
            'local'          => $this->local,
            'keybinding'     => $this->keybinding,
            'confirm'        => $this->confirm,
        ];
    }
}
