<?php
declare(strict_types=1);
namespace Uncanny_Automator\Api\Transports\Restful\Integrations;

use Uncanny_Automator\Api\Services\Integration\Integration_Query_Service;
use Uncanny_Automator\Api\Components\Integration\Enums\Integration_Item_Types;
use Uncanny_Automator\Api\Transports\Restful\Utilities\Traits\Restful_Permissions;
use Uncanny_Automator\Api\Transports\Restful\Utilities\Traits\Rest_Responses;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * Integrations REST Controller.
 *
 * Provides REST API endpoints for Automator integrations.
 *
 * @since 7.0
 */
class Integrations_Rest_Controller {

	use Restful_Permissions;
	use Rest_Responses;

	/**
	 * Namespace for the REST API.
	 *
	 * @var string
	 */
	protected $namespace = AUTOMATOR_REST_API_END_POINT;

	/**
	 * Base route.
	 *
	 * @var string
	 */
	protected $rest_base = 'integrations';

	/**
	 * Registers the routes for integrations.
	 *
	 * @since 7.0
	 *
	 * @return void
	 */
	public function register_routes() {

		// GET /wp-json/uap/v2/integrations/get
		// Returns a collection of all available Automator integrations (unscoped).
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/get',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_integrations' ),
					'permission_callback' => array( $this, 'permissions_check' ),
				),
			)
		);

		// GET /wp-json/uap/v2/integrations/get/{scope}
		// Returns a collection of integrations scoped by type (trigger, action, loop_filter, condition).
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/get/(?P<scope>[a-zA-Z0-9_-]+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_integrations_by_scope' ),
					'permission_callback' => array( $this, 'permissions_check' ),
					'args'                => $this->get_scope_args(),
				),
			)
		);

		// GET /wp-json/uap/v2/integrations/collections/get
		// Returns a collection of all available Automator integration collections.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/collections/get',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_collections' ),
					'permission_callback' => array( $this, 'permissions_check' ),
				),
			)
		);
	}

	/**
	 * Defines the scope endpoint arguments used for validation.
	 *
	 * @since 7.0
	 *
	 * @return array
	 */
	protected function get_scope_args(): array {
		return array(
			'scope' => array(
				'required'          => true,
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => array( $this, 'validate_scope' ),
			),
		);
	}

	/**
	 * Validate scope parameter.
	 *
	 * @since 7.0
	 *
	 * @param mixed           $value   The value to validate.
	 * @param WP_REST_Request $request The request object.
	 * @param string          $param   The parameter name.
	 *
	 * @return bool|WP_Error True if valid, WP_Error otherwise.
	 */
	public function validate_scope( $value, WP_REST_Request $request, string $param ) {
		if ( ! Integration_Item_Types::is_valid( $value ) ) {
			return new WP_Error(
				'invalid_scope',
				sprintf(
					// translators: %s is a list of allowed scopes.
					esc_html_x( 'Invalid scope parameter. Allowed values: %s', 'Automator', 'uncanny-automator' ),
					esc_html( implode( ', ', Integration_Item_Types::get_all() ) )
				),
				array( 'status' => 400 )
			);
		}

		return true;
	}

	/**
	 * Returns a collection of all unscoped Automator integrations.
	 *
	 * The data for each integration is "unscoped". This means it provides general,
	 * static information about the integration (like its name, icon, and a list of
	 * all its possible triggers/actions). It does not contain information that
	 * changes based on the current user, their license, or the specific context
	 * in which they are viewing the integration (e.g., while building a recipe).
	 *
	 * @since 7.0
	 *
	 * @param WP_REST_Request $request The request object.
	 *
	 * @return WP_REST_Response
	 */
	public function get_integrations( WP_REST_Request $request ): WP_REST_Response {
		$query_service = Integration_Query_Service::get_instance();
		return new WP_REST_Response( $query_service->get_all_integrations_to_rest(), 200 );
	}

	/**
	 * Get integrations by scope.
	 *
	 * Scoped means that certain properties of an integration are dynamically
	 * resolved based on this context.
	 *
	 * For example:
	 * - Popularity: The popularity score of an integration can differ depending on the scope.
	 *   It might be more popular (compared to another integration) for "actions" than for "conditions."
	 *
	 * - Dependencies: An integration's dependencies (like requiring Automator Pro) can also vary by scope.
	 *   WooCommerce might depend on Automator Pro when a user is trying to access its "conditions"
	 *   but not when accessing its basic "triggers".
	 *
	 * - Some integrations may not be returned for certain scopes.
	 *   For example, if the scope is set to "trigger" but an integration has no triggers, it won't be included.
	 *
	 * @since 7.0
	 *
	 * @param WP_REST_Request $request The request object.
	 *
	 * @return WP_REST_Response
	 */
	public function get_integrations_by_scope( WP_REST_Request $request ): WP_REST_Response {
		$scope         = $request->get_param( 'scope' );
		$query_service = Integration_Query_Service::get_instance();
		return new WP_REST_Response( $query_service->get_scoped_integrations( $scope ), 200 );
	}

	/**
	 * Get integrations collections.
	 *
	 * @since 7.0
	 *
	 * @param WP_REST_Request $request The request object.
	 *
	 * @return WP_REST_Response Response containing collection data.
	 *    @property string $id          Collection identifier.
	 *    @property string $name        Collection name.
	 *    @property string $description Collection description.
	 *    @property array  $integration_codes Array of integration codes.
	 */
	public function get_collections( WP_REST_Request $request ): WP_REST_Response {
		$query_service = Integration_Query_Service::get_instance();
		return new WP_REST_Response( $query_service->get_collections(), 200 );
	}
}
