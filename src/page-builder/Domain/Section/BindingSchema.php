<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\Section;

use UncannyPageBuilder\Domain\Binding\BindingRegistry;

/**
 * Canonical vocabulary for the component/binding system.
 *
 * Single source of truth for editable types, dynamic sources, bind keys,
 * and query attributes. All validators and extractors reference this class.
 *
 * Dynamic sources are loaded from the BindingRegistry (populated from
 * bindings directory declarations at boot). Call init() before using.
 *
 * Meta bindings (meta.<key>) are governed by DynamicContentConfig blocklists.
 */
final class BindingSchema
{
    public const SCHEMA_ID = 'uncanny_page_builder_v1';

    private const EDITABLE_TYPES = ['text', 'textarea', 'image', 'link', 'bg-image'];

    private static ?BindingRegistry $registry = null;

    /**
     * Initialize with the loaded binding registry. Must be called at boot.
     */
    public static function init(BindingRegistry $registry): void
    {
        self::$registry = $registry;
    }

    /**
     * Reset for testing.
     * @internal
     */
    public static function reset(): void
    {
        self::$registry = null;
    }

    private static function registry(): BindingRegistry
    {
        if (self::$registry === null) {
            throw new \LogicException('BindingSchema::init() must be called before use.');
        }
        return self::$registry;
    }

    /** @return string[] */
    public static function editableTypes(): array
    {
        return self::EDITABLE_TYPES;
    }

    /** @return string[] */
    public static function dynamicSources(): array
    {
        return self::registry()->ids();
    }

    /**
     * Static (non-meta) bind keys for a specific source.
     *
     * @return string[]
     */
    public static function bindKeysForSource(string $source): array
    {
        return self::registry()->bindKeysForSource($source);
    }

    /**
     * All bind keys for a source, including governed meta.<key> bindings.
     *
     * @return string[]
     */
    public static function allBindKeysForSource(string $source): array
    {
        return self::bindKeysForSource($source);
    }

    /** @return string[] All query attribute names (required + optional). */
    public static function queryAttributesForSource(string $source): array
    {
        return self::registry()->queryAttributesForSource($source);
    }

    /**
     * Full query attribute config (name → {required, default, cast}) for a source.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function queryAttributeConfigForSource(string $source): array
    {
        $decl = self::registry()->get($source);
        return $decl ? $decl->queryAttributes : [];
    }

    /** @return string[] Only required query attribute names. */
    public static function requiredQueryAttributesForSource(string $source): array
    {
        return self::registry()->requiredQueryAttributesForSource($source);
    }

    /**
     * All supported bind keys across all sources.
     *
     * @return string[]
     */
    public static function allBindKeys(): array
    {
        $reg = self::registry();
        $keys = [];
        foreach ($reg->ids() as $source) {
            $keys = array_merge($keys, self::allBindKeysForSource($source));
        }
        return array_values(array_unique($keys));
    }

    public static function isValidEditableType(string $type): bool
    {
        return in_array($type, self::EDITABLE_TYPES, true);
    }

    public static function isValidDynamicSource(string $source): bool
    {
        return self::registry()->has($source);
    }

    /**
     * Whether the binding is a card (looping) type that requires a template child.
     */
    public static function isCardBinding(string $source): bool
    {
        $decl = self::registry()->get($source);
        return $decl !== null && $decl->isCard();
    }

    /**
     * Validate a bind key against all sources and governed meta/terms allowlists.
     */
    public static function isValidBindKey(string $key): bool
    {
        $reg = self::registry();

        foreach ($reg->ids() as $source) {
            if (in_array($key, $reg->bindKeysForSource($source), true)) {
                return true;
            }
        }

        if (str_starts_with($key, 'meta.')) {
            $metaKey = substr($key, 5);
            return $metaKey !== '' && DynamicContentConfig::isMetaKeyAllowed($metaKey);
        }

        // terms.<taxonomy> — only valid when at least one source declares termsBindings.
        if (str_starts_with($key, 'terms.')) {
            if (substr($key, 6) === '') {
                return false;
            }
            foreach ($reg->ids() as $source) {
                if ($reg->allowsTermsBindings($source)) {
                    return true;
                }
            }
            return false;
        }

        return false;
    }

    /**
     * Validate a bind key against a specific source's vocabulary.
     */
    public static function isValidBindKeyForSource(string $key, string $source): bool
    {
        $reg = self::registry();
        if (!$reg->has($source)) {
            return false;
        }

        $bindKeys = $reg->bindKeysForSource($source);
        if (in_array($key, $bindKeys, true)) {
            return true;
        }

        if (str_starts_with($key, 'meta.')) {
            $metaKey = substr($key, 5);
            if ($metaKey === '') {
                return false;
            }
            // Only allow meta bindings for sources that declare them.
            if (!$reg->allowsMetaBindings($source)) {
                return false;
            }
            return DynamicContentConfig::isMetaKeyAllowed($metaKey);
        }

        // terms.<taxonomy> — only allowed on sources that declare termsBindings.
        if (str_starts_with($key, 'terms.')) {
            if (substr($key, 6) === '') {
                return false;
            }
            return $reg->allowsTermsBindings($source);
        }

        return false;
    }

    /** @return array<string, mixed> */
    public static function toArray(): array
    {
        $sources = [];
        foreach (self::dynamicSources() as $name) {
            $sources[$name] = [
                'query_attributes' => self::queryAttributesForSource($name),
                'bind_keys'        => self::allBindKeysForSource($name),
                'output_shape'     => self::registry()->get($name)?->outputShape ?? 'html',
            ];
        }

        return [
            'schema_id'              => self::SCHEMA_ID,
            'editable_types'         => self::EDITABLE_TYPES,
            'dynamic_sources'        => $sources,
            'dynamic_content_config' => DynamicContentConfig::toArray(),
        ];
    }
}
