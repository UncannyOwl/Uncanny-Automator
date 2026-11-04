<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Infrastructure\Automator_Platform\Dispatch;

use Uncanny_Automator\App\Recipe_Runner\Exceptions\Action_Error_Exception;

/**
 * A request refused on the site, before it was sent: the answer is final and
 * already in the customer's words, with its own error code. Nothing reclassifies
 * it from its status, as an answer from the platform would be.
 *
 * @since 8.0.1
 */
final class Refused_Before_Sending_Exception extends Action_Error_Exception {
}
