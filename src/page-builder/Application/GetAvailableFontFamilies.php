<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application;

use UncannyPageBuilder\Domain\DesignStandards\FontFamilyCatalogSourceInterface;
use UncannyPageBuilder\Domain\DesignStyles\DesignStyleValue;

/**
 * Builds the shared font-family catalog used by admin and canvas typography UI.
 */
final class GetAvailableFontFamilies
{
    /**
     * @param list<array{label: string, value: string}> $standardOptions
     */
    private const STANDARD_OPTIONS = [
        [
            'label' => 'System font stack',
            'value' => "system-ui,-apple-system,\"Segoe UI\",Roboto,\"Helvetica Neue\",\"Noto Sans\",\"Liberation Sans\",Arial,sans-serif,\"Apple Color Emoji\",\"Segoe UI Emoji\",\"Segoe UI Symbol\",\"Noto Color Emoji\"",
        ],
        [
            'label' => 'Editorial serif',
            'value' => 'Georgia,"Times New Roman",serif',
        ],
        [
            'label' => 'Monospace',
            'value' => 'SFMono-Regular,Menlo,Monaco,Consolas,monospace',
        ],
        [
            'label' => 'Inherit',
            'value' => 'inherit',
        ],
    ];

    public function __construct(
        private readonly FontFamilyCatalogSourceInterface $source,
    ) {}

    /**
     * @return list<array{key: string, label: string, options: list<array{label: string, value: string, source: string}>}>
     */
    public function catalog(?string $currentValue = null): array
    {
        $seen = [];
        $groups = [];

        $groups[] = $this->standardGroup($seen);

        $google = $this->namedGroup(
            'google',
            'Google Fonts',
            $this->source->googleFontFamilies(),
            'google',
            $seen,
        );
        if ($google !== null) {
            $groups[] = $google;
        }

        $custom = $this->namedGroup(
            'custom',
            'Uploaded fonts',
            $this->source->customFontFamilies(),
            'custom',
            $seen,
        );
        if ($custom !== null) {
            $groups[] = $custom;
        }

        $current = $this->currentValueGroup($currentValue, $seen);
        if ($current !== null) {
            $groups[] = $current;
        }

        return $groups;
    }

    /**
     * @param array<string, true> $seen
     * @return array{key: string, label: string, options: list<array{label: string, value: string, source: string}>}
     */
    private function standardGroup(array &$seen): array
    {
        $options = [];

        foreach (self::STANDARD_OPTIONS as $option) {
            if (isset($seen[$option['value']])) {
                continue;
            }

            $seen[$option['value']] = true;
            $options[] = [
                'label' => $option['label'],
                'value' => $option['value'],
                'source' => 'standard',
            ];
        }

        return [
            'key' => 'standard',
            'label' => 'Standard stacks',
            'options' => $options,
        ];
    }

    /**
     * @param list<string> $families
     * @param array<string, true> $seen
     * @return array{key: string, label: string, options: list<array{label: string, value: string, source: string}>}|null
     */
    private function namedGroup(
        string $key,
        string $label,
        array $families,
        string $source,
        array &$seen,
    ): ?array {
        $options = [];

        foreach ($families as $family) {
            $sanitized = $this->sanitizeNamedFamily($family);
            if ($sanitized === '') {
                continue;
            }

            $value = $this->namedFamilyCssValue($sanitized);
            if (isset($seen[$value])) {
                continue;
            }

            $seen[$value] = true;
            $options[] = [
                'label' => $sanitized,
                'value' => $value,
                'source' => $source,
            ];
        }

        if ($options === []) {
            return null;
        }

        return [
            'key' => $key,
            'label' => $label,
            'options' => $options,
        ];
    }

    /**
     * @param array<string, true> $seen
     * @return array{key: string, label: string, options: list<array{label: string, value: string, source: string}>}|null
     */
    private function currentValueGroup(?string $currentValue, array &$seen): ?array
    {
        $value = trim((string) $currentValue);
        if ($value === '' || isset($seen[$value]) || !DesignStyleValue::isSafeValue($value)) {
            return null;
        }

        $seen[$value] = true;

        return [
            'key' => 'current',
            'label' => 'Custom value',
            'options' => [[
                'label' => 'Custom value',
                'value' => $value,
                'source' => 'current',
            ]],
        ];
    }

    private function sanitizeNamedFamily(string $family): string
    {
        $family = preg_replace('/[\x00-\x1F\x7F]/', ' ', $family) ?? '';
        $family = preg_replace('/[;{}"\'\\\\]/', '', $family) ?? '';
        $family = preg_replace('/\s+/', ' ', trim($family)) ?? '';

        return $family;
    }

    private function namedFamilyCssValue(string $family): string
    {
        return '"' . str_replace('"', '\"', $family) . '"';
    }
}
