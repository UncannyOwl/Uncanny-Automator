<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\Controls;

final class ControlInvokeRequest
{
    /** @param array<string, mixed> $extra */
    public function __construct(
        private readonly string $controlId,
        private readonly ControlContext $context,
        private readonly mixed $value,
        private readonly array $extra = [],
    ) {}

    public function controlId(): string
    {
        return $this->controlId;
    }

    public function context(): ControlContext
    {
        return $this->context;
    }

    public function pageId(): int
    {
        return $this->context->pageId();
    }

    public function globalPartId(): int
    {
        return $this->context->globalPartId();
    }

    public function userId(): int
    {
        return $this->context->userId();
    }

    public function value(): mixed
    {
        return $this->value;
    }

    /** @return array<string, mixed> */
    public function extra(): array
    {
        return $this->extra;
    }
}
