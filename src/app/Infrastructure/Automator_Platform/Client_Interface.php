<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Infrastructure\Automator_Platform;

/**
 * Interface Client_Interface
 *
 * Contract for an HTTP client that sends requests to the Automator Platform API.
 * Lives flat alongside its implementation `Client` per the app-layer
 * skill rule against `contracts/` subdirectories.
 *
 * @since 7.0.0
 * @package Uncanny_Automator\App\Infrastructure\Automator_Platform
 */
interface Client_Interface {

	/**
	 * Send an API request and return the response.
	 *
	 * @param Request $request The request to send.
	 *
	 * @return Response The parsed API response.
	 *
	 * @throws \Exception On transport errors or API error responses.
	 */
	public function send( Request $request ): Response;
}
