<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\Persistence;

use UncannyPageBuilder\Domain\Settings\Settings;
use UncannyPageBuilder\Domain\Settings\SettingsRepositoryInterface;

/**
 * Persists the sitewide Settings aggregate in one wp_options row.
 */
final class WpSettingsRepository implements SettingsRepositoryInterface
{
    public const OPTION_KEY = 'uncanny_page_builder_settings';
    private const LOCK_NAME = 'uncanny_page_builder_settings_row';
    private const LOCK_TIMEOUT_SECONDS = 5;

    // ── Aggregate reads ─────────────────────────────────────────────────────

    public function load(): Settings
    {
        return Settings::fromArray($this->storedData());
    }

    // ── Aggregate writes ────────────────────────────────────────────────────

    public function save(Settings $settings): void
    {
        $this->saveStoredData($settings->toArray());
    }

    public function mutate(callable $mutator): Settings
    {
        return $this->withSettingsRowLock(function () use ($mutator): Settings {
            $current = Settings::fromArray($this->freshStoredData());
            $next = $mutator($current);

            if (!$next instanceof Settings) {
                throw new \RuntimeException('Settings mutator must return a Settings aggregate.');
            }

            if ($next->toArray() !== $current->toArray()) {
                $this->saveStoredData($next->toArray());
            } else {
                $this->disableSettingsOptionAutoload();
            }

            return $next;
        });
    }

    // ── Raw option payloads ─────────────────────────────────────────────────

    /**
     * @return array<string, mixed>
     */
    public function storedData(): array
    {
        $stored = get_option(self::OPTION_KEY, []);

        return is_array($stored) ? $stored : [];
    }

    /**
     * @return array<string, mixed>
     */
    public function freshStoredData(): array
    {
        if (\function_exists('wp_cache_delete')) {
            \wp_cache_delete(self::OPTION_KEY, 'options');
            \wp_cache_delete('alloptions', 'options');
            \wp_cache_delete('notoptions', 'options');
        }

        return $this->storedData();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function saveStoredData(array $data): void
    {
        /*
         * Options accept unslashed values. This differs from metadata writes,
         * which call wp_unslash() internally and therefore need one compensating
         * wp_slash() layer at their repository boundary.
         */
        update_option(self::OPTION_KEY, $data, false);

        if ($this->freshStoredData() !== $data) {
            throw new WordPressWriteVerificationException('WordPress could not persist the Page Builder settings.');
        }

        $this->disableSettingsOptionAutoload();
    }

    // ── Settings-row locking ────────────────────────────────────────────────

    /**
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    private function withSettingsRowLock(callable $callback): mixed
    {
        $lockStatus = $this->acquireSettingsRowLock();

        if ($lockStatus === false) {
            throw new \RuntimeException('Could not lock the settings row for update.');
        }

        try {
            return $callback();
        } finally {
            if ($lockStatus === true) {
                $this->releaseSettingsRowLock();
            }
        }
    }

    private function acquireSettingsRowLock(): ?bool
    {
        $wpdb = $GLOBALS['wpdb'] ?? null;
        if (!is_object($wpdb) || !method_exists($wpdb, 'prepare') || !method_exists($wpdb, 'get_var')) {
            return null;
        }

        $result = $wpdb->get_var(
            $wpdb->prepare(
                'SELECT GET_LOCK(%s, %d)',
                self::LOCK_NAME,
                self::LOCK_TIMEOUT_SECONDS,
            )
        );

        if ($result === '1' || $result === 1) {
            return true;
        }

        return false;
    }

    private function releaseSettingsRowLock(): void
    {
        $wpdb = $GLOBALS['wpdb'] ?? null;
        if (!is_object($wpdb) || !method_exists($wpdb, 'prepare') || !method_exists($wpdb, 'get_var')) {
            return;
        }

        $wpdb->get_var(
            $wpdb->prepare(
                'SELECT RELEASE_LOCK(%s)',
                self::LOCK_NAME,
            )
        );
    }

    // ── Option policy ───────────────────────────────────────────────────────

    private function disableSettingsOptionAutoload(): void
    {
        if (\function_exists(__NAMESPACE__ . '\\wp_set_option_autoload')) {
            wp_set_option_autoload(self::OPTION_KEY, false);
            return;
        }

        if (\function_exists('wp_set_option_autoload')) {
            \wp_set_option_autoload(self::OPTION_KEY, false);
        }
    }
}
