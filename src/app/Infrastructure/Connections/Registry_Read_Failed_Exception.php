<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Infrastructure\Connections;

use RuntimeException;

/**
 * A `uap_connections` read the database did not answer. Thrown instead of
 * returning an empty list, so a caller never reads a failed query as "there
 * is nothing to do" — an adoption that saw no rows would report itself
 * finished, a sync would report nothing to repair.
 *
 * @since 8.0.0
 */
final class Registry_Read_Failed_Exception extends RuntimeException {
}
