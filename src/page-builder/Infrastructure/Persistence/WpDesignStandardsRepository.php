<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\Persistence;

use UncannyPageBuilder\Domain\DesignStandards\DesignStandardsProfile;
use UncannyPageBuilder\Domain\DesignStandards\DesignStandardsRepositoryInterface;
use UncannyPageBuilder\Domain\Settings\Settings;

/**
 * Persists the sitewide Bootstrap design standards profile inside Settings.
 *
 * Page overrides stay in post meta because they are page-scoped state, but the
 * sitewide design profile now lives under the Brand styles subsection of the
 * single settings aggregate row.
 */
final class WpDesignStandardsRepository implements DesignStandardsRepositoryInterface
{
    private const META_OVERRIDES = '_uncanny_engine_theme_overrides';

    private WpSettingsRepository $settingsRepository;

    public function __construct(?WpSettingsRepository $settingsRepository = null)
    {
        $this->settingsRepository = $settingsRepository ?? new WpSettingsRepository();
    }

    public function load(): ?DesignStandardsProfile
    {
        try {
            return $this->settingsRepository
                ->load()
                ->brandStyles()
                ->designStandardsProfile();
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function save(DesignStandardsProfile $profile): void
    {
        $this->settingsRepository->mutate(function (Settings $settings) use ($profile): Settings {
            if ($this->sameProfileContent($settings->toArray(), $profile)) {
                return $settings;
            }

            return $this->settingsWithUpdatedProfile($settings, $profile);
        });
    }

    public function loadPageOverrides(int $pageId): ?array
    {
        $raw = get_post_meta($pageId, self::META_OVERRIDES, true);
        return is_array($raw) ? $raw : null;
    }

    public function savePageOverrides(int $pageId, array $data): void
    {
        $stored = $this->freshOverrides($pageId);
        if ($this->sameOverridesContent($stored, $data)) {
            return;
        }

        $persisted = \function_exists('wp_slash') ? \wp_slash($data) : $data;
        update_post_meta($pageId, self::META_OVERRIDES, $persisted);

        if (!$this->sameOverridesContent($this->freshOverrides($pageId), $data)) {
            throw new WordPressWriteVerificationException('WordPress could not persist the page design overrides.');
        }
    }

    public function applyFilter(string $filterName, mixed $value, mixed ...$args): mixed
    {
        try {
            return apply_filters($filterName, $value, ...$args);
        } catch (\Throwable $failure) {
            // Design filters run during API and canvas reads. Keep the stored
            // design value when an external callback fails so one extension
            // cannot replace those surfaces with a fatal response.
            try {
                error_log(sprintf(
                    '[Uncanny Page Builder] WordPress design filter "%s" failed (%s).',
                    $filterName,
                    $failure::class,
                ));
            } catch (\Throwable) {
                // A log failure cannot replace the stored design value.
            }

            return $value;
        }
    }

    private function freshOverrides(int $pageId): array
    {
        if (\function_exists('wp_cache_delete')) {
            \wp_cache_delete($pageId, 'post_meta');
        }

        $stored = get_post_meta($pageId, self::META_OVERRIDES, true);

        return is_array($stored) ? $stored : [];
    }

    /**
     * Whether the new payload changes the stored profile content.
     */
    private function sameProfileContent(array $stored, DesignStandardsProfile $new): bool
    {
        // Strict compare after normalization: loose == would coerce
        // numerically-equal token literals ('400' vs '4e2') into a no-op,
        // silently dropping a reformatting write with success feedback.
        return $this->normalized($this->storedProfileArray($stored))
            === $this->normalized($new->toArray());
    }

    /**
     * @param array<string, mixed> $stored
     * @param array<string, mixed> $new
     */
    private function sameOverridesContent(array $stored, array $new): bool
    {
        return $this->normalized($stored) === $this->normalized($new);
    }

    /**
     * Profile arrays can carry stdClass placeholders (empty token maps),
     * stored copies round-trip through serialization, and different writers
     * may emit the same map in a different key order; normalize all of that
     * so the strict content compare is structural.
     */
    private function normalized(mixed $value): mixed
    {
        if (is_object($value)) {
            $value = (array) $value;
        }

        if (is_array($value)) {
            foreach ($value as $key => $item) {
                $value[$key] = $this->normalized($item);
            }
            ksort($value);
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $stored
     * @return array<string, mixed>
     */
    private function storedProfileArray(array $stored): array
    {
        $settings = Settings::fromArray($stored);

        return $settings->brandStyles()->designStandardsProfile()->toArray();
    }

    private function settingsWithUpdatedProfile(Settings $settings, DesignStandardsProfile $profile): Settings
    {
        return $settings->withBrandStyles(
            $settings
                ->brandStyles()
                ->withDesignStandardsProfile($profile),
        );
    }
}
