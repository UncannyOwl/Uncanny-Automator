<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Recipe_Builder\Recipe\Services;

use Uncanny_Automator\App\Events\Recipe_Change_Events;
use Uncanny_Automator\App\Infrastructure\Database\Database;
use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Recipe_Store;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Services\Utilities\Recipe_Validator;
use Uncanny_Automator\App\Events\Enums\Recipe_Change_Operation;
use Uncanny_Automator\App\Events\Dtos\Recipe_Changed;
use WP_Error;

/**
 * Targeted read/write service for the recipe's scalar post-entity "detail"
 * fields — notes and title. These do NOT change how the recipe runs (that's
 * Recipe_Settings_Service's domain); they are the post-level surface the
 * recipe-builder header edits. Taxonomy (categories/tags) lives in
 * Recipe_Taxonomy_Service.
 *
 * Lazy-default DI per Rule 9c.
 */
final class Recipe_Details_Service {

	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Recipe store — persistence boundary for recipe reads and the targeted
	 * detail-field writers.
	 *
	 * @var Recipe_Store
	 */
	private Recipe_Store $recipe_store;

	/**
	 * Validator/sanitizer for detail-field inputs (e.g. notes).
	 *
	 * @var Recipe_Validator
	 */
	private Recipe_Validator $validator;

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
	 * @param Recipe_Store|null    $recipe_store
	 * @param Recipe_Validator|null $validator
	 */
	public function __construct( ?Recipe_Store $recipe_store = null, ?Recipe_Validator $validator = null ) {
		$this->recipe_store = $recipe_store ?? Database::get_recipe_store();
		$this->validator    = $validator ?? new Recipe_Validator();
	}

	/**
	 * Guard — `recipe_not_found` WP_Error when the id doesn't resolve to a
	 * recipe post, else null. Shared by every write method.
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
				esc_html_x( 'Recipe %d not found.', 'Recipe details service error', 'uncanny-automator' ),
				$recipe_id
			)
		);
	}

	/**
	 * Read the recipe's notes via the store's narrow reader ('' when unset).
	 */
	public function get_notes( int $recipe_id ): string {
		return $this->recipe_store->get_notes( $recipe_id );
	}

	/**
	 * Write the recipe's notes via the store's targeted writer.
	 *
	 * @return array{success: true}|WP_Error
	 */
	public function update_notes( int $recipe_id, string $notes ) {
		return Recipe_Change_Events::operation(
			function () use ( $recipe_id, $notes ) {
				$missing = $this->guard_recipe_exists( $recipe_id );
				if ( null !== $missing ) {
					return $missing;
				}

				$sanitized = $this->validator->validate_notes( trim( $notes ) );

				try {
					$this->recipe_store->update_notes( $recipe_id, $sanitized );
				} catch ( \Throwable $e ) {
					return new WP_Error( 'update_failed', $e->getMessage() );
				}

				Recipe_Change_Events::changed( new Recipe_Changed( Recipe_Change_Operation::UPDATED, Recipe_Changed::RECIPE, $recipe_id, $recipe_id ) );

				return array( 'success' => true );
			}
		);
	}

	/**
	 * Write the recipe's title via the store's targeted writer.
	 *
	 * Empty/whitespace titles normalize to '(no-title)' (validate_title).
	 *
	 * @return array{success: true}|WP_Error
	 */
	public function update_title( int $recipe_id, string $title ) {
		return Recipe_Change_Events::operation(
			function () use ( $recipe_id, $title ) {
				$missing = $this->guard_recipe_exists( $recipe_id );
				if ( null !== $missing ) {
					return $missing;
				}

				$sanitized = $this->validator->validate_title( $title );

				try {
					$this->recipe_store->update_title( $recipe_id, $sanitized );
				} catch ( \Throwable $e ) {
					return new WP_Error( 'update_failed', $e->getMessage() );
				}

				Recipe_Change_Events::changed( new Recipe_Changed( Recipe_Change_Operation::UPDATED, Recipe_Changed::RECIPE, $recipe_id, $recipe_id ) );

				return array( 'success' => true );
			}
		);
	}
}
