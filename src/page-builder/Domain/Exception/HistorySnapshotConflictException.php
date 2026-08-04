<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\Exception;

/**
 * Thrown when an undo/redo restore is attempted but the page changed since the
 * history entry was captured, so restoring would clobber the newer edits. The
 * restore is aborted; the caller should refresh and retry.
 */
final class HistorySnapshotConflictException extends \RuntimeException
{
    public function __construct(
        string $message = 'The page changed since this history entry was captured. Refresh and try again.',
    ) {
        parent::__construct($message);
    }
}
