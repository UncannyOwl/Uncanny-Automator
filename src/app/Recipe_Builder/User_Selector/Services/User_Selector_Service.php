<?php
/**
 * User Selector Service.
 *
 * Application service that orchestrates user selector operations.
 * Bridges the gap between MCP tools and domain layer.
 *
 * @since 7.0.0
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Recipe_Builder\User_Selector\Services;

use Uncanny_Automator\App\Events\Recipe_Change_Events;
use Uncanny_Automator\App\Recipe_Builder\User_Selector\User_Selector;
use Uncanny_Automator\App\Recipe_Builder\User_Selector\User_Selector_Config;
use Uncanny_Automator\App\Infrastructure\Database\Interfaces\User_Selector_Store;
use Uncanny_Automator\App\Infrastructure\Database\Stores\WP_User_Selector_Store;
use Uncanny_Automator\App\Recipe_Builder\User_Selector\Services\Utilities\User_Selector_Wire_Mapper;
use Uncanny_Automator\App\Recipe_Builder\User_Selector\Value_Objects\User_Selector_Source;
use Uncanny_Automator\App\Events\Enums\Recipe_Change_Operation;
use Uncanny_Automator\App\Events\Dtos\Recipe_Changed;

/**
 * User_Selector_Service Class.
 *
 * Provides application-level operations for user selector management.
 */
class User_Selector_Service {

	/**
	 * Service instance (singleton pattern).
	 *
	 * @var User_Selector_Service|null
	 */
	private static $instance = null;

	/**
	 * User selector store.
	 *
	 * @var User_Selector_Store
	 */
	private $store;

	/**
	 * Whether this site can use a run-as setting (a user resolver is registered).
	 *
	 * @var User_Selector_Availability
	 */
	private User_Selector_Availability $availability;

	/**
	 * Constructor.
	 *
	 * Allows dependency injection for testing while maintaining
	 * lazy loading for production use.
	 *
	 * @param User_Selector_Store|null        $store        Optional store for testing.
	 * @param User_Selector_Availability|null $availability Optional availability for testing.
	 */
	public function __construct( ?User_Selector_Store $store = null, ?User_Selector_Availability $availability = null ) {
		$this->store        = $store ?? new WP_User_Selector_Store();
		$this->availability = $availability ?? new User_Selector_Availability();
	}

	/**
	 * Get service instance (singleton).
	 *
	 * @return User_Selector_Service
	 */
	public static function instance(): User_Selector_Service {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Create or update a user selector for a recipe.
	 *
	 * The one authoring write behind REST and MCP. Refused with
	 * `User_Selector_Availability::REQUIRES_PRO` when no user resolver is
	 * registered, because nothing would act on the setting.
	 *
	 * @param int   $recipe_id Recipe ID.
	 * @param array $data      User selector data.
	 * @return array|\WP_Error User selector data on success, WP_Error on failure.
	 */
	public function save_user_selector( int $recipe_id, array $data ) {
		return $this->write( $recipe_id, $data, true );
	}

	/**
	 * Copy a recipe's existing user selector onto another recipe: a
	 * duplicate, or an imported recipe.
	 *
	 * Copying carries a setting the source recipe already holds, so it is not
	 * refused when no user resolver is registered: the copy keeps the setting
	 * the way the source recipe does, and the run ignores it the same way.
	 *
	 * @since 8.0.0
	 *
	 * @param int   $recipe_id Recipe ID of the copy.
	 * @param array $data      User selector data of the source recipe.
	 * @return array|\WP_Error User selector data on success, WP_Error on failure.
	 */
	public function copy_user_selector( int $recipe_id, array $data ) {
		return $this->write( $recipe_id, $data, false );
	}

	/**
	 * Validate and persist a user selector as one recipe operation.
	 *
	 * @param int   $recipe_id          Recipe ID.
	 * @param array $data               User selector data.
	 * @param bool  $needs_availability Whether the write is refused when no user resolver is registered.
	 * @return array|\WP_Error User selector data on success, WP_Error on failure.
	 */
	private function write( int $recipe_id, array $data, bool $needs_availability ) {
		return Recipe_Change_Events::operation(
			function () use ( $recipe_id, $data, $needs_availability ) {
				try {
					// Validate recipe exists.
					$recipe = get_post( $recipe_id );
					if ( ! $recipe || AUTOMATOR_POST_TYPE_RECIPE !== $recipe->post_type ) {
						return new \WP_Error(
							'invalid_recipe',
							sprintf( 'Recipe with ID %d not found', $recipe_id ),
							array( 'status' => 404 )
						);
					}

					$refusal = $needs_availability ? $this->availability->refusal() : null;
					if ( null !== $refusal ) {
						return $refusal;
					}

					// Build config from data.
					$config = $this->build_config_from_data( $recipe_id, $data );

					// Create domain object (validates invariants).
					$user_selector = new User_Selector( $config );

					// Persist.
					$saved = $this->store->save( $user_selector );

					$result = array(
						'message' => 'User selector saved successfully',
						'data'    => $saved->to_array(),
					);

				} catch ( \InvalidArgumentException $e ) {
					return new \WP_Error(
						'validation_error',
						$e->getMessage(),
						array( 'status' => 400 )
					);
				} catch ( \Exception $e ) {
					return new \WP_Error(
						'save_error',
						'Failed to save user selector: ' . $e->getMessage(),
						array( 'status' => 500 )
					);
				}

				Recipe_Change_Events::changed( new Recipe_Changed( Recipe_Change_Operation::UPDATED, Recipe_Changed::RECIPE, $recipe_id, $recipe_id ) );

				return $result;
			}
		);
	}

	/**
	 * Get user selector for a recipe.
	 *
	 * @param int $recipe_id Recipe ID.
	 * @return array|\WP_Error User selector data on success, WP_Error on failure.
	 */
	public function get_user_selector( int $recipe_id ) {
		try {
			// Validate recipe exists.
			$recipe = get_post( $recipe_id );
			if ( ! $recipe || AUTOMATOR_POST_TYPE_RECIPE !== $recipe->post_type ) {
				return new \WP_Error(
					'invalid_recipe',
					sprintf( 'Recipe with ID %d not found', $recipe_id ),
					array( 'status' => 404 )
				);
			}

			$user_selector = $this->store->get_by_recipe_id( $recipe_id );

			if ( null === $user_selector ) {
				return array(
					'message' => 'No user selector configured for this recipe',
					'data'    => null,
				);
			}

			return array(
				'message' => 'User selector retrieved successfully',
				'data'    => $user_selector->to_array(),
			);

		} catch ( \Exception $e ) {
			return new \WP_Error(
				'get_error',
				'Failed to get user selector: ' . $e->getMessage(),
				array( 'status' => 500 )
			);
		}
	}

	/**
	 * Whether any published Everyone recipe's run-as logs the user in.
	 *
	 * @since 8.0.0
	 *
	 * @return bool
	 */
	public function any_published_recipe_logs_user_in(): bool {
		return $this->store->any_published_recipe_logs_user_in();
	}

	/**
	 * Project the recipe's user selector as a self-describing FieldsOutput
	 * envelope, or null when no selector is configured for the recipe.
	 *
	 * Returns `{ fields: <option_code-keyed FieldsOutput> }` on success.
	 * The `source` is folded into `fields['SOURCE']` by the wire mapper;
	 * there is no top-level `source` key in the returned array.
	 *
	 * @param int $recipe_id Recipe post ID.
	 * @return array{fields: array}|null Projection on success, null when no selector exists.
	 */
	public function project_for_recipe( int $recipe_id ): ?array {
		$selector = $this->store->get_by_recipe_id( $recipe_id );
		if ( null === $selector ) {
			return null;
		}

		return array(
			'fields' => ( new User_Selector_Wire_Mapper() )->to_wire_fields( $selector ),
		);
	}

	/**
	 * Delete user selector for a recipe.
	 *
	 * @param int $recipe_id Recipe ID.
	 * @return array|\WP_Error Success message on success, WP_Error on failure.
	 */
	public function delete_user_selector( int $recipe_id ) {
		return Recipe_Change_Events::operation(
			function () use ( $recipe_id ) {
				try {
					// Validate recipe exists.
					$recipe = get_post( $recipe_id );
					if ( ! $recipe || AUTOMATOR_POST_TYPE_RECIPE !== $recipe->post_type ) {
						return new \WP_Error(
							'invalid_recipe',
							sprintf( 'Recipe with ID %d not found', $recipe_id ),
							array( 'status' => 404 )
						);
					}

					// Check if user selector exists.
					if ( ! $this->store->exists_for_recipe( $recipe_id ) ) {
						return new \WP_Error(
							'not_found',
							'No user selector configured for this recipe',
							array( 'status' => 404 )
						);
					}

					$this->store->delete_by_recipe_id( $recipe_id );

					Recipe_Change_Events::changed( new Recipe_Changed( Recipe_Change_Operation::UPDATED, Recipe_Changed::RECIPE, $recipe_id, $recipe_id ) );

					return array(
						'message' => 'User selector deleted successfully',
					);

				} catch ( \Exception $e ) {
					return new \WP_Error(
						'delete_error',
						'Failed to delete user selector: ' . $e->getMessage(),
						array( 'status' => 500 )
					);
				}
			}
		);
	}

	/**
	 * Check if a recipe has a user selector configured.
	 *
	 * @param int $recipe_id Recipe ID.
	 * @return bool True if user selector exists.
	 */
	public function has_user_selector( int $recipe_id ): bool {
		return $this->store->exists_for_recipe( $recipe_id );
	}

	/**
	 * Validate that the user selector does not target an administrator.
	 *
	 * Static (non-token) values that resolve to an admin user are rejected.
	 * Token values like {{user_email}} are allowed since they resolve at runtime.
	 *
	 * @since 7.0.0
	 *
	 * @param array $user_selector User selector configuration.
	 *
	 * @return true|\WP_Error True if valid, WP_Error if targeting an admin.
	 */
	public function validate_user_selector_not_admin( array $user_selector ) {

		$source = $user_selector['source'] ?? '';
		try {
			$is_existing = ( new User_Selector_Source( (string) $source ) )->is_existing_user();
		} catch ( \InvalidArgumentException $e ) {
			return true;
		}
		if ( ! $is_existing ) {
			return true;
		}

		$field = $user_selector['unique_field'] ?? '';
		$value = $user_selector['unique_field_value'] ?? '';

		// Token values resolve at runtime — skip.
		if ( empty( $value ) || false !== strpos( (string) $value, '{{' ) ) {
			return true;
		}

		$user = null;

		if ( 'email' === $field ) {
			$user = get_user_by( 'email', $value );
		} elseif ( 'id' === $field ) {
			$user = get_user_by( 'ID', $value );
		} elseif ( 'username' === $field ) {
			$user = get_user_by( 'login', $value );
		}

		if ( ! $user ) {
			return true; // User not found — will fail at runtime, but not our concern here.
		}

		if ( user_can( $user, 'manage_options' ) ) {
			return new \WP_Error(
				'user_selector_admin_rejected',
				sprintf(
					'User selector cannot target an administrator (user #%d: %s). '
					. 'Automator recipes should not run actions as admin users for security reasons. '
					. 'Use a non-admin user, or use a token like {{user_email}} so the user is determined at runtime.',
					$user->ID,
					$user->user_email
				)
			);
		}

		return true;
	}


	/**
	 * Build User_Selector_Config from raw data array.
	 *
	 * @param int   $recipe_id Recipe ID.
	 * @param array $data      Raw data array.
	 * @return User_Selector_Config Configuration object.
	 */
	private function build_config_from_data( int $recipe_id, array $data ): User_Selector_Config {
		$config = new User_Selector_Config();
		$config->recipe_id( $recipe_id );

		if ( isset( $data['source'] ) ) {
			$config->source( $data['source'] );
		}

		if ( isset( $data['unique_field'] ) ) {
			$config->unique_field( $data['unique_field'] );
		}

		if ( isset( $data['unique_field_value'] ) ) {
			$config->unique_field_value( $data['unique_field_value'] );
		}

		if ( isset( $data['fallback'] ) ) {
			$config->fallback( $data['fallback'] );
		}

		if ( isset( $data['prioritized_field'] ) ) {
			$config->prioritized_field( $data['prioritized_field'] );
		}

		if ( isset( $data['user_data'] ) && is_array( $data['user_data'] ) ) {
			$config->user_data( $data['user_data'] );
		}

		return $config;
	}
}
