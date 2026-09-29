<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\Exception;

/** A known content rejection, separate from malformed command input. */
final class NodeEditRejectedException extends \InvalidArgumentException
{
    private function __construct(private readonly string $reason, string $detail)
    {
        parent::__construct($detail);
    }

    public static function targetChanged(string $detail): self
    {
        return new self('target_changed', $detail);
    }

    public static function invalidBlock(string $detail): self
    {
        return new self('invalid_block', $detail);
    }

    public static function unsafeHtml(string $detail): self
    {
        return new self('unsafe_html', $detail);
    }

    public static function bindingOwned(string $detail): self
    {
        return new self('binding_owned', $detail);
    }

    public function reason(): string
    {
        return $this->reason;
    }
}
