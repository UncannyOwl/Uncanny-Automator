<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Restful\Recipe;

use Uncanny_Automator\App\Infrastructure\Database\Stores\WP_Recipe_Store;
use Uncanny_Automator\App\Integration_Catalog\Integration\Enums\Block_Modifier_Types;
use Uncanny_Automator\App\Integration_Catalog\Integration\Enums\Integration_Item_Types;
use Uncanny_Automator\App\Recipe_Builder\Block\Enums\Block_Type;
use Uncanny_Automator\App\Transports\Restful\Recipe\Routes\Block_Modifiers_Routes;
use Uncanny_Automator\App\Transports\Restful\Recipe\Routes\Blocks_Routes;
use Uncanny_Automator\App\Transports\Restful\Recipe\Routes\Get_Most_Active_Route;
use Uncanny_Automator\App\Transports\Restful\Recipe\Routes\Get_Recipe_Route;
use Uncanny_Automator\App\Transports\Restful\Recipe\Routes\Recipe_Details_Rest;
use Uncanny_Automator\App\Transports\Restful\Recipe\Routes\Recipe_Taxonomies_Rest;
use Uncanny_Automator\App\Transports\Restful\Recipe\Routes\Items_Routes;
use Uncanny_Automator\App\Transports\Restful\Recipe\Routes\Node_Tests_Routes;
use Uncanny_Automator\App\Transports\Restful\Recipe\Routes\Recipe_Settings_Rest;
use Uncanny_Automator\App\Transports\Restful\Recipe\Routes\Reorder_Route;
use Uncanny_Automator\App\Transports\Restful\Recipe\Routes\Resend_Action_Rest;
use Uncanny_Automator\App\Transports\Restful\Recipe\Routes\Run_As_Rest;
use Uncanny_Automator\App\Transports\Restful\Recipe\Routes\Run_Now_Rest;
use Uncanny_Automator\App\Transports\Restful\Utilities\Rest_Arg;
use Uncanny_Automator\App\Transports\Restful\Utilities\Traits\Restful_Permissions;

/**
 * Recipe REST registration hub.
 *
 * Static-only. Owns every `register_rest_route()` call for the recipe
 * surface and the cross-cutting `validate_recipe_id` validator. Route
 * handler classes are referenced as class-string callables so they
 * stay autoload-cold until WP dispatches a matching request.
 */
final class Recipe_Rest_Controller {

	use Restful_Permissions;

	/**
	 * Namespace for the REST API.
	 *
	 * @var string
	 */
	private const NAMESPACE_ID = AUTOMATOR_REST_API_END_POINT;

	/**
	 * Base route segment for the recipe surface.
	 *
	 * @var string
	 */
	private const REST_BASE = 'recipe';

	/**
	 * Register every recipe REST route. Called once per process from
	 * `Restful_Bootstrap::register_rest_routes()` on `rest_api_init`.
	 *
	 * @return void
	 */
	public static function register_routes(): void {

		$recipe_id_arg = Rest_Arg::validated( array( self::class, 'validate_recipe_id' ) );

		// GET /uap/v2/recipe/get-most-active
		register_rest_route(
			self::NAMESPACE_ID,
			'/' . self::REST_BASE . '/get-most-active',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( Get_Most_Active_Route::class, 'handle' ),
					'permission_callback' => array( self::class, 'permissions_check' ),
				),
			)
		);

		// GET /uap/v2/recipe/{recipe_id}
		register_rest_route(
			self::NAMESPACE_ID,
			'/' . self::REST_BASE . '/(?P<recipe_id>\d+)',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( Get_Recipe_Route::class, 'handle' ),
					'permission_callback' => array( self::class, 'permissions_check' ),
					'args'                => array(
						'recipe_id' => $recipe_id_arg,
					),
				),
			)
		);

		// POST /uap/v2/recipe/{recipe_id}/reorder
		register_rest_route(
			self::NAMESPACE_ID,
			'/' . self::REST_BASE . '/(?P<recipe_id>\d+)/reorder',
			array(
				array(
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => array( Reorder_Route::class, 'handle' ),
					'permission_callback' => array( self::class, 'permissions_check' ),
					'args'                => array(
						'recipe_id' => $recipe_id_arg,
					),
				),
			)
		);

		// POST /uap/v2/recipe/{recipe_id}/item/{item_type}/{operation}
		register_rest_route(
			self::NAMESPACE_ID,
			'/' . self::REST_BASE . '/(?P<recipe_id>\d+)/item/(?P<item_type>[a-zA-Z0-9_-]+)/(?P<operation>[a-zA-Z0-9_-]+)',
			array(
				array(
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => array( Items_Routes::class, 'dispatch' ),
					'permission_callback' => array( self::class, 'permissions_check' ),
					'args'                => array(
						'recipe_id' => $recipe_id_arg,
						'item_type' => Rest_Arg::key( array( Integration_Item_Types::class, 'is_valid' ) ),
						'operation' => Rest_Arg::key( array( Items_Routes::class, 'validate_operation' ) ),
					),
				),
			)
		);

		// POST /uap/v2/recipe/{recipe_id}/block/{block_type}/{operation}
		register_rest_route(
			self::NAMESPACE_ID,
			'/' . self::REST_BASE . '/(?P<recipe_id>\d+)/block/(?P<block_type>[a-zA-Z0-9_-]+)/(?P<operation>[a-zA-Z0-9_-]+)',
			array(
				array(
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => array( Blocks_Routes::class, 'dispatch' ),
					'permission_callback' => array( self::class, 'permissions_check' ),
					'args'                => array(
						'recipe_id'      => $recipe_id_arg,
						'block_type'     => Rest_Arg::key( array( Block_Type::class, 'is_valid' ) ),
						'operation'      => Rest_Arg::key( array( Blocks_Routes::class, 'validate_operation' ) ),
						// Only `delete` reads it; declared here because the
						// four block operations share one route definition.
						'delete_content' => Rest_Arg::boolean(),
					),
				),
			)
		);

		// POST /uap/v2/recipe/{recipe_id}/block-modifier/{block_modifier_type}/{operation}
		register_rest_route(
			self::NAMESPACE_ID,
			'/' . self::REST_BASE . '/(?P<recipe_id>\d+)/block-modifier/(?P<block_modifier_type>[a-zA-Z0-9_-]+)/(?P<operation>[a-zA-Z0-9_-]+)',
			array(
				array(
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => array( Block_Modifiers_Routes::class, 'dispatch' ),
					'permission_callback' => array( self::class, 'permissions_check' ),
					'args'                => array(
						'recipe_id'           => $recipe_id_arg,
						'block_modifier_type' => Rest_Arg::key( array( Block_Modifier_Types::class, 'is_valid' ) ),
						'operation'           => Rest_Arg::key( array( Block_Modifiers_Routes::class, 'validate_operation' ) ),
					),
				),
			)
		);

		// GET /uap/v2/recipe/{recipe_id}/node-tests/{kind}/{node_id}
		register_rest_route(
			self::NAMESPACE_ID,
			'/' . self::REST_BASE . '/(?P<recipe_id>\d+)/node-tests/(?P<kind>item|block|block-modifier)/(?P<node_id>\d+)',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( Node_Tests_Routes::class, 'list_tests' ),
					'permission_callback' => array( self::class, 'permissions_check' ),
					'args'                => array(
						'recipe_id' => $recipe_id_arg,
						'node_id'   => Rest_Arg::positive_int(),
					),
				),
			)
		);

		// POST + DELETE /uap/v2/recipe/{recipe_id}/node-tests/{kind}/{node_id}/test/{test_id}/run
		register_rest_route(
			self::NAMESPACE_ID,
			'/' . self::REST_BASE . '/(?P<recipe_id>\d+)/node-tests/(?P<kind>item|block|block-modifier)/(?P<node_id>\d+)/test/(?P<test_id>[a-zA-Z0-9_-]+)/run',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( Node_Tests_Routes::class, 'run' ),
					'permission_callback' => array( self::class, 'permissions_check' ),
					'args'                => array(
						'recipe_id' => $recipe_id_arg,
						'node_id'   => Rest_Arg::positive_int(),
					),
				),
				array(
					'methods'             => \WP_REST_Server::DELETABLE,
					'callback'            => array( Node_Tests_Routes::class, 'cancel' ),
					'permission_callback' => array( self::class, 'permissions_check' ),
					'args'                => array(
						'recipe_id' => $recipe_id_arg,
						'node_id'   => Rest_Arg::positive_int(),
					),
				),
			)
		);

		// GET /uap/v2/recipe/{recipe_id}/node-tests/{kind}/{node_id}/test/{test_id}/run-status
		register_rest_route(
			self::NAMESPACE_ID,
			'/' . self::REST_BASE . '/(?P<recipe_id>\d+)/node-tests/(?P<kind>item|block|block-modifier)/(?P<node_id>\d+)/test/(?P<test_id>[a-zA-Z0-9_-]+)/run-status',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( Node_Tests_Routes::class, 'run_status' ),
					'permission_callback' => array( self::class, 'permissions_check' ),
					'args'                => array(
						'recipe_id' => $recipe_id_arg,
						'node_id'   => Rest_Arg::positive_int(),
					),
				),
			)
		);

		// POST /uap/v2/recipe/{recipe_id}/settings/{operation}
		register_rest_route(
			self::NAMESPACE_ID,
			'/' . self::REST_BASE . '/(?P<recipe_id>\d+)/settings/(?P<operation>[a-zA-Z0-9_-]+)',
			array(
				array(
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => array( Recipe_Settings_Rest::class, 'dispatch' ),
					'permission_callback' => array( self::class, 'permissions_check' ),
					'args'                => array(
						'recipe_id' => $recipe_id_arg,
						'operation' => Rest_Arg::key( array( Recipe_Settings_Rest::class, 'validate_operation' ) ),
					),
				),
			)
		);

		// POST /uap/v2/recipe/{recipe_id}/details/{field}
		register_rest_route(
			self::NAMESPACE_ID,
			'/' . self::REST_BASE . '/(?P<recipe_id>\d+)/details/(?P<field>[a-zA-Z0-9_-]+)',
			array(
				array(
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => array( Recipe_Details_Rest::class, 'dispatch' ),
					'permission_callback' => array( self::class, 'permissions_check' ),
					'args'                => array(
						'recipe_id' => $recipe_id_arg,
						'field'     => Rest_Arg::key( array( Recipe_Details_Rest::class, 'validate_field' ) ),
					),
				),
			)
		);

		// POST /uap/v2/recipe/{recipe_id}/run-now
		register_rest_route(
			self::NAMESPACE_ID,
			'/' . self::REST_BASE . '/(?P<recipe_id>\d+)/run-now',
			array(
				array(
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => array( Run_Now_Rest::class, 'dispatch' ),
					'permission_callback' => array( self::class, 'permissions_check' ),
					'args'                => array(
						'recipe_id' => $recipe_id_arg,
					),
				),
			)
		);

		// POST /uap/v2/recipe/{recipe_id}/run_as/set
		register_rest_route(
			self::NAMESPACE_ID,
			'/' . self::REST_BASE . '/(?P<recipe_id>\d+)/run_as/set',
			array(
				array(
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => array( Run_As_Rest::class, 'dispatch' ),
					'permission_callback' => array( self::class, 'permissions_check' ),
					'args'                => array(
						'recipe_id' => $recipe_id_arg,
					),
				),
			)
		);

		// GET /uap/v2/recipe-taxonomies
		register_rest_route(
			self::NAMESPACE_ID,
			'/recipe-taxonomies',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( Recipe_Taxonomies_Rest::class, 'dispatch' ),
					'permission_callback' => array( self::class, 'permissions_check' ),
				),
			)
		);

		// POST /uap/v2/action-log/{action_log_id}/resend
		register_rest_route(
			self::NAMESPACE_ID,
			'/action-log/(?P<action_log_id>\d+)/resend',
			array(
				array(
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => array( Resend_Action_Rest::class, 'dispatch' ),
					'permission_callback' => array( self::class, 'permissions_check' ),
					'args'                => array(
						'action_log_id' => Rest_Arg::integer(),
					),
				),
			)
		);
	}

	/**
	 * Validate the `recipe_id` URL segment used by every recipe-scoped
	 * route. Referenced as `[ self::class, 'validate_recipe_id' ]` from
	 * the `register_rest_route()` args specs above.
	 *
	 * @param mixed $value
	 * @return bool
	 */
	public static function validate_recipe_id( $value ): bool {
		if ( empty( (int) $value ) || (int) $value <= 0 ) {
			return false;
		}

		$store  = new WP_Recipe_Store();
		$recipe = $store->get_wp_post( (int) $value );

		return ! empty( $recipe );
	}
}
