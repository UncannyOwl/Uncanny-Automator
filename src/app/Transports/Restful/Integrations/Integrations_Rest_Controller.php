<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Restful\Integrations;

use Uncanny_Automator\App\Integration_Catalog\Integration\Enums\Block_Modifier_Types;
use Uncanny_Automator\App\Integration_Catalog\Integration\Enums\Integration_Item_Types;
use Uncanny_Automator\App\Transports\Restful\Integrations\Routes\Ai_Entity_Suggestions_Route;
use Uncanny_Automator\App\Transports\Restful\Integrations\Routes\Block_Modifier_Fields_Route;
use Uncanny_Automator\App\Transports\Restful\Integrations\Routes\Get_Integrations_Route;
use Uncanny_Automator\App\Transports\Restful\Integrations\Routes\Get_Scoped_Integrations_Route;
use Uncanny_Automator\App\Transports\Restful\Integrations\Routes\Item_Fields_Route;
use Uncanny_Automator\App\Transports\Restful\Utilities\Rest_Arg;
use Uncanny_Automator\App\Transports\Restful\Utilities\Traits\Restful_Permissions;
use WP_Error;
use WP_REST_Request;

/**
 * Integrations REST registration hub.
 *
 * Static-only. Owns every `register_rest_route()` call for the
 * integrations surface and the cross-cutting `validate_scope`
 * validator. Route handler classes are referenced as class-string
 * callables so they stay autoload-cold until WP dispatches a matching
 * request.
 */
final class Integrations_Rest_Controller {

	use Restful_Permissions;

	/**
	 * Namespace for the REST API.
	 *
	 * @var string
	 */
	private const NAMESPACE_ID = AUTOMATOR_REST_API_END_POINT;

	/**
	 * Base route segment for the integrations surface.
	 *
	 * @var string
	 */
	private const REST_BASE = 'integrations';

	/**
	 * Register every integrations REST route. Called once per process
	 * from `Restful_Bootstrap::register_rest_routes()` on `rest_api_init`.
	 *
	 * @return void
	 */
	public static function register_routes(): void {

		// GET /uap/v2/integrations/get
		register_rest_route(
			self::NAMESPACE_ID,
			'/' . self::REST_BASE . '/get',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( Get_Integrations_Route::class, 'handle' ),
					'permission_callback' => array( self::class, 'permissions_check' ),
				),
			)
		);

		$scope_arg = Rest_Arg::string( array( self::class, 'validate_scope' ) );

		// GET /uap/v2/integrations/get/{scope}
		register_rest_route(
			self::NAMESPACE_ID,
			'/' . self::REST_BASE . '/get/(?P<scope>[a-zA-Z0-9_-]+)',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( Get_Scoped_Integrations_Route::class, 'handle' ),
					'permission_callback' => array( self::class, 'permissions_check' ),
					'args'                => array(
						'scope' => $scope_arg,
					),
				),
			)
		);

		// GET /uap/v2/integrations/ai-entity-suggestions/{recipe_id}/{kind}/{scope}/{parent_id}/{path_code}?
		register_rest_route(
			self::NAMESPACE_ID,
			'/' . self::REST_BASE . '/ai-entity-suggestions/(?P<recipe_id>\d+)/(?P<kind>[a-z_]+)/(?P<scope>[a-zA-Z0-9_-]+)/(?P<parent_id>[a-zA-Z0-9_-]+)(?:/(?P<path_code>[A-Z0-9_-]+))?',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( Ai_Entity_Suggestions_Route::class, 'handle' ),
					'permission_callback' => array( self::class, 'permissions_check' ),
					'args'                => array(
						'recipe_id' => Rest_Arg::positive_int(),
						'kind'      => Rest_Arg::string( array( self::class, 'validate_kind' ) ),
						'scope'     => $scope_arg,
						'parent_id' => Rest_Arg::string(),
						'path_code' => Rest_Arg::string( null, false ),
					),
				),
			)
		);

		// GET /uap/v2/integrations/{integration_code}/items/{item_type}/{item_code}/fields
		// item_code accepts hyphens for legacy action codes like ENRLCOURSE-A
		// (ancient naming convention; -A suffix = "action").
		register_rest_route(
			self::NAMESPACE_ID,
			'/' . self::REST_BASE . '/(?P<integration_code>[A-Z0-9_]+)/items/(?P<item_type>[a-z_]+)/(?P<item_code>[A-Z0-9_-]+)/fields',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( Item_Fields_Route::class, 'handle' ),
					'permission_callback' => array( self::class, 'permissions_check' ),
					'args'                => array(
						'integration_code' => Rest_Arg::string(),
						'item_type'        => Rest_Arg::string( array( Item_Fields_Route::class, 'validate_type' ) ),
						'item_code'        => Rest_Arg::string(),
					),
				),
			)
		);

		// GET /uap/v2/integrations/{integration_code}/block-modifiers/{block_modifier_type}/{modifier_code}/fields
		// modifier_code accepts hyphens for back-compat parity with item_code.
		register_rest_route(
			self::NAMESPACE_ID,
			'/' . self::REST_BASE . '/(?P<integration_code>[A-Z0-9_]+)/block-modifiers/(?P<block_modifier_type>[a-z_]+)/(?P<modifier_code>[A-Z0-9_-]+)/fields',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( Block_Modifier_Fields_Route::class, 'handle' ),
					'permission_callback' => array( self::class, 'permissions_check' ),
					'args'                => array(
						'integration_code'    => Rest_Arg::string(),
						'block_modifier_type' => Rest_Arg::string( array( Block_Modifier_Fields_Route::class, 'validate_type' ) ),
						'modifier_code'       => Rest_Arg::string(),
					),
				),
			)
		);
	}

	/**
	 * Validate the `kind` URL segment: 'item' or 'block_modifier'.
	 *
	 * @param mixed           $value
	 * @param WP_REST_Request $request
	 * @param string          $param
	 * @return bool|WP_Error
	 */
	public static function validate_kind( $value, WP_REST_Request $request, string $param ) {
		if ( in_array( (string) $value, array( 'item', 'block_modifier' ), true ) ) {
			return true;
		}
		return new WP_Error(
			'invalid_kind',
			/* translators: REST API error message for an invalid kind parameter. */
			esc_html_x( 'Invalid kind. Allowed values: item, block_modifier.', 'Automator', 'uncanny-automator' ),
			array( 'status' => 400 )
		);
	}

	/**
	 * Validate the `scope` URL segment used by the scoped catalog and
	 * AI suggestions routes. Allowed values are the union of
	 * `Integration_Item_Types` (trigger / action) and
	 * `Block_Modifier_Types` (filter_condition / loop_filter).
	 *
	 * @param mixed           $value
	 * @param WP_REST_Request $request
	 * @param string          $param
	 * @return bool|WP_Error
	 */
	public static function validate_scope( $value, WP_REST_Request $request, string $param ) {
		if ( ! Integration_Item_Types::is_valid( (string) $value ) && ! Block_Modifier_Types::is_valid( (string) $value ) ) {
			$allowed = array_merge( Integration_Item_Types::get_all(), Block_Modifier_Types::get_all() );

			return new WP_Error(
				'invalid_scope',
				sprintf(
					// translators: %s is a list of allowed scopes.
					esc_html_x( 'Invalid scope parameter. Allowed values: %s', 'Automator', 'uncanny-automator' ),
					esc_html( implode( ', ', $allowed ) )
				),
				array( 'status' => 400 )
			);
		}

		return true;
	}
}
