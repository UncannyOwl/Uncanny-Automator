<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\Persistence;

/**
 * Raised before a Page Builder source mutation when MySQL cannot provide the
 * row locking and rollback guarantees required by the generation boundary.
 */
final class SourceTransactionsUnavailableException extends \RuntimeException
{
    public function __construct(
        private readonly string $table,
        private readonly string $engine,
    ) {
        parent::__construct(
            "Atomic Page Builder writes require InnoDB; table {$table} uses {$engine}. Convert it to InnoDB before retrying.",
        );
    }

    public function table(): string
    {
        return $this->table;
    }

    public function engine(): string
    {
        return $this->engine;
    }
}
