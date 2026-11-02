<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Recipe_Builder\Recipe\Settings\Services;

use Uncanny_Automator\App\Events\Recipe_Change_Events;
use Uncanny_Automator\App\Events\Enums\Recipe_Change_Operation;
use Uncanny_Automator\App\Events\Dtos\Recipe_Changed;
use Uncanny_Automator\App\Infrastructure\Database\Database;
use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Recipe_Store;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Settings\Enums\Recipe_Setting_Id;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Value_Objects\Recipe_Status;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Services\Recipe_Violation_Service;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Value_Objects\Recipe_Trigger_Logic;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Value_Objects\Recipe_Throttle;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Value_Objects\Recipe_Total_Times;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Value_Objects\Recipe_Times_Per_User;

/**
 * Single source of truth for the recipe-level policy strip that the
 * canonical schema-v3 tree emits — `recipe_type`, `trigger_logic`,
 * `throttle`, `total_times`, `times_per_user`.
 *
 * Returns **clean domain values** sourced through the `Recipe` aggregate
 * (via `Recipe_Store`). No FieldsOutput envelopes, no presentation
 * labels — those are wire-layer concerns owned by the wire serializer.
 *
 * Three of the values exposed here (`throttle`, `trigger_logic`, and the
 * item-level `fields` map in Phase 8) were silent dead reads in the PoC
 * tree builder, which read post-meta keys with no writers anywhere in
 * the codebase. Routing through the aggregate fixes the bugs and pins
 * the contract: the cache carries domain facts, not storage shapes.
 */
final class Recipe_Settings_Service {

	/** Singleton holder (Rule 9a). */
	private static ?self $instance = null;

	/**
	 * Return the shared singleton, creating it on first call.
	 */
	public static function instance(): self {
		return self::$instance ??= new self();
	}

	/**
	 * Reads the recipe aggregate the settings are sourced from.
	 *
	 * @var Recipe_Store
	 */
	private Recipe_Store $recipe_store;

	/**
	 * Lazy-default DI (Rule 9c): tests inject a fake store; production gets the default.
	 *
	 * @param Recipe_Store|null $recipe_store
	 */
	public function __construct( ?Recipe_Store $recipe_store = null ) {
		$this->recipe_store = $recipe_store ?? Database::get_recipe_store();
	}

	/**
	 * Whether the recipe runs for a logged-in user or anonymously. Falls back to `user` when the recipe cannot be read.
	 *
	 * @return string 'user' | 'anonymous'
	 */
	public function get_recipe_type( int $recipe_id ): string {
		$recipe = $this->recipe_store->get( $recipe_id );
		if ( null === $recipe ) {
			return 'user';
		}

		return $recipe->get_recipe_type()->get_value();
	}

	/**
	 * The recipe's chosen type, or null when none chosen yet.
	 *
	 * Read-side companion to {@see get_recipe_type()} (which collapses an unset
	 * type to 'user' for execution); this preserves null so the canonical tree
	 * and the wire's top-level `recipe_type` carry the unset state.
	 *
	 * @return string|null 'user' | 'anonymous' | null
	 */
	public function get_recipe_type_or_null( int $recipe_id ): ?string {
		return $this->recipe_store->get_recipe_type_raw( $recipe_id );
	}

	/**
	 * Persist the recipe type.
	 *
	 * Validates the value and writes through the store, whose write invalidates
	 * the cached tree.
	 *
	 * @param int    $recipe_id
	 * @param string $recipe_type 'user' | 'anonymous'
	 * @return bool|\WP_Error
	 */
	public function set_recipe_type( int $recipe_id, string $recipe_type ) {
		return Recipe_Change_Events::operation(
			function () use ( $recipe_id, $recipe_type ) {
				if ( ! in_array( $recipe_type, array( 'user', 'anonymous' ), true ) ) {
					return new \WP_Error( 'automator_invalid_recipe_type', 'Recipe type must be user or anonymous.' );
				}

				$ok = $this->recipe_store->set_recipe_type( $recipe_id, $recipe_type );

				if ( false !== $ok && 0 < $recipe_id ) {
					Recipe_Change_Events::changed( new Recipe_Changed( Recipe_Change_Operation::UPDATED, Recipe_Changed::RECIPE, $recipe_id, $recipe_id ) );
				}

				return $ok;
			}
		);
	}

	/**
	 * Whether every trigger must fire or any one is enough. Falls back to `all` when the recipe cannot be read.
	 *
	 * @return string 'all' | 'any'
	 */
	public function get_trigger_logic( int $recipe_id ): string {
		$recipe = $this->recipe_store->get( $recipe_id );
		if ( null === $recipe ) {
			return Recipe_Trigger_Logic::LOGIC_ALL;
		}

		$logic = $recipe->get_recipe_trigger_logic();
		return null === $logic ? Recipe_Trigger_Logic::LOGIC_ALL : $logic->get_value();
	}

	/**
	 * Canonical declarative throttle shape.
	 *
	 * `{enabled: bool, duration: int|null, unit: string|null, scope: string|null}`
	 *
	 * Disabled throttles return `enabled: false` with the other three
	 * keys nullable — the VO enforces this. The wire serializer projects
	 * the disabled shape to an empty FieldsOutput at emit time.
	 *
	 * @return array{enabled: bool, duration: int|null, unit: string|null, scope: string|null}
	 */
	public function get_throttle( int $recipe_id ): array {
		$recipe = $this->recipe_store->get( $recipe_id );
		if ( null === $recipe ) {
			return Recipe_Throttle::disabled_shape();
		}

		$throttle = $recipe->get_recipe_throttle();
		return null === $throttle ? Recipe_Throttle::disabled_shape() : $throttle->to_array();
	}

	/**
	 * Total allowed runs across all users.
	 *
	 * @return int Saved value, or -1 when unset.
	 */
	public function get_total_times( int $recipe_id ): int {
		$recipe = $this->recipe_store->get( $recipe_id );
		if ( null === $recipe ) {
			return Recipe_Total_Times::UNLIMITED;
		}

		$total = $recipe->get_recipe_total_times();
		return null === $total ? Recipe_Total_Times::UNLIMITED : $total->to_int();
	}

	/**
	 * Allowed runs per user (user recipes only — anonymous recipes always -1).
	 *
	 * @return int Saved value, or -1 when unset / not applicable.
	 */
	public function get_times_per_user( int $recipe_id ): int {
		$recipe = $this->recipe_store->get( $recipe_id );
		if ( null === $recipe ) {
			return Recipe_Times_Per_User::UNLIMITED;
		}

		$times = $recipe->get_recipe_times_per_user();
		return null === $times ? Recipe_Times_Per_User::UNLIMITED : $times->to_int();
	}

	/**
	 * Set one editable recipe-level setting via the store's targeted writer.
	 *
	 * Validates the recipe exists and that the setting id is editable
	 * (rejects derived / read-only ids per `Recipe_Setting_Id::is_editable()`),
	 * then delegates to `Recipe_Store::update_setting()`. The store owns
	 * cache invalidation.
	 *
	 * @param int    $recipe_id  Recipe post id.
	 * @param string $setting_id One of `Recipe_Setting_Id::editable_ids()`.
	 * @param array  $fields     Wire-shape FieldsOutput for the setting.
	 *
	 * @return array{success: true}|\WP_Error Domain result.
	 */
	public function set_setting( int $recipe_id, string $setting_id, array $fields ) {
		return Recipe_Change_Events::operation(
			function () use ( $recipe_id, $setting_id, $fields ) {
				if ( ! $this->recipe_exists( $recipe_id ) ) {
					return new \WP_Error(
						'recipe_not_found',
						sprintf(
							// translators: %d is the recipe post ID.
							esc_html_x( 'Recipe %d not found.', 'Recipe settings service error', 'uncanny-automator' ),
							$recipe_id
						)
					);
				}
				if ( ! Recipe_Setting_Id::is_editable( $setting_id ) ) {
					return new \WP_Error(
						'setting_not_editable',
						sprintf(
							// translators: %s is the recipe-setting ID that was rejected.
							esc_html_x( "Setting '%s' is not editable.", 'Recipe settings service error', 'uncanny-automator' ),
							esc_html( $setting_id )
						)
					);
				}
				try {
					$this->recipe_store->update_setting( $recipe_id, $setting_id, $fields );
				} catch ( \Throwable $e ) {
					return new \WP_Error( 'update_failed', $e->getMessage() );
				}
				Recipe_Change_Events::changed( new Recipe_Changed( Recipe_Change_Operation::UPDATED, Recipe_Changed::RECIPE, $recipe_id, $recipe_id ) );
				return array( 'success' => true );
			}
		);
	}

	/**
	 * Toggle the recipe's post status via the store.
	 *
	 * @param int  $recipe_id Recipe post id.
	 * @param bool $is_on     True → publish, false → draft.
	 *
	 * @return array{success: true}|\WP_Error Domain result.
	 */
	public function set_status( int $recipe_id, bool $is_on ) {
		return Recipe_Change_Events::operation(
			function () use ( $recipe_id, $is_on ) {
				if ( ! $this->recipe_exists( $recipe_id ) ) {
					return new \WP_Error(
						'recipe_not_found',
						sprintf(
							// translators: %d is the recipe post ID.
							esc_html_x( 'Recipe %d not found.', 'Recipe settings service error', 'uncanny-automator' ),
							$recipe_id
						)
					);
				}

				if ( $is_on ) {
					$rejection = Recipe_Violation_Service::rejection_error(
						Recipe_Violation_Service::instance()->evaluate_recipe_publish( $recipe_id ),
						esc_html_x( 'This recipe can’t be set live yet.', 'Recipe settings service error', 'uncanny-automator' )
					);
					if ( $rejection instanceof \WP_Error ) {
						return $rejection;
					}
				}

				try {
					$this->recipe_store->update_status( $recipe_id, $is_on ? Recipe_Status::PUBLISH : Recipe_Status::DRAFT );
				} catch ( \Throwable $e ) {
					return new \WP_Error( 'status_update_failed', $e->getMessage() );
				}
				Recipe_Change_Events::changed( new Recipe_Changed( Recipe_Change_Operation::UPDATED, Recipe_Changed::RECIPE, $recipe_id, $recipe_id ) );
				return array( 'success' => true );
			}
		);
	}

	/**
	 * Guard used by the write methods — confirm the post exists and is a recipe
	 * before delegating to the store.
	 */
	private function recipe_exists( int $recipe_id ): bool {
		return null !== $this->recipe_store->get_wp_post( $recipe_id );
	}
}
