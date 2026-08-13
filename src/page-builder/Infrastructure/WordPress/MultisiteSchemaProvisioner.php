<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\WordPress;

use UncannyPageBuilder\Application\System\SchemaInstallerInterface;

/**
 * Provisions Page Builder tables when WordPress creates a multisite child site.
 *
 * Network activation installs existing sites, but it does not cover sites
 * created later. The wp_initialize_site hook runs after WordPress has created
 * the site's core tables, so switching the blog prefix here is safe and keeps
 * the schema ready before that site can serve Page Builder traffic.
 */
final class MultisiteSchemaProvisioner
{
    public function __construct(
        private readonly SchemaInstallerInterface $schemaInstaller,
    ) {}

    public function register(): void
    {
        add_action('wp_initialize_site', [$this, 'provisionSite'], 200, 2);
    }

    public function provisionSite($site = null, $args = null): void
    {
        unset($args);

        $siteId = $this->siteId($site);
        if ($siteId <= 0) {
            return;
        }

        if (
            !function_exists('switch_to_blog')
            || !function_exists('restore_current_blog')
        ) {
            return;
        }

        $currentSiteId = function_exists('get_current_blog_id')
            ? (int) get_current_blog_id()
            : 0;

        try {
            if ($currentSiteId === $siteId) {
                $this->schemaInstaller->ensureCurrentSite();

                return;
            }

            switch_to_blog($siteId);
            try {
                $this->schemaInstaller->ensureCurrentSite();
            } finally {
                // The inner finally restores the blog context before the
                // outer catch runs, so a failed install can never leave the
                // request on the new site's table prefix.
                restore_current_blog();
            }
        } catch (\Throwable $failure) {
            // A provisioning failure must not break WordPress site creation.
            // The schema installs on the site's first Page Builder use or the
            // next network upgrade instead.
            error_log(sprintf(
                '[Uncanny Page Builder] Multisite schema provisioning failed (%s).',
                $failure::class,
            ));
        }
    }

    private function siteId(mixed $site): int
    {
        if (is_object($site)) {
            return max(0, (int) ($site->blog_id ?? $site->id ?? 0));
        }

        if (is_int($site)) {
            return max(0, $site);
        }

        if (is_string($site) && ctype_digit($site)) {
            return max(0, (int) $site);
        }

        return 0;
    }
}
