<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\Persistence;

/**
 * WordPress returned from a write without making the requested state durable.
 *
 * Callers must not treat the write API's return value alone as success. This
 * exception marks an exact readback mismatch so external facades can steer the
 * caller through a fresh read before any retry.
 */
final class WordPressWriteVerificationException extends \RuntimeException
{
}
