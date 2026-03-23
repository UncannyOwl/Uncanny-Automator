<?php
declare(strict_types=1);
namespace Uncanny_Automator\Api\Transports\Restful\Recipe;

use Uncanny_Automator\Api\Components\Integration\Enums\Integration_Item_Types;
use Uncanny_Automator\Api\Transports\Restful\Utilities\Traits\Restful_Permissions;
use Uncanny_Automator\Api\Transports\Restful\Utilities\Traits\Rest_Responses;
use Uncanny_Automator\Api\Components\Block\Enums\Block_Type;
use Uncanny_Automator\Api\Database\Stores\WP_Recipe_Store;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * Automator REST Controller.
 *
 * WordPress REST API endpoint for Automator.
 */
class Recipe_Rest_Controller {

	use Restful_Permissions;
	use Rest_Responses;

	/**
	 * Namespace for the REST API.
	 *
	 * @var string
	 */
	protected $namespace = AUTOMATOR_REST_API_END_POINT;

	/**
	 * Base route
	 *
	 * @var string
	 */
	protected $rest_base = 'recipe';

	/**
	 * Whitelisted operations
	 *
	 * @var array
	 */
	protected array $whitelisted_operations = array(
		'add',
		'delete',
		'update',
	);

	/**
	 * Item handler classes
	 *
	 * @var array
	 */
	protected array $item_handler_classes = array(
		Integration_Item_Types::TRIGGER          => __NAMESPACE__ . '\Items\Trigger_Rest',
		Integration_Item_Types::ACTION           => __NAMESPACE__ . '\Items\Action_Rest',
		Integration_Item_Types::LOOP_FILTER      => __NAMESPACE__ . '\Items\Loop_Filter_Rest',
		Integration_Item_Types::FILTER_CONDITION => __NAMESPACE__ . '\Items\Filter_Condition_Rest',
		Integration_Item_Types::CLOSURE          => __NAMESPACE__ . '\Items\Closure_Rest',
	);

	/**
	 * Block handler classes
	 *
	 * @var array
	 */
	protected array $block_handler_classes = array(
		Block_Type::FILTER         => __NAMESPACE__ . '\Blocks\Filter_Block_Rest',
		Block_Type::LOOP           => __NAMESPACE__ . '\Blocks\Loop_Block_Rest',
		Block_Type::DELAY_SCHEDULE => __NAMESPACE__ . '\Blocks\Delay_Block_Rest',
	);

	/**
	 * Registers the routes for recipe mutations.
	 *
	 * @return void
	 */
	public function register_routes() {
		// Recipe item route: /wp-json/uap/v2/recipe/1/item/trigger/add
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<recipe_id>\d+)/item/(?P<item_type>[a-zA-Z0-9_-]+)/(?P<operation>[a-zA-Z0-9_-]+)',
			array(
				array(
					'methods'             => WP_REST_Server::EDITABLE, // POST / PUT / PATCH
					'callback'            => array( $this, 'handle_item_operation' ),
					'permission_callback' => array( $this, 'permissions_check' ),
					'args'                => $this->get_item_endpoint_args(),
				),
			)
		);

		// Recipe block route: /wp-json/uap/v2/recipe/1/block/filter/add
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<recipe_id>\d+)/block/(?P<block_type>[a-zA-Z0-9_-]+)/(?P<operation>[a-zA-Z0-9_-]+)',
			array(
				array(
					'methods'             => WP_REST_Server::EDITABLE, // POST / PUT / PATCH
					'callback'            => array( $this, 'handle_block_operation' ),
					'permission_callback' => array( $this, 'permissions_check' ),
					'args'                => $this->get_block_endpoint_args(),
				),
			)
		);
	}

	/**
	 * Defines the item endpoint arguments used for validation.
	 *
	 * @return array
	 */
	protected function get_item_endpoint_args() {
		return array(
			'recipe_id' => array(
				'validate_callback' => array( $this, 'validate_recipe_id' ),
			),
			'item_type' => array(
				'sanitize_callback' => 'sanitize_key',
				'validate_callback' => array( Integration_Item_Types::class, 'is_valid' ),
			),
			'operation' => array(
				'sanitize_callback' => 'sanitize_key',
				'validate_callback' => array( $this, 'validate_operation' ),
			),
		);
	}

	/**
	 * Defines the block endpoint arguments used for validation.
	 *
	 * @return array
	 */
	protected function get_block_endpoint_args() {
		return array(
			'recipe_id' => array(
				'validate_callback' => array( $this, 'validate_recipe_id' ),
			),
			'block_type' => array(
				'sanitize_callback' => 'sanitize_key',
				'validate_callback' => array( Block_Type::class, 'is_valid' ),
			),
			'operation' => array(
				'sanitize_callback' => 'sanitize_key',
				'validate_callback' => array( $this, 'validate_operation' ),
			),
		);
	}

	/**
	 * Validates the recipe ID
	 *
	 * @param mixed $value
	 *
	 * @return bool
	 */
	public function validate_recipe_id( $value ): bool {

		// Validate we have post ID.
		if ( empty( (int) $value ) || (int) $value <= 0 ) {
			return false;
		}

		// Validate it's a recipe post type.
		$store  = new WP_Recipe_Store();
		$recipe = $store->get_wp_post( (int) $value );
		if ( empty( $recipe ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Validates the operation
	 *
	 * @param mixed $value
	 *
	 * @return bool
	 */
	public function validate_operation( $value ): bool {
		return in_array( $value, $this->whitelisted_operations, true );
	}

	/**
	 * Main router that dispatches item add/delete/update actions.
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_item_operation( WP_REST_Request $request ) {
		return $this->handle_entity_operation(
			$request,
			'item_type',
			$this->item_handler_classes,
			'item'
		);
	}

	/**
	 * Main router that dispatches block add/delete/update actions.
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_block_operation( WP_REST_Request $request ) {
		return $this->handle_entity_operation(
			$request,
			'block_type',
			$this->block_handler_classes,
			'block'
		);
	}

	/**
	 * Generic router that dispatches entity operations.
	 *
	 * @param WP_REST_Request $request
	 * @param string          $type_param The request parameter name for entity type.
	 * @param array           $handler_classes Handler class map.
	 * @param string          $entity_label Human-readable entity label for errors.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	private function handle_entity_operation(
		WP_REST_Request $request,
		string $type_param,
		array $handler_classes,
		string $entity_label
	) {
		$recipe_id = (int) $request['recipe_id'];
		$type      = sanitize_key( $request[ $type_param ] );
		$operation = sanitize_key( $request['operation'] );

		$handler = $this->get_handler_instance( $type, $recipe_id, $request, $handler_classes, $entity_label );
		if ( is_wp_error( $handler ) ) {
			return $handler;
		}

		if ( ! method_exists( $handler, $operation ) ) {
			return $this->failure(
				sprintf(
					'Operation "%s" is not supported for %s type "%s".',
					esc_html( $operation ),
					esc_html( $entity_label ),
					esc_html( $type )
				),
				400,
				'automator_rest_invalid_operation'
			);
		}

		return $handler->{$operation}();
	}

	/**
	 * Generic handler instance resolver.
	 *
	 * @param string          $type Entity type.
	 * @param int             $recipe_id Recipe ID.
	 * @param WP_REST_Request $request Request object.
	 * @param array           $handler_classes Handler class map.
	 * @param string          $entity_label Human-readable entity label for errors.
	 *
	 * @return object|WP_Error Handler instance or error.
	 */
	private function get_handler_instance(
		string $type,
		int $recipe_id,
		WP_REST_Request $request,
		array $handler_classes,
		string $entity_label
	) {
		$class = $handler_classes[ $type ] ?? null;

		if ( ! $class || ! class_exists( $class ) ) {
			return $this->failure(
				sprintf( 'Unknown %s type "%s".', esc_html( $entity_label ), esc_html( $type ) ),
				400,
				sprintf( 'automator_rest_unknown_%s_type', esc_html( $entity_label ) )
			);
		}

		return new $class( $recipe_id, $request );
	}
}
