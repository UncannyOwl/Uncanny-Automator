<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Restful\Recipe\Traits;

use Uncanny_Automator\App\Recipe_Builder\Recipe\Wire_Serializer\Recipe_Violation_Annotator;
use Uncanny_Automator\App\Transports\Restful\Recipe\Recipe_Response;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Trait for shared recipe entity REST properties and helper methods.
 *
 * Provides getters and setters for properties shared by both items and blocks,
 * including constructor logic and recipe object retrieval.
 *
 * @since 7.0
 */
trait Recipe_Entity {

	/**
	 * The recipe post ID
	 *
	 * @var int
	 */
	private int $recipe_id;

	/**
	 * The request object
	 *
	 * @var WP_REST_Request
	 */
	private WP_REST_Request $request;

	/**
	 * The parent ID (recipe or parent block post ID).
	 *
	 * @var int|null
	 */
	private ?int $parent_id = null;

	/**
	 * The fields
	 *
	 * @var array
	 */
	private array $fields;

	/**
	 * Constructor.
	 *
	 * @param int $recipe_id The recipe post ID.
	 * @param WP_REST_Request $request The request.
	 *
	 * @return void
	 */
	public function __construct( int $recipe_id, WP_REST_Request $request ) {
		$this->set_recipe_id( $recipe_id );
		$this->set_request( $request );
		$this->setup();
	}

	/**
	 * Setup for extending classes.
	 *
	 * @return void
	 */
	protected function setup(): void {
		// Override in child classes as needed vs constructor.
	}

	/**
	 * Set the recipe ID.
	 *
	 * @param int $recipe_id The recipe ID.
	 *
	 * @return void
	 */
	protected function set_recipe_id( int $recipe_id ): void {
		$this->recipe_id = $recipe_id;
	}

	/**
	 * Set the parent ID.
	 *
	 * @param int|null $parent_id The parent post ID.
	 *
	 * @return void
	 */
	protected function set_parent_id( ?int $parent_id ): void {
		$this->parent_id = $parent_id;
	}

	/**
	 * Set the fields.
	 *
	 * Accepts an array or a JSON string and normalizes to array.
	 *
	 * @param array|string $fields The fields as array or JSON string.
	 *
	 * @return void
	 */
	protected function set_fields( $fields ): void {
		if ( is_string( $fields ) ) {
			$fields = json_decode( $fields, true );
		}

		$this->fields = is_array( $fields ) ? $fields : array();
	}

	/**
	 * Set the request.
	 *
	 * @param WP_REST_Request $request The request.
	 *
	 * @return void
	 */
	protected function set_request( WP_REST_Request $request ): void {
		$this->request = $request;
	}

	/**
	 * Get the recipe ID.
	 *
	 * @return int
	 */
	protected function get_recipe_id(): int {
		return $this->recipe_id;
	}

	/**
	 * Get the parent ID.
	 *
	 * @return int|null
	 */
	protected function get_parent_id(): ?int {
		return $this->parent_id;
	}

	/**
	 * Get the fields.
	 *
	 * @return array
	 */
	protected function get_fields(): array {
		return $this->fields;
	}

	/**
	 * Get the requested path code, when the entity targets a specific path
	 * of a path-aware parent block. Null when absent or empty.
	 *
	 * @return string|null
	 */
	protected function get_path_code(): ?string {
		$path_code = $this->get_request()->get_param( 'path_code' );
		return ( is_string( $path_code ) && '' !== $path_code ) ? $path_code : null;
	}

	/**
	 * Get the request.
	 *
	 * @return WP_REST_Request
	 */
	protected function get_request(): WP_REST_Request {
		return $this->request;
	}

	/**
	 * Get the recipe as a `ModelRecipe`-shaped wire array.
	 *
	 * Delegates to `Recipe_Response::wire()` — the single wire-retrieval point
	 * for the recipe REST surface. Returns null when the id doesn't resolve to
	 * a `uo-recipe`.
	 *
	 * Used by the new-transport mutation handlers (`Recipe_Block_Rest`,
	 * `Recipe_Item_Rest`) for the `recipe` response key.
	 *
	 * @return array<string, mixed>|null
	 */
	protected function get_recipe_wire(): ?array {
		return Recipe_Response::wire( $this->get_recipe_id() );
	}

	/**
	 * The 422 envelope builder, provided by `Rest_Responses`. Declared here
	 * so a class that composes this trait without that one fails to compose
	 * instead of failing on its first refusal.
	 *
	 * @param array  $violations Wire-shape violation list (non-empty).
	 * @param array  $recipe     Full wire recipe, status unchanged.
	 * @param string $message    Localized top-level summary.
	 *
	 * @return WP_REST_Response
	 */
	abstract protected static function rejection( array $violations, array $recipe, string $message ): WP_REST_Response;

	/**
	 * Map a `Recipe_Violation_Service::REJECTION_CODE` WP_Error to the 422
	 * rejection envelope, with the violations stamped onto the current wire
	 * recipe. The envelope itself comes from `Rest_Responses::rejection()`.
	 *
	 * @param \WP_Error $error Rejection built by Recipe_Violation_Service::rejection_error().
	 *
	 * @return WP_REST_Response
	 */
	protected function rejection_from_error( \WP_Error $error ): WP_REST_Response {
		$data       = (array) $error->get_error_data();
		$violations = (array) ( $data['violations'] ?? array() );

		return static::rejection(
			$violations,
			( new Recipe_Violation_Annotator() )->annotate( $this->get_recipe_wire() ?? array(), $violations ),
			$error->get_error_message()
		);
	}
}
