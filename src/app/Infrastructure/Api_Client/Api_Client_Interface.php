<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Infrastructure\Api_Client;

/**
 * Interface Api_Client_Interface
 *
 * Contract for an HTTP client that sends requests to the Automator API.
 * Lives flat alongside its implementation `Api_Client` per the app-layer
 * skill rule against `contracts/` subdirectories.
 *
 * @since 7.0.0
 * @package Uncanny_Automator\App\Infrastructure\Api_Client
 */
interface Api_Client_Interface {

	/**
	 * Send an API request and return the response.
	 *
	 * @param Api_Request $request The request to send.
	 *
	 * @return Api_Response The parsed API response.
	 *
	 * @throws \Exception On transport errors or API error responses.
	 */
	public function send( Api_Request $request ): Api_Response;
}
