<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\WordPress;

/**
 * Normalized fingerprint of the Google/custom font options that shape
 * rendered output.
 *
 * Saved artifacts do not embed font delivery; fonts are injected live from
 * these options. Including this fingerprint in the artifact input version
 * marks owned artifacts stale when font configuration changes instead of
 * pretending old projections still match the configured fonts.
 */
final class FontSettingsFingerprint
{
    public function __construct(
        private readonly ?WordPressFontSettings $settings = null,
    ) {}

    public function compute(): string
    {
        $settings = $this->settings;
        if (!$settings instanceof WordPressFontSettings) {
            throw new \RuntimeException('WordPress font settings are required to compute the live fingerprint.');
        }

        return $this->fromSettings($settings->googleFonts(), $settings->customFonts());
    }

    /**
     * @param array<int, mixed> $googleFonts
     * @param array<int, mixed> $customFonts
     */
    public function fromSettings(array $googleFonts, array $customFonts): string
    {
        $google = [];
        foreach ($googleFonts as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $family = trim((string) ($entry['family'] ?? ''));
            if ($family === '') {
                continue;
            }

            $google[] = [
                'family' => $family,
                'weights' => FontInjector::sanitizeGoogleFontWeights(trim((string) ($entry['weights'] ?? ''))),
            ];
        }

        $custom = [];
        foreach ($customFonts as $font) {
            if (!is_array($font)) {
                continue;
            }

            $family = FontInjector::sanitizeCustomFontFamily((string) ($font['family'] ?? ''));
            $attachmentId = (int) ($font['attachment_id'] ?? 0);
            if ($family === '' || $attachmentId <= 0) {
                continue;
            }

            $custom[] = [
                'family' => $family,
                'attachment_id' => $attachmentId,
                'weight' => FontInjector::sanitizeCustomFontWeight($font['weight'] ?? '400'),
            ];
        }

        $normalized = static fn(array $a, array $b): int =>
            self::encodeJson($a, JSON_THROW_ON_ERROR) <=> self::encodeJson($b, JSON_THROW_ON_ERROR);
        usort($google, $normalized);
        usort($custom, $normalized);

        return 'fonts-' . md5(self::encodeJson(
            ['google' => $google, 'custom' => $custom],
            JSON_THROW_ON_ERROR,
        ));
    }

    private static function encodeJson(mixed $value, int $flags = 0): string
    {
        if (function_exists('wp_json_encode')) {
            return wp_json_encode($value, $flags);
        }

        // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- Standalone font tests run without WordPress functions.
        return json_encode($value, $flags);
    }
}
