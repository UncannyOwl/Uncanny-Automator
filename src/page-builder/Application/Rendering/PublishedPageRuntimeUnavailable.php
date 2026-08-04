<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\Rendering;

/**
 * The immutable artifact is valid, but its required plugin runtime cannot be served.
 */
final class PublishedPageRuntimeUnavailable extends \RuntimeException
{
    public function __construct(
        private readonly string $reasonCode,
        string $message,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function reasonCode(): string
    {
        return $this->reasonCode;
    }
}
