<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\SourcePackage;

/**
 * Carries one approved Page Source export failure to the editor transport.
 */
final class PageSourceExportException extends \RuntimeException
{
    public function __construct(
        string $userMessage,
        private readonly int $httpStatus,
        ?\Throwable $previous = null,
    ) {
        if ($httpStatus < 400 || $httpStatus > 599) {
            throw new \InvalidArgumentException('A page source export error requires a valid HTTP status.');
        }

        parent::__construct($userMessage, 0, $previous);
    }

    public function httpStatus(): int
    {
        return $this->httpStatus;
    }
}
