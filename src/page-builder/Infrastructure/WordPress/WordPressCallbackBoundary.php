<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\WordPress;

/**
 * Contains failures from Page Builder callbacks that WordPress invokes.
 *
 * WordPress does not provide one exception boundary around plugin hooks. A
 * failure in one Page Builder callback must not terminate the shared request
 * or prevent later plugins from running.
 */
final class WordPressCallbackBoundary
{
    public static function valueOrDie(string $context, callable $callback): mixed
    {
        try {
            return $callback();
        } catch (\Throwable $failure) {
            self::report($context, $failure);
            wp_die(
                esc_html_x(
                    'Uncanny Page Builder could not validate this request. Return to the previous page and try again.',
                    'Page Builder',
                    'uncanny-automator',
                ),
                '',
                ['response' => 500],
            );
        }
    }

    public function action(string $context, callable $callback): \Closure
    {
        return static function (...$args) use ($context, $callback): void {
            try {
                $callback(...$args);
            } catch (\Throwable $failure) {
                self::report($context, $failure);
            }
        };
    }

    public function filter(string $context, callable $callback): \Closure
    {
        return static function ($input = null, ...$args) use ($context, $callback) {
            try {
                return $callback($input, ...$args);
            } catch (\Throwable $failure) {
                self::report($context, $failure);
                return $input;
            }
        };
    }

    private static function report(string $context, \Throwable $failure): void
    {
        error_log(sprintf(
            '[Uncanny Page Builder] WordPress callback %s failed (%s).',
            str_replace(["\r", "\n"], '', $context),
            $failure::class,
        ));
    }
}
