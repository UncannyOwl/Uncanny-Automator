<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\WordPress;

use UncannyPageBuilder\Application\Publishing\WorkingCanvasRefreshScheduler;

/**
 * Refreshes derived working canvases when their compiler inputs change.
 *
 * The tracked value includes the plugin runtime version and the static rendering
 * policy fingerprint so editor previews do not silently retain old compiled
 * output. Published content remains immutable while runtime assets follow the
 * installed plugin release.
 */
final class WorkingCanvasInputVersionListener
{
    private const OPTION_KEY = 'uncanny_page_builder_working_canvas_input_version';
    public function __construct(
        private readonly WorkingCanvasRefreshScheduler $refreshScheduler,
        private readonly string $workingCanvasInputFingerprint,
    ) {
    }

    public function register(): void
    {
        add_action('init', [$this, 'checkVersion']);
        add_action('admin_init', [$this, 'checkVersion']);
    }

    public function checkVersion(): void
    {
        $current = trim($this->workingCanvasInputFingerprint);
        if ($current === '') {
            return;
        }

        try {
            $stored = get_option(self::OPTION_KEY, '');
            if (!is_string($stored) || trim($stored) === '') {
                $this->refreshScheduler->enqueueAll();
                update_option(self::OPTION_KEY, $current, false);
                return;
            }

            if ($stored === $current) {
                return;
            }

            $this->refreshScheduler->enqueueAll();
            update_option(self::OPTION_KEY, $current, false);
        } catch (\Throwable $failure) {
            // WordPress runs this callback during init and admin_init. A
            // failure must not terminate the shared request. The stored input
            // version stays unchanged, so the next request retries the work.
            error_log(sprintf(
                '[Uncanny Page Builder] Working canvas input-version refresh failed (%s).',
                $failure::class,
            ));
        }
    }
}
