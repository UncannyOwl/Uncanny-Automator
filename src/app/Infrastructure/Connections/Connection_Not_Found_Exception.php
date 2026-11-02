<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Infrastructure\Connections;

use Uncanny_Automator\App\Recipe_Runner\Exceptions\Action_Error_Exception;

/**
 * Thrown when a `/v3` step has no connection to dispatch with.
 *
 * Distinct from a generic exception so the `get_credentials()` façade (and
 * downstream callers) can catch specifically this case. Its message says why
 * there is none — no connection, a failed lookup, or a row that cannot be used
 * ({@see Default_Connection_Lookup}) — and its error code is the one the
 * message owner gives that reason
 * ({@see \Uncanny_Automator\App\Infrastructure\Automator_Platform\Platform_Failure_Message::for_lookup()}).
 *
 * An action failure like any other: as an {@see Action_Error_Exception} the
 * action log records that code, instead of guessing one from the English text.
 *
 * @since 8.0.0
 */
final class Connection_Not_Found_Exception extends Action_Error_Exception {
}
