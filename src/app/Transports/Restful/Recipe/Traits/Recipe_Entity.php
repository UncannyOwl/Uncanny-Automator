<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Restful\Recipe\Traits;

use Uncanny_Automator\App\Bridge\Automator_Recipe_Object_Bridge;
use Uncanny_Automator\App\Bridge\Recipe_Object_Bridge;
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
	 * Bridge to the legacy recipe-object lookups.
	 *
	 * @var Recipe_Object_Bridge|null
	 */
	private ?Recipe_Object_Bridge $recipes_bridge = null;

	/**
	 * Inject a recipe-object bridge (test seam).
	 *
	 * Per review note C22, this trait keeps the lazy-resolver pattern
	 * (not full constructor DI) because traits can't elegantly take
	 * constructor parameters — `Recipe_Entity::__construct( int, WP_REST_Request )`
	 * already has a fixed signature consumed by every using class. The
	 * protected setter gives subclass tests a seam without forcing every
	 * consumer of the trait to plumb a bridge through their constructor.
	 *
	 * @param Recipe_Object_Bridge $bridge Bridge implementation.
	 * @return void
	 */
	protected function set_recipes_bridge( Recipe_Object_Bridge $bridge ): void {
		$this->recipes_bridge = $bridge;
	}

	/**
	 * Resolve the recipe-object bridge (lazy default).
	 *
	 * @return Recipe_Object_Bridge
	 */
	private function recipes_bridge(): Recipe_Object_Bridge {
		if ( null === $this->recipes_bridge ) {
			$this->recipes_bridge = new Automator_Recipe_Object_Bridge();
		}

		return $this->recipes_bridge;
	}

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
	 * Returns whatever the recipe-object bridge returns — typically a
	 * Structure VO from `Services\Recipe\Structure::retrieve()`. May
	 * return `null` for a missing recipe; consumers must handle that.
	 *
	 * The pre-bridge implementation here returned `Automator()->get_recipe_object( $id, 'OBJECT' )`
	 * directly, which has the same null-on-missing semantics — the trait
	 * was previously typed `: object` (non-nullable), which silently lied.
	 * Phase 1c of the api-layer refactor tightened this to `?object`.
	 *
	 * @return object|null
	 */
	protected function get_recipe_object(): ?object {
		return $this->recipes_bridge()->get_recipe_as_object( $this->get_recipe_id() );
	}

	/**
	 * Legacy recipe object.
	 *
	 * @return array
	 */
	protected function get_recipe_object_legacy(): array {
		return $this->recipes_bridge()->get_recipes_data( true, $this->get_recipe_id() );
	}
}
