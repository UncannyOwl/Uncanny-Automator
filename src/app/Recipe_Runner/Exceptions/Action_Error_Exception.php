<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Recipe_Runner\Exceptions;

use Throwable;
use Uncanny_Automator\App\Recipe_Runner\Services\Error_Code;
use Uncanny_Automator\App\Recipe_Runner\Value_Objects\Action_Error;

/**
 * A failure that knows its error code.
 *
 * Throw it from process_action(), or from anything process_action() calls,
 * such as an API caller. The action fails with this code in the error log.
 * A plain exception fails the action too, but its code is guessed from the
 * message text (Error_Code::infer_from_message()), and a translated message
 * defeats the guess.
 *
 * Code that catches Exception keeps working: this is one.
 *
 * Throw it through a named constructor:
 *
 *     throw Action_Error_Exception::validation_failed( esc_html_x( 'Folder is required.', 'Dropbox', 'uncanny-automator' ) );
 *
 * WPCS's exception-escaping sniff flags every argument of a throw that is not
 * escaped, an Error_Code constant included, so the named constructors take
 * only the escaped message. Add one here for each code an integration needs.
 *
 * @package Uncanny_Automator\App\Recipe_Runner\Exceptions
 * @since   8.0.0
 */
class Action_Error_Exception extends \Exception {

	/**
	 * @var string Error code (Error_Code constant).
	 */
	private $error_code;

	/**
	 * @param string         $message    Human-readable message, escaped for output.
	 * @param string         $error_code Error code (Error_Code constant).
	 * @param int            $code       Exception code, such as the HTTP status.
	 * @param Throwable|null $previous   The failure this one classifies.
	 */
	public function __construct( string $message, string $error_code, int $code = 0, ?Throwable $previous = null ) {
		parent::__construct( $message, $code, $previous );

		$this->error_code = $error_code;
	}

	/**
	 * A failed API request, classified by Error_Code::from_http_status().
	 *
	 * @param string         $message  Human-readable message.
	 * @param int            $status   The HTTP status; it is also the exception code.
	 * @param Throwable|null $previous The failure this one classifies.
	 *
	 * @return self
	 */
	public static function from_http_status( string $message, int $status, ?Throwable $previous = null ): self {
		return new self( $message, Error_Code::from_http_status( $status, $message ), $status, $previous );
	}

	/**
	 * The action's input is missing or invalid.
	 *
	 * @param string $message Human-readable message, escaped for output.
	 *
	 * @return self
	 */
	public static function validation_failed( string $message ): self {
		return new self( $message, Error_Code::VALIDATION_FAILED );
	}

	/**
	 * Something the action needs does not exist: the booking, course or
	 * record its input names.
	 *
	 * @param string $message Human-readable message, escaped for output.
	 *
	 * @return self
	 */
	public static function not_found( string $message ): self {
		return new self( $message, Error_Code::NOT_FOUND );
	}

	/**
	 * The action's write failed, or did not leave the end state it should.
	 *
	 * @param string $message Human-readable message, escaped for output.
	 *
	 * @return self
	 */
	public static function execution_failed( string $message ): self {
		return new self( $message, Error_Code::EXECUTION_FAILED );
	}

	/**
	 * The app the action calls is not connected: the site holds no credentials for it.
	 *
	 * @since 8.0.0
	 *
	 * @param string $message Human-readable message, escaped for output.
	 *
	 * @return self
	 */
	public static function app_not_connected( string $message ): self {
		return new self( $message, Error_Code::APP_NOT_CONNECTED );
	}

	/**
	 * @return string Error code (Error_Code constant).
	 */
	public function get_error_code(): string {
		return $this->error_code;
	}

	/**
	 * The structured error the action records.
	 *
	 * @return Action_Error
	 */
	public function to_action_error(): Action_Error {
		return new Action_Error( $this->error_code, $this->getMessage() );
	}
}
