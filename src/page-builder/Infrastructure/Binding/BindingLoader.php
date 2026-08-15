<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\Binding;

use UncannyPageBuilder\Domain\Binding\BindingDeclaration;
use UncannyPageBuilder\Domain\Binding\BindingStaticSafety;
use UncannyPageBuilder\Infrastructure\Rendering\SectionRendererInterface;

/**
 * Scans the bindings/ directory and loads all declaration.json files.
 *
 * After loading built-in bindings, applies the 'uncanny_page_builder_bindings'
 * filter so external plugins can register their own.
 */
final class BindingLoader
{
    /**
     * Load all binding declarations from a directory.
     *
     * @param string $bindingsDir Absolute path to the bindings/ root directory.
     * @return array<string, BindingDeclaration>
     */
    public function load(string $bindingsDir): array
    {
        $bindings = [];

        if (!is_dir($bindingsDir)) {
            return $this->applyFilter($bindings);
        }

        $dirs = glob($bindingsDir . '/*/declaration.json');
        if (!is_array($dirs)) {
            return $this->applyFilter($bindings);
        }

        foreach ($dirs as $jsonPath) {
            $decl = $this->loadDeclaration($jsonPath);
            if ($decl !== null) {
                $bindings[$decl->id] = $decl;
            }
        }

        return $this->applyFilter($bindings);
    }

    /**
     * @param array<string, BindingDeclaration> $bindings
     * @return array<string, BindingDeclaration>
     */
    private function applyFilter(array $bindings): array
    {
        try {
            /** @var array<string, BindingDeclaration> $filtered */
            $filtered = apply_filters('uncanny_page_builder_bindings', $bindings);
        } catch (\Throwable $failure) {
            // Binding extensions run during plugin boot. Keep the valid
            // built-in declarations when an external callback fails.
            $this->reportFilterFailure($failure);

            return $bindings;
        }

        return is_array($filtered) ? $filtered : $bindings;
    }

    private function reportFilterFailure(\Throwable $failure): void
    {
        try {
            error_log(sprintf(
                '[Uncanny Page Builder] WordPress filter "uncanny_page_builder_bindings" failed (%s).',
                $failure::class,
            ));
        } catch (\Throwable) {
            // A log failure cannot stop plugin boot.
        }
    }

    private function loadDeclaration(string $jsonPath): ?BindingDeclaration
    {
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Binding declarations are plugin-local JSON files, not remote URLs.
        $json = file_get_contents($jsonPath);
        if ($json === false) {
            return null;
        }

        try {
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            if (function_exists('_doing_it_wrong')) {
                _doing_it_wrong(
                    __METHOD__,
                    sprintf('Invalid JSON in %s: %s', $jsonPath, $e->getMessage()),
                    '1.0.0'
                );
            }
            return null;
        }

        if (!is_array($data)) {
            return null;
        }

        // Validate required fields.
        $required = ['id', 'type', 'renderer_class'];
        foreach ($required as $field) {
            if (!isset($data[$field]) || !is_string($data[$field]) || $data[$field] === '') {
                if (function_exists('_doing_it_wrong')) {
                    _doing_it_wrong(
                        __METHOD__,
                        sprintf('Binding declaration missing required field "%s" in %s', $field, $jsonPath),
                        '1.0.0'
                    );
                }
                return null;
            }
        }

        // Validate renderer class exists.
        if (!class_exists($data['renderer_class'])) {
            if (function_exists('_doing_it_wrong')) {
                _doing_it_wrong(
                    __METHOD__,
                    sprintf('Renderer class "%s" not found for binding "%s"', $data['renderer_class'], $data['id']),
                    '1.0.0'
                );
            }
            return null;
        }

        if (!is_subclass_of($data['renderer_class'], SectionRendererInterface::class)) {
            if (function_exists('_doing_it_wrong')) {
                _doing_it_wrong(
                    __METHOD__,
                    sprintf(
                        'Renderer class "%s" for binding "%s" must implement %s',
                        $data['renderer_class'],
                        $data['id'],
                        SectionRendererInterface::class,
                    ),
                    '1.0.0'
                );
            }
            return null;
        }

        // Resolve guide path from the same directory.
        $bindingDir = dirname($jsonPath);
        $guidePath = $bindingDir . '/guide.md';
        if (!file_exists($guidePath)) {
            $guidePath = '';
        }

        $rawTags = $data['tags'] ?? [];
        $tags = [];
        if (is_array($rawTags)) {
            foreach ($rawTags as $tag) {
                $tag = trim((string) $tag);
                if ($tag !== '') {
                    $tags[] = $tag;
                }
            }
        }

        return new BindingDeclaration(
            id:              $data['id'],
            title:           $data['title'] ?? $data['id'],
            summary:         $data['summary'] ?? '',
            type:            $data['type'],
            rendererClass:   $data['renderer_class'],
            queryAttributes: $data['query_attributes'] ?? [],
            bindKeys:        $data['bind_keys'] ?? [],
            metaBindings:    $data['meta_bindings'] ?? false,
            guidePath:       $guidePath,
            tags:            $tags,
            description:     $data['description'] ?? '',
            termsBindings:   $data['terms_bindings'] ?? false,
            staticSafety:    $this->staticSafety($data['static_safety'] ?? null),
            outputShape:     $this->outputShape($data['output_shape'] ?? null),
            regionContractOverride: is_array($data['region_contract'] ?? null) ? $data['region_contract'] : [],
        );
    }

    private function staticSafety(mixed $value): BindingStaticSafety
    {
        if (!is_string($value)) {
            return BindingStaticSafety::NotStatic;
        }

        return BindingStaticSafety::tryFrom($value) ?? BindingStaticSafety::NotStatic;
    }

    private function outputShape(mixed $value): string
    {
        if (!is_string($value)) {
            return 'html';
        }

        $value = trim($value);
        return in_array($value, ['text', 'url', 'html', 'conditional', 'card'], true) ? $value : 'html';
    }
}
