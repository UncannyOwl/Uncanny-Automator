<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Infrastructure\Automator_Platform;

use Uncanny_Automator\App\Recipe_Runner\Exceptions\Action_Error_Exception;
use Uncanny_Automator\App\Recipe_Runner\Services\Error_Code;

/**
 * The platform answered with an error, and here is the answer.
 *
 * {@see Client::send()} throws this for every error answer, on every surface.
 * Its message, code and error code are exactly what the client has always
 * thrown — the platform's description, the HTTP status, and the error code
 * that status gives — so every existing caller (`/v2` included) is unchanged.
 * What it adds is the answer itself, classified: a `/v3` caller reads which
 * thing failed ({@see Platform_Failure}) to tell the customer the right thing
 * ({@see Platform_Failure_Message}) instead of matching on text.
 *
 * @since 8.0.0
 */
final class Platform_Error_Exception extends Action_Error_Exception {

	/**
	 * @var Platform_Failure
	 */
	private $failure;

	/**
	 * The exception for an error answer.
	 *
	 * @param Response $response The platform's answer; it carries an `error` block.
	 *
	 * @return self
	 */
	public static function from_response( Response $response ): self {

		$message   = $response->error_description();
		$exception = new self( $message, Error_Code::from_http_status( $response->status_code(), $message ), $response->status_code() );

		$exception->failure = Platform_Failure::of( $response );

		return $exception;
	}

	/**
	 * @return Platform_Failure Which thing failed.
	 */
	public function failure(): Platform_Failure {
		return $this->failure;
	}

	/**
	 * @return Response The platform's answer.
	 */
	public function response(): Response {
		return $this->failure->response();
	}
}
