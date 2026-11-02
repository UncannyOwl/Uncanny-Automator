<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Restful\Recipe\Routes;

use Uncanny_Automator\App\Infrastructure\Database\Database;
use Uncanny_Automator\App\Infrastructure\Database\Stores\WP_User_Selector_Store;
use Uncanny_Automator\App\Recipe_Builder\User_Selector\Services\User_Selector_Service;
use Uncanny_Automator\App\Recipe_Builder\User_Selector\Services\Utilities\User_Selector_Wire_Mapper;
use Uncanny_Automator\App\Transports\Restful\Recipe\Legacy_Rest_Hooks;
use Uncanny_Automator\App\Transports\Restful\Recipe\Traits\Recipe_Entity;
use Uncanny_Automator\App\Transports\Restful\Utilities\Traits\Rest_Responses;
use WP_REST_Request;

/**
 * Set run-as REST handler.
 *
 * `POST /uap/v2/recipe/{recipe_id}/run_as/set` — reads `fields.SOURCE.value`,
 * translates the wire blob to canonical service data, and delegates to the
 * single User_Selector write. Returns the standard envelope with the freshly
 * serialized recipe. Thin per Rule 11 — logic lives in the service + mapper
 * + aggregate. A successful save fires the legacy REST API's
 * `automator_recipe_user_selector_updated` through `Legacy_Rest_Hooks`.
 */
final class Run_As_Rest {

	use Rest_Responses;
	use Recipe_Entity;

	/**
	 * Valid run-as source values (kebab canonical).
	 *
	 * @var string[]
	 */
	private const VALID_SOURCES = array( 'existing-user', 'new-user' );

	/**
	 * REST callback. Referenced from `Recipe_Rest_Controller::register_routes()`.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function dispatch( WP_REST_Request $request ) {
		$handler = new self( (int) $request['recipe_id'], $request );
		return $handler->run();
	}

	/**
	 * Validate source, map, persist, respond.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	private function run() {
		$fields = $this->get_request()->get_param( 'fields' );
		if ( ! is_array( $fields ) ) {
			return $this->failure( esc_html_x( 'Run-as fields are required', 'Run-as REST error', 'uncanny-automator' ), 400, 'invalid_fields' );
		}

		$source = isset( $fields['SOURCE']['value'] ) ? (string) $fields['SOURCE']['value'] : '';
		if ( ! in_array( $source, self::VALID_SOURCES, true ) ) {
			return $this->failure(
				sprintf(
					// translators: %s is a comma-separated list of valid run-as sources.
					esc_html_x( 'Run-as source must be one of: %s', 'Run-as REST error', 'uncanny-automator' ),
					esc_html( implode( ', ', self::VALID_SOURCES ) )
				),
				400,
				'invalid_source'
			);
		}

		$data   = ( new User_Selector_Wire_Mapper() )->to_service_data( $fields );
		$result = User_Selector_Service::instance()->save_user_selector( $this->get_recipe_id(), $data );

		if ( is_wp_error( $result ) ) {
			$status = (int) ( $result->get_error_data()['status'] ?? 400 );
			return $this->failure( $result->get_error_message(), $status, $result->get_error_code() );
		}

		$response = array(
			'success' => true,
			'recipe'  => $this->get_recipe_wire(),
		);

		// The legacy hook passed the source and fields as stored: camelCase, which the store still writes.
		$recipes = Database::get_recipe_store();
		Legacy_Rest_Hooks::action(
			'automator_recipe_user_selector_updated',
			array(
				$this->get_recipe_id(),
				$recipes->get_meta( $this->get_recipe_id(), WP_User_Selector_Store::META_SOURCE ),
				$recipes->get_meta( $this->get_recipe_id(), WP_User_Selector_Store::META_FIELDS ),
				$response,
			)
		);

		return $this->success( $response, esc_html_x( 'Run-as updated', 'Run-as REST success', 'uncanny-automator' ) );
	}
}
