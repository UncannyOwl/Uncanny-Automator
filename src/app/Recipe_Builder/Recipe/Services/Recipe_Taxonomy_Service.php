<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Recipe_Builder\Recipe\Services;

use Uncanny_Automator\App\Events\Recipe_Change_Events;
use Uncanny_Automator\App\Infrastructure\Database\Database;
use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Recipe_Store;
use Uncanny_Automator\App\Events\Enums\Recipe_Change_Operation;
use Uncanny_Automator\App\Events\Dtos\Recipe_Changed;
use WP_Error;
use WP_Term;

/**
 * Recipe taxonomy read/write service — `recipe_category` + `recipe_tag`.
 *
 * Owns the wire shaping of terms (for the recipe wire + the available-terms
 * endpoint) and the term-object write path (resolve existing / create new +
 * the `created` map). The "details" grouping lives at the /details endpoint;
 * this is split out from Recipe_Details_Service because it spans reads the
 * details writes do not.
 *
 * Lazy-default DI per Rule 9c.
 */
final class Recipe_Taxonomy_Service {

	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Recipe store — persistence boundary for term reads/writes.
	 *
	 * @var Recipe_Store
	 */
	private Recipe_Store $recipe_store;

	/**
	 * Singleton accessor.
	 *
	 * @return self
	 */
	public static function instance(): self {
		return self::$instance ??= new self();
	}

	/**
	 * Lazy-default DI per Rule 9c.
	 *
	 * @param Recipe_Store|null $recipe_store
	 */
	public function __construct( ?Recipe_Store $recipe_store = null ) {
		$this->recipe_store = $recipe_store ?? Database::get_recipe_store();
	}

	/**
	 * The recipe's assigned terms in a taxonomy as wire term objects.
	 *
	 * @return array<int,array{id:int,name:string,slug:string,parent:int}>
	 */
	public function get_assigned_terms_wire( int $recipe_id, string $taxonomy ): array {
		return array_values(
			array_map(
				array( $this, 'term_to_wire' ),
				$this->recipe_store->get_assigned_terms( $recipe_id, $taxonomy )
			)
		);
	}

	/**
	 * The full term universe for both recipe taxonomies (incl. unused).
	 *
	 * @return array{categories:array,tags:array}
	 */
	public function get_all_terms_wire(): array {
		return array(
			'categories' => array_values(
				array_map( array( $this, 'term_to_wire' ), $this->recipe_store->get_all_terms( 'recipe_category' ) )
			),
			'tags'       => array_values(
				array_map( array( $this, 'term_to_wire' ), $this->recipe_store->get_all_terms( 'recipe_tag' ) )
			),
		);
	}

	/**
	 * Replace the recipe's terms in a taxonomy from an array of wire term
	 * objects. `id > 0` assigns the existing term; `id <= 0` creates by name
	 * (categories carry `parent`, tags stay flat) and assigns. Returns the
	 * `created` map — one row per `id <= 0`, unconditionally (incl. when a
	 * create resolved onto an existing term via `term_exists`).
	 *
	 * @param array $terms Array of { id, name, slug, parent }.
	 * @return array{created:array}|WP_Error
	 */
	public function set_terms( int $recipe_id, string $taxonomy, array $terms ) {
		return Recipe_Change_Events::operation(
			function () use ( $recipe_id, $taxonomy, $terms ) {
				$missing = $this->guard_recipe_exists( $recipe_id );
				if ( null !== $missing ) {
					return $missing;
				}

				$assign_ids = array();
				$created    = array();

				foreach ( $terms as $term ) {
					$id     = (int) ( $term['id'] ?? 0 );
					$name   = trim( (string) ( $term['name'] ?? '' ) );
					$parent = (int) ( $term['parent'] ?? 0 );

					if ( $id > 0 ) {
						$existing = get_term( $id, $taxonomy );
						if ( $existing instanceof WP_Term ) {
							$assign_ids[] = $id;
						}
						continue;
					}

					$result = $this->recipe_store->create_term(
						$name,
						$taxonomy,
						'recipe_category' === $taxonomy ? $parent : 0
					);
					if ( is_wp_error( $result ) ) {
						return $result;
					}

					$resolved_id  = (int) $result['term_id'];
					$assign_ids[] = $resolved_id;
					$canonical    = get_term( $resolved_id, $taxonomy );

					$created[] = array(
						'client_id' => $id,
						'id'        => $resolved_id,
						'name'      => $canonical instanceof WP_Term ? (string) $canonical->name : $name,
						'slug'      => $canonical instanceof WP_Term ? (string) $canonical->slug : '',
						'parent'    => $canonical instanceof WP_Term ? (int) $canonical->parent : 0,
					);
				}

				$this->recipe_store->assign_terms( $recipe_id, array_values( array_unique( $assign_ids ) ), $taxonomy );

				Recipe_Change_Events::changed( new Recipe_Changed( Recipe_Change_Operation::UPDATED, Recipe_Changed::RECIPE, $recipe_id, $recipe_id ) );

				return array( 'created' => $created );
			}
		);
	}

	/**
	 * Slug-based assign for the MCP/programmatic path. Null skips that
	 * taxonomy. Delegates to the store's slug writers.
	 *
	 * @param int        $recipe_id  Recipe post id.
	 * @param array|null $categories Category slugs (replaces existing). Null to skip.
	 * @param array|null $tags       Tag slugs (replaces existing). Null to skip.
	 */
	public function assign_taxonomies( int $recipe_id, ?array $categories, ?array $tags ): void {
		Recipe_Change_Events::operation(
			function () use ( $recipe_id, $categories, $tags ) {
				if ( $recipe_id <= 0 ) {
					return;
				}

				if ( is_array( $categories ) ) {
					$this->recipe_store->update_categories( $recipe_id, $categories );
				}

				if ( is_array( $tags ) ) {
					$this->recipe_store->update_tags( $recipe_id, $tags );
				}

				if ( is_array( $categories ) || is_array( $tags ) ) {
					Recipe_Change_Events::changed( new Recipe_Changed( Recipe_Change_Operation::UPDATED, Recipe_Changed::RECIPE, $recipe_id, $recipe_id ) );
				}
			}
		);
	}

	/**
	 * Guard — `recipe_not_found` WP_Error when the id doesn't resolve, else null.
	 *
	 * @return WP_Error|null
	 */
	private function guard_recipe_exists( int $recipe_id ) {
		if ( null !== $this->recipe_store->get_wp_post( $recipe_id ) ) {
			return null;
		}

		return new WP_Error(
			'recipe_not_found',
			sprintf(
				// translators: %d is the recipe post ID.
				esc_html_x( 'Recipe %d not found.', 'Recipe taxonomy service error', 'uncanny-automator' ),
				$recipe_id
			)
		);
	}

	/**
	 * Shape a WP_Term into the wire term contract.
	 *
	 * @return array{id:int,name:string,slug:string,parent:int}
	 */
	private function term_to_wire( WP_Term $term ): array {
		return array(
			'id'     => (int) $term->term_id,
			'name'   => (string) $term->name,
			'slug'   => (string) $term->slug,
			'parent' => (int) $term->parent,
		);
	}
}
