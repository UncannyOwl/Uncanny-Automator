<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\Controls;

final class ControlState
{
    private const BASE_KEYS = ['id', 'value', 'visible', 'permitted', 'enabled', 'busy', 'badge', 'options', 'reason'];

    /** @param array<int, array<string, mixed>>|null $options */
    public function __construct(
        private readonly string $id,
        private readonly mixed $value = null,
        private readonly bool $visible = true,
        private readonly bool $permitted = true,
        private readonly bool $enabled = true,
        private readonly bool $busy = false,
        private readonly ?string $badge = null,
        private readonly ?array $options = null,
        private readonly ?string $reason = null,
        /** @var array<string, mixed> */
        private readonly array $extra = [],
    ) {}

    public static function defaults(ControlDefinition $definition): self
    {
        return new self(
            id: $definition->id(),
            value: $definition->defaultValue(),
            options: $definition->options(),
        );
    }

    /** @param array<string, mixed> $patch */
    public function withPatch(array $patch): self
    {
        return new self(
            id: $this->id,
            value: array_key_exists('value', $patch) ? $patch['value'] : $this->value,
            visible: array_key_exists('visible', $patch) ? (bool) $patch['visible'] : $this->visible,
            permitted: array_key_exists('permitted', $patch) ? (bool) $patch['permitted'] : $this->permitted,
            enabled: array_key_exists('enabled', $patch) ? (bool) $patch['enabled'] : $this->enabled,
            busy: array_key_exists('busy', $patch) ? (bool) $patch['busy'] : $this->busy,
            badge: array_key_exists('badge', $patch) ? ($patch['badge'] === null ? null : (string) $patch['badge']) : $this->badge,
            options: array_key_exists('options', $patch) && (is_array($patch['options']) || $patch['options'] === null) ? $patch['options'] : $this->options,
            reason: array_key_exists('reason', $patch) ? ($patch['reason'] === null ? null : (string) $patch['reason']) : $this->reason,
            extra: array_diff_key($patch, array_flip(self::BASE_KEYS)) + $this->extra,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return array_merge([
            'id'      => $this->id,
            'value'   => $this->value,
            'visible' => $this->visible,
            'permitted' => $this->permitted,
            'enabled' => $this->enabled,
            'busy'    => $this->busy,
            'badge'   => $this->badge,
            'options' => $this->options,
            'reason'  => $this->reason,
        ], $this->extra);
    }
}
