<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\Rendering;

/**
 * The immutable artifact is valid, but its required plugin runtime cannot be served.
 */
final class PublishedPageRuntimeUnavailable extends \RuntimeException
{
    /** @param array<string, string> $diagnostics Safe infrastructure details, with server paths already removed. */
    public function __construct(
        private readonly string $reasonCode,
        string $message,
        ?\Throwable $previous = null,
        private readonly array $diagnostics = [],
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function reasonCode(): string
    {
        return $this->reasonCode;
    }

    /** @return array<string, string> */
    public function diagnostics(): array
    {
        return $this->diagnostics;
    }
}
