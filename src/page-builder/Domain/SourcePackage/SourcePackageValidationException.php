<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\SourcePackage;

/**
 * User-facing validation failure for portable source packages.
 */
final class SourcePackageValidationException extends \InvalidArgumentException
{
    public function __construct(
        string $message,
        int $code = 0,
        ?\Throwable $previous = null,
        private readonly ?string $userMessage = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function userMessage(): string
    {
        return $this->userMessage ?? $this->getMessage();
    }

    public function withUserMessage(string $userMessage): self
    {
        if ($this->userMessage !== null) {
            return $this;
        }

        return new self($this->getMessage(), $this->getCode(), $this, $userMessage);
    }
}
