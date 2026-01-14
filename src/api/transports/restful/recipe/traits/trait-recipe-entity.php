<?php
declare(strict_types=1);

namespace Uncanny_Automator\Api\Transports\Restful\Recipe\Traits;

use WP_REST_Request;

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
	 * The parent ID - int or string, strings represent condition-group placeholders
	 *
	 * @var int|string|null
	 */
	private $parent_id;

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
	 * @param int|string|null $parent_id The parent ID.
	 *
	 * @return void
	 */
	protected function set_parent_id( $parent_id ): void {
		$this->parent_id = $parent_id;
	}

	/**
	 * Set the fields.
	 *
	 * @param array $fields The fields.
	 *
	 * @return void
	 */
	protected function set_fields( array $fields ): void {
		$this->fields = $fields;
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
	 * @return int|string|null
	 */
	protected function get_parent_id() {
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
	 * Get the request.
	 *
	 * @return WP_REST_Request
	 */
	protected function get_request(): WP_REST_Request {
		return $this->request;
	}

	/**
	 * Get the recipe object.
	 *
	 * @return object
	 */
	protected function get_recipe_object(): object {
		return Automator()->get_recipe_object( $this->get_recipe_id(), 'OBJECT' );
	}

	/**
	 * Legacy recipe object.
	 *
	 * @return array
	 */
	protected function get_recipe_object_legacy(): array {
		return Automator()->get_recipes_data( true, $this->get_recipe_id() );
	}
}
