<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\WordPress;

/** Captures the cause of one filesystem operation for an authenticated publication error. */
final class FallbackAssetFilesystemDiagnostics
{
    private string $operation = '';
    private ?string $systemError = null;

    /** @var array<string, string> */
    private array $paths = [];

    public function addPath(string $path, string $label): void
    {
        $path = rtrim($path, '/\\');
        if ($path !== '') {
            $this->paths[$path] = $label;
            $this->paths[str_replace('\\', '/', $path)] = $label;
        }
    }

    public function capture(string $operation, \Closure $action): mixed
    {
        // A silent failure must not inherit a warning from an earlier operation.
        $this->operation = $operation;
        $this->systemError = null;
        set_error_handler(function (int $severity, string $message): bool {
            $this->systemError = $message;
            // Native warnings must not corrupt the REST response, including when @ is used.
            return true;
        }, E_WARNING | E_USER_WARNING);

        try {
            return $action();
        } catch (\Throwable $failure) {
            $this->systemError = $failure->getMessage();
            throw $failure;
        } finally {
            // Other plugins must get their handler back after success and failure.
            restore_error_handler();
        }
    }

    /** @return array{operation: string, system_error: string} */
    public function details(): array
    {
        return [
            'operation' => $this->operation,
            'system_error' => $this->redactPaths($this->systemError ?? 'unknown'),
        ];
    }

    private function redactPaths(string $message): string
    {
        return ServerPathRedaction::redact($message, $this->paths);
    }
}
