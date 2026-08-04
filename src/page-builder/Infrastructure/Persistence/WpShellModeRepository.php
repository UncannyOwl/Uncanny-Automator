<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\Persistence;

use UncannyPageBuilder\Domain\Shell\ShellMode;
use UncannyPageBuilder\Domain\Shell\ShellModeRepositoryInterface;

final class WpShellModeRepository implements ShellModeRepositoryInterface
{
    private const OPTION_KEY = 'uncanny_page_builder_shell_mode';
    private const META_KEY   = '_uncanny_page_builder_shell_mode';

    public function getSiteDefault(): ShellMode
    {
        $value = get_option(self::OPTION_KEY, null);

        if (!is_string($value)) {
            return ShellMode::None;
        }

        return ShellMode::tryFrom($value) ?? ShellMode::None;
    }

    public function setSiteDefault(ShellMode $mode): void
    {
        $this->clearDatabaseError();
        update_option(self::OPTION_KEY, $mode->value, false);

        if ($this->databaseError() !== '' || $this->freshSiteDefault() !== $mode) {
            throw new WordPressWriteVerificationException('WordPress could not persist the default shell mode.');
        }
    }

    public function getForPage(int $pageId): ?ShellMode
    {
        $value = get_post_meta($pageId, self::META_KEY, true);

        if (!is_string($value) || $value === '') {
            return null;
        }

        return ShellMode::tryFrom($value);
    }

    public function setForPage(int $pageId, ShellMode $mode): void
    {
        $this->clearDatabaseError();
        update_post_meta($pageId, self::META_KEY, $mode->value);

        if ($this->databaseError() !== '' || $this->freshPageMode($pageId) !== $mode) {
            throw new WordPressWriteVerificationException('WordPress could not persist the page shell mode.');
        }
    }

    public function clearPageOverride(int $pageId): void
    {
        $this->clearDatabaseError();
        delete_post_meta($pageId, self::META_KEY);

        if ($this->databaseError() !== '' || $this->freshPageMode($pageId) !== null) {
            throw new WordPressWriteVerificationException('WordPress could not clear the page shell mode.');
        }
    }

    private function freshSiteDefault(): ShellMode
    {
        if (\function_exists('wp_cache_delete')) {
            \wp_cache_delete(self::OPTION_KEY, 'options');
            \wp_cache_delete('alloptions', 'options');
            \wp_cache_delete('notoptions', 'options');
        }

        return $this->getSiteDefault();
    }

    private function freshPageMode(int $pageId): ?ShellMode
    {
        if (\function_exists('wp_cache_delete')) {
            \wp_cache_delete($pageId, 'post_meta');
        }

        return $this->getForPage($pageId);
    }

    private function clearDatabaseError(): void
    {
        $wpdb = $GLOBALS['wpdb'] ?? null;
        if (is_object($wpdb) && property_exists($wpdb, 'last_error')) {
            $wpdb->last_error = '';
        }
    }

    private function databaseError(): string
    {
        $wpdb = $GLOBALS['wpdb'] ?? null;

        return is_object($wpdb) && property_exists($wpdb, 'last_error')
            ? (string) $wpdb->last_error
            : '';
    }
}
