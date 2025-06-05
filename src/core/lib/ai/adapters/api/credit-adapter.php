<?php

declare(strict_types=1);

namespace Uncanny_Automator\Core\Lib\AI\Adapters\API;

use Uncanny_Automator\Api_Server;
use Uncanny_Automator\Core\Lib\AI\Core\Interfaces\Credits_Manager_Interface;

/**
 * WordPress credit tracking adapter.
 *
 * Integrates with Uncanny Automator's credit system for AI usage tracking.
 * Credits are deducted for successful API requests only.
 *
 * @package Uncanny_Automator\Core\Lib\AI\Adapters\API
 * @since 5.6
 */
class Credit_Adapter implements Credits_Manager_Interface {

	/**
	 * Reduce credits for AI usage.
	 *
	 * Makes API call to Uncanny Automator's credit system.
	 * Called after successful AI API requests.
	 *
	 * @return array
	 */
	public function reduce_credits(): array {

		$payload = array(
			'endpoint' => 'v2/credits',
			'body'     => array( 'action' => 'reduce_credits' ),
		);

		try {
			$response = Api_Server::api_call( $payload );
			// Return success message.
			return array(
				'success'  => true,
				'message'  => 'Credits reduced successfully',
				'response' => $response,
			);
		} catch ( \Exception $e ) {
			// Return error message.
			return array(
				'success'  => false,
				'message'  => $e->getMessage(),
				'response' => array(),
			);
		}
	}
}
