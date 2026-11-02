<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Restful\Recipe\Routes;

use Uncanny_Automator\App\Recipe_Builder\Node_Tests\Services\Node_Tests_Service;
use Uncanny_Automator\App\Transports\Restful\Utilities\Traits\Rest_Responses;
use WP_REST_Request;

/**
 * Node-tests routes — `/recipe/{id}/node-tests/{kind}/{node_id}[…]`.
 *
 * Thin dispatchers (Rule 11): URL-kind → wire-kind translation, body
 * extraction, service call, envelope. All test semantics live in
 * Node_Tests_Service. Registration lives in
 * `Recipe_Rest_Controller::register_routes()`.
 */
final class Node_Tests_Routes {

	use Rest_Responses;

	/**
	 * GET list handler.
	 *
	 * @param WP_REST_Request $request The request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function list_tests( WP_REST_Request $request ) {
		$body = ( new Node_Tests_Service() )->list_tests(
			(int) $request['recipe_id'],
			self::kind( $request ),
			(int) $request['node_id']
		);

		if ( null === $body ) {
			return self::node_not_found();
		}

		return self::success( $body );
	}

	/**
	 * POST run handler.
	 *
	 * @param WP_REST_Request $request The request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function run( WP_REST_Request $request ) {
		$result = ( new Node_Tests_Service() )->run(
			(int) $request['recipe_id'],
			self::kind( $request ),
			(int) $request['node_id'],
			(string) $request['test_id'],
			self::values( $request, 'node_values' ),
			self::values( $request, 'test_values' )
		);

		if ( null === $result ) {
			return self::node_not_found();
		}

		return self::success( array( 'result' => $result ) );
	}

	/**
	 * GET run-status handler.
	 *
	 * @param WP_REST_Request $request The request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function run_status( WP_REST_Request $request ) {
		$result = ( new Node_Tests_Service() )->run_status(
			(int) $request['recipe_id'],
			self::kind( $request ),
			(int) $request['node_id'],
			(string) $request['test_id']
		);

		if ( null === $result ) {
			return self::node_not_found();
		}

		return self::success( array( 'result' => $result ) );
	}

	/**
	 * DELETE cancel handler.
	 *
	 * @param WP_REST_Request $request The request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function cancel( WP_REST_Request $request ) {
		$reason = (string) $request->get_param( 'reason' );

		if ( ! in_array( $reason, array( 'cancelled', 'timed_out' ), true ) ) {
			return self::failure(
				esc_html_x( 'Invalid cancellation reason.', 'Node tests REST error', 'uncanny-automator' ),
				400,
				'automator_node_test_invalid_reason'
			);
		}

		$result = ( new Node_Tests_Service() )->cancel(
			(int) $request['recipe_id'],
			self::kind( $request ),
			(int) $request['node_id'],
			(string) $request['test_id'],
			$reason
		);

		if ( null === $result ) {
			return self::node_not_found();
		}

		return self::success( array( 'result' => $result ) );
	}

	/**
	 * URL segment (`item|block|block-modifier`) → wire kind.
	 *
	 * @param WP_REST_Request $request The request.
	 * @return string
	 */
	private static function kind( WP_REST_Request $request ): string {
		return str_replace( '-', '_', (string) $request['kind'] );
	}

	/**
	 * A FieldsOutput body member, tolerating absence and object shape.
	 *
	 * @param WP_REST_Request $request The request.
	 * @param string          $key     Body key.
	 * @return array
	 */
	private static function values( WP_REST_Request $request, string $key ): array {
		$values = $request->get_param( $key );

		return is_array( $values ) ? $values : (array) $values;
	}

	/**
	 * The shared node-not-found failure.
	 *
	 * @return \WP_Error
	 */
	private static function node_not_found() {
		return self::failure(
			esc_html_x( 'Node not found in this recipe.', 'Node tests REST error', 'uncanny-automator' ),
			404,
			'automator_node_test_node_not_found'
		);
	}
}
