<?php
/**
 * Loop CRUD Service
 *
 * Core business logic service for loop instance operations.
 * Manages CRUD operations for loop instances within recipes.
 * Single source of truth for loop management.
 *
 * @since 7.0.0
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\Api\Services\Loop\Services;

use Uncanny_Automator\Api\Database\Interfaces\Loop\Loop_Store;
use Uncanny_Automator\Api\Database\Interfaces\Loop\Filter_Store;
use Uncanny_Automator\Api\Database\Stores\Loop\WP_Loop_Store;
use Uncanny_Automator\Api\Database\Stores\Loop\WP_Filter_Store;
use Uncanny_Automator\Api\Database\Stores\WP_Recipe_Store;
use Uncanny_Automator\Api\Components\Loop\Loop;
use Uncanny_Automator\Api\Components\Loop\Loop_Config;
use Uncanny_Automator\Api\Components\Loop\Enums\Loop_Status;
use Uncanny_Automator\Api\Components\Loop\Iterable_Expression\Enums\Iteration_Type;
use Uncanny_Automator\Api\Presentation\Loop\Filters\Loop_Filter_Sentence_Composer;
use Uncanny_Automator\Api\Services\Loop\Filter\Services\Filter_CRUD_Service;
use WP_Error;

/**
 * Loop CRUD Service Class
 *
 * Handles CRUD operations for loop instances within recipes.
 * Default loop filter sentence HTML composition is delegated to presentation layer
 * (`Loop_Filter_Sentence_Composer`).
 */
class Loop_CRUD_Service {

	/**
	 * Service instance (singleton pattern).
	 *
	 * @var Loop_CRUD_Service|null
	 */
	private static ?Loop_CRUD_Service $instance = null;

	/**
	 * Loop store instance.
	 *
	 * @var Loop_Store
	 */
	private Loop_Store $loop_store;

	/**
	 * Filter store instance.
	 *
	 * @var Filter_Store
	 */
	private Filter_Store $filter_store;

	/**
	 * Recipe store instance.
	 *
	 * @var WP_Recipe_Store
	 */
	private WP_Recipe_Store $recipe_store;

	/**
	 * Loop filter sentence composer.
	 *
	 * @var Loop_Filter_Sentence_Composer
	 */
	private Loop_Filter_Sentence_Composer $filter_sentence_composer;

	/**
	 * Constructor.
	 *
	 * @param Loop_Store|null                 $loop_store              Optional loop store instance.
	 * @param Filter_Store|null               $filter_store            Optional filter store instance.
	 * @param WP_Recipe_Store|null            $recipe_store            Optional recipe store instance.
	 * @param Loop_Filter_Sentence_Composer|null $filter_sentence_composer Optional filter sentence composer instance.
	 */
	private function __construct(
		?Loop_Store $loop_store = null,
		?Filter_Store $filter_store = null,
		?WP_Recipe_Store $recipe_store = null,
		?Loop_Filter_Sentence_Composer $filter_sentence_composer = null
	) {
		global $wpdb;

		$this->filter_store             = $filter_store ?? new WP_Filter_Store( $wpdb );
		$this->loop_store               = $loop_store ?? new WP_Loop_Store( $wpdb, $this->filter_store );
		$this->recipe_store             = $recipe_store ?? new WP_Recipe_Store( $wpdb );
		$this->filter_sentence_composer = $filter_sentence_composer ?? new Loop_Filter_Sentence_Composer();
	}

	/**
	 * Get service instance (singleton).
	 *
	 * @since 7.0.0
	 * @return Loop_CRUD_Service
	 */
	public static function instance(): Loop_CRUD_Service {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Create a service instance with explicit dependencies.
	 *
	 * Primarily useful for testing where stores should be replaced with mocks.
	 *
	 * @since 7.0.0
	 * @param Loop_Store|null                 $loop_store               Custom loop store.
	 * @param Filter_Store|null               $filter_store             Custom filter store.
	 * @param WP_Recipe_Store|null            $recipe_store             Custom recipe store.
	 * @param Loop_Filter_Sentence_Composer|null $filter_sentence_composer Custom filter sentence composer.
	 * @return self
	 */
	public static function create_with_dependencies(
		?Loop_Store $loop_store = null,
		?Filter_Store $filter_store = null,
		?WP_Recipe_Store $recipe_store = null,
		?Loop_Filter_Sentence_Composer $filter_sentence_composer = null
	): self {
		return new self( $loop_store, $filter_store, $recipe_store, $filter_sentence_composer );
	}

	/**
	 * Coerce loop ID to integer type.
	 *
	 * @since 7.0.0
	 * @param int|string $loop_id Loop ID to coerce.
	 * @return int Coerced integer loop ID.
	 */
	private function coerce_loop_id( $loop_id ): int {
		return (int) $loop_id;
	}

	/**
	 * Validate recipe exists.
	 *
	 * @param int $recipe_id Recipe ID.
	 * @return mixed Recipe object or null if not found.
	 */
	public function validate_recipe_exists( int $recipe_id ) {
		return $this->recipe_store->get( $recipe_id );
	}

	/**
	 * Validate iteration type.
	 *
	 * @param string $type Iteration type.
	 * @return bool True if valid.
	 */
	public function is_valid_iteration_type( string $type ): bool {
		return in_array( $type, array( Iteration_Type::USERS, Iteration_Type::POSTS, Iteration_Type::TOKEN ), true );
	}

	/**
	 * Add loop to recipe.
	 *
	 * @since 7.0.0
	 * @param int    $recipe_id           Recipe ID.
	 * @param array  $iterable_expression Iterable expression config.
	 * @param string $status              Optional status (draft/publish).
	 * @param array  $filters             Optional initial filters.
	 * @param mixed  $run_on              Optional run_on condition.
	 * @return array|\WP_Error Success data or error.
	 */
	public function add_to_recipe(
		int $recipe_id,
		array $iterable_expression,
		string $status = Loop_Status::DRAFT,
		array $filters = array(),
		$run_on = null
	) {
		// Validate recipe exists.
		if ( ! $this->validate_recipe_exists( $recipe_id ) ) {
			return new WP_Error(
				'recipe_not_found',
				sprintf(
					/* translators: %d Recipe ID. */
					esc_html_x( 'Add loop failed: Recipe not found with ID: %d', 'Loop CRUD error', 'uncanny-automator' ),
					$recipe_id
				)
			);
		}

		// Validate iteration type if provided.
		$type = $iterable_expression['type'] ?? Iteration_Type::USERS;
		if ( ! $this->is_valid_iteration_type( $type ) ) {
			return new WP_Error(
				'invalid_iteration_type',
				sprintf(
					/* translators: %s Iteration type. */
					esc_html_x( "Invalid iteration type: '%s'. Must be one of: users, posts, token", 'Loop CRUD error', 'uncanny-automator' ),
					$type
				)
			);
		}

		// Build loop config.
		$config = ( new Loop_Config() )
			->recipe_id( $recipe_id )
			->status( $status )
			->iterable_expression( $iterable_expression )
			->filters( $filters );

		if ( null !== $run_on ) {
			$config->run_on( $run_on );
		}

		try {
			$loop = new Loop( $config );

			// Save loop (includes filters).
			$saved_loop = $this->loop_store->save( $loop );
			$loop_id    = $saved_loop->get_loop_id()->get_value();

			// Add default filter if no filters were provided.
			$default_filter_result = null;
			if ( empty( $filters ) ) {
				$default_filter_result = $this->assign_default_filter( $loop_id, $type );
			}

			// Re-fetch loop to include the default filter.
			$final_loop = $this->loop_store->get( $loop_id );

			return array(
				'success'        => true,
				'message'        => esc_html_x( 'Loop successfully added to recipe.', 'Loop CRUD success message', 'uncanny-automator' ),
				'loop_id'        => $loop_id,
				'loop'           => $final_loop ? $final_loop->to_array() : $saved_loop->to_array(),
				'default_filter' => $default_filter_result,
			);

		} catch ( \Exception $e ) {
			return new WP_Error(
				'loop_save_failed',
				sprintf(
					/* translators: %s Error message. */
					esc_html_x( 'Failed to save loop: %s', 'Loop CRUD error', 'uncanny-automator' ),
					$e->getMessage()
				)
			);
		}
	}

	/**
	 * Get a specific loop by ID.
	 *
	 * @since 7.0.0
	 * @param int|string $loop_id Loop ID.
	 * @return array|\WP_Error Loop data or error.
	 */
	public function get_loop( $loop_id ) {
		$loop = $this->loop_store->get( $this->coerce_loop_id( $loop_id ) );

		if ( ! $loop ) {
			return new WP_Error(
				'loop_not_found',
				sprintf(
					/* translators: %d Loop ID. */
					esc_html_x( 'Loop not found with ID: %d', 'Loop CRUD error', 'uncanny-automator' ),
					$loop_id
				)
			);
		}

		return array(
			'success' => true,
			'loop'    => $loop->to_array(),
		);
	}

	/**
	 * Get all loops for a recipe.
	 *
	 * @since 7.0.0
	 * @param int $recipe_id Recipe ID.
	 * @return array|\WP_Error Recipe loops data or error.
	 */
	public function get_recipe_loops( int $recipe_id ) {
		// Validate recipe exists.
		$recipe = $this->recipe_store->get( $recipe_id );

		if ( ! $recipe ) {
			return new WP_Error(
				'recipe_not_found',
				sprintf(
					/* translators: %d Recipe ID. */
					esc_html_x( 'Get recipe loops failed: Recipe not found with ID: %d', 'Loop CRUD error', 'uncanny-automator' ),
					$recipe_id
				)
			);
		}

		try {
			$loops = $this->loop_store->get_recipe_loops( $recipe_id );

			$formatted_loops = array();
			foreach ( $loops as $loop ) {
				$formatted_loops[] = $loop->to_array();
			}

			return array(
				'success'    => true,
				'message'    => esc_html_x( 'Loops retrieved successfully.', 'Loop CRUD success message', 'uncanny-automator' ),
				'recipe_id'  => $recipe_id,
				'loop_count' => count( $loops ),
				'loops'      => $formatted_loops,
			);

		} catch ( \Exception $e ) {
			return new WP_Error(
				'recipe_loops_error',
				sprintf(
					/* translators: %s Error message. */
					esc_html_x( 'Failed to retrieve recipe loops: %s', 'Loop CRUD error', 'uncanny-automator' ),
					$e->getMessage()
				)
			);
		}
	}

	/**
	 * Update an existing loop.
	 *
	 * @since 7.0.0
	 * @param int|string  $loop_id             Loop ID.
	 * @param array       $iterable_expression Optional new iterable expression.
	 * @param string|null $status              Optional new status.
	 * @param mixed       $run_on              Optional new run_on condition.
	 * @return array|\WP_Error Updated loop data or error.
	 */
	public function update_loop( $loop_id, array $iterable_expression = array(), ?string $status = null, $run_on = null ) {
		$existing_loop = $this->loop_store->get( $this->coerce_loop_id( $loop_id ) );

		if ( ! $existing_loop ) {
			return new WP_Error(
				'loop_not_found',
				sprintf(
					/* translators: %d Loop ID. */
					esc_html_x( 'Loop not found with ID: %d', 'Loop CRUD error', 'uncanny-automator' ),
					$loop_id
				)
			);
		}

		try {
			$existing_data = $existing_loop->to_array();

			// Merge iterable expression if provided.
			$merged_expression = ! empty( $iterable_expression )
				? array_merge( $existing_data['iterable_expression'], $iterable_expression )
				: $existing_data['iterable_expression'];

			// Build updated config.
			$config = ( new Loop_Config() )
				->id( $existing_data['id'] )
				->recipe_id( $existing_data['recipe_id'] )
				->status( $status ?? $existing_data['status'] )
				->ui_order( $existing_data['_ui_order'] )
				->iterable_expression( $merged_expression )
				->run_on( $run_on ?? $existing_data['run_on'] )
				->filters( $existing_data['filters'] )
				->items( $existing_data['items'] );

			$updated_loop = new Loop( $config );
			$saved_loop   = $this->loop_store->save( $updated_loop );

			return array(
				'success' => true,
				'message' => esc_html_x( 'Loop successfully updated.', 'Loop CRUD success message', 'uncanny-automator' ),
				'loop'    => $saved_loop->to_array(),
			);

		} catch ( \Exception $e ) {
			return new WP_Error(
				'loop_update_failed',
				sprintf(
					/* translators: %s Error message. */
					esc_html_x( 'Failed to update loop: %s', 'Loop CRUD error', 'uncanny-automator' ),
					$e->getMessage()
				)
			);
		}
	}

	/**
	 * Delete a loop from a recipe.
	 *
	 * @since 7.0.0
	 * @param int|string $loop_id   Loop ID.
	 * @param bool       $confirmed Confirmation flag for safety.
	 * @return array|\WP_Error Success confirmation or error.
	 */
	public function delete_loop( $loop_id, bool $confirmed = false ) {
		if ( ! $confirmed ) {
			return new WP_Error(
				'confirmation_required',
				esc_html_x( 'You must confirm deletion by setting $confirmed parameter to true', 'Loop CRUD error', 'uncanny-automator' )
			);
		}

		$loop = $this->loop_store->get( $this->coerce_loop_id( $loop_id ) );

		if ( ! $loop ) {
			return new WP_Error(
				'loop_not_found',
				sprintf(
					/* translators: %d Loop ID. */
					esc_html_x( 'Loop not found with ID: %d', 'Loop CRUD error', 'uncanny-automator' ),
					$loop_id
				)
			);
		}

		try {
			$loop_data = $loop->to_array();
			$recipe_id = (int) $loop_data['recipe_id'];
			$loop_id   = (int) $loop_data['id'];

			// Reparent any actions inside this loop to the recipe to prevent orphans.
			$this->reparent_loop_actions_to_recipe( $loop_id, $recipe_id );

			$this->loop_store->delete( $loop );

			return array(
				'success'         => true,
				'message'         => esc_html_x( 'Loop and its filters successfully deleted from recipe. Actions were moved to recipe.', 'Loop CRUD success message', 'uncanny-automator' ),
				'deleted_loop_id' => $loop_data['id'],
				'recipe_id'       => $loop_data['recipe_id'],
			);

		} catch ( \Exception $e ) {
			return new WP_Error(
				'delete_loop_failed',
				sprintf(
					/* translators: %s Error message. */
					esc_html_x( 'Failed to delete loop: %s', 'Loop CRUD error', 'uncanny-automator' ),
					$e->getMessage()
				)
			);
		}
	}

	/**
	 * Reparent actions from a loop to its parent recipe.
	 *
	 * When a loop is deleted, any actions inside it need to be moved
	 * back to the recipe to prevent orphaned actions.
	 *
	 * @since 7.0.0
	 * @param int $loop_id   The loop ID being deleted.
	 * @param int $recipe_id The recipe ID to reparent actions to.
	 * @return int Number of actions reparented.
	 */
	private function reparent_loop_actions_to_recipe( int $loop_id, int $recipe_id ): int {
		global $wpdb;

		// Update all actions with post_parent = loop_id to have post_parent = recipe_id.
		$updated = $wpdb->update(
			$wpdb->posts,
			array( 'post_parent' => $recipe_id ),
			array(
				'post_parent' => $loop_id,
				'post_type'   => 'uo-action',
			),
			array( '%d' ),
			array( '%d', '%s' )
		);

		// Clean post cache for affected actions.
		if ( $updated > 0 ) {
			clean_post_cache( $recipe_id );
		}

		return (int) $updated;
	}

	/**
	 * Publish a loop.
	 *
	 * @since 7.0.0
	 * @param int|string $loop_id Loop ID.
	 * @return array|\WP_Error Success data or error.
	 */
	public function publish_loop( $loop_id ) {
		return $this->update_loop( $loop_id, array(), Loop_Status::PUBLISH );
	}

	/**
	 * Unpublish a loop (set to draft).
	 *
	 * @since 7.0.0
	 * @param int|string $loop_id Loop ID.
	 * @return array|\WP_Error Success data or error.
	 */
	public function unpublish_loop( $loop_id ) {
		return $this->update_loop( $loop_id, array(), Loop_Status::DRAFT );
	}

	/**
	 * Get the loop store.
	 *
	 * @return Loop_Store Loop store instance.
	 */
	public function get_loop_store(): Loop_Store {
		return $this->loop_store;
	}

	/**
	 * Get the filter store.
	 *
	 * @return Filter_Store Filter store instance.
	 */
	public function get_filter_store(): Filter_Store {
		return $this->filter_store;
	}

	/**
	 * Assign a default filter to a loop based on iteration type.
	 *
	 * Only adds default filter if the filter code is registered (requires Pro).
	 * Gracefully returns null if filter is not available.
	 *
	 * @since 7.0.0
	 * @param int    $loop_id Loop ID.
	 * @param string $type    Iteration type (users, posts, token).
	 * @return array|null Filter result or null if not available.
	 */
	protected function assign_default_filter( int $loop_id, string $type ): ?array {
		$default = $this->get_default_filter_config( $type );

		if ( null === $default ) {
			return null;
		}

		$filter_service = Filter_CRUD_Service::instance();

		$result = $filter_service->add_to_loop(
			$loop_id,
			$default['code'],
			$default['integration_code'],
			$default['fields'],
			$default['backup']
		);

		// If filter code is not registered (e.g., Pro not active), return null gracefully.
		if ( is_wp_error( $result ) ) {
			return null;
		}

		return $result;
	}

	/**
	 * Get default filter configuration for an iteration type.
	 *
	 * @since 7.0.0
	 * @param string $type Iteration type.
	 * @return array|null Filter config or null if no default.
	 */
	protected function get_default_filter_config( string $type ): ?array {
		switch ( $type ) {
			case Iteration_Type::USERS:
				return array(
					'code'             => 'WP_USER_HAS_ROLE',
					'integration_code' => 'WP',
					'fields'           => array(
						'CRITERIA'         => array(
							'type'     => 'select',
							'value'    => 'does-not-have',
							'readable' => 'does not have',
							'backup'   => array(
								'label'                    => 'Criteria',
								'supports_custom_value'    => false,
								'supports_multiple_values' => false,
							),
						),
						'WP_USER_HAS_ROLE' => array(
							'type'     => 'select',
							'value'    => 'administrator',
							'readable' => 'Administrator',
							'backup'   => array(
								'label'                    => 'Role',
								'supports_custom_value'    => false,
								'supports_multiple_values' => false,
							),
						),
					),
					'backup'           => array(
						'integration_name' => 'WordPress',
						'sentence'         => 'User {{has:CRITERIA}} {{a specific role:WP_USER_HAS_ROLE}}',
						'sentence_html'    => $this->compose_filter_sentence_html( 'User {{has:CRITERIA}} {{a specific role:WP_USER_HAS_ROLE}}', array(
							'CRITERIA'         => array(
								'type'     => 'select',
								'value'    => 'does-not-have',
								'readable' => 'does not have',
								'backup'   => array(
									'label'                    => 'Criteria',
									'supports_custom_value'    => false,
									'supports_multiple_values' => false,
								),
							),
							'WP_USER_HAS_ROLE' => array(
								'type'     => 'select',
								'value'    => 'administrator',
								'readable' => 'Administrator',
								'backup'   => array(
									'label'                    => 'Role',
									'supports_custom_value'    => false,
									'supports_multiple_values' => false,
								),
							),
						) ),
					),
				);

			case Iteration_Type::POSTS:
				return array(
					'code'             => 'WP_POST_EQUALS_POST_TYPE',
					'integration_code' => 'WP',
					'fields'           => array(
						'WP_POST_EQUALS_POST_TYPE' => array(
							'type'     => 'select',
							'value'    => 'post',
							'readable' => 'Posts',
							'backup'   => array(
								'label'                    => 'Post type',
								'supports_custom_value'    => true,
								'supports_multiple_values' => false,
							),
						),
					),
					'backup'           => array(
						'integration_name' => 'WordPress',
						'sentence'         => 'Post type is {{a specific post type:WP_POST_EQUALS_POST_TYPE}}',
						'sentence_html'    => $this->compose_filter_sentence_html( 'Post type is {{a specific post type:WP_POST_EQUALS_POST_TYPE}}', array(
							'WP_POST_EQUALS_POST_TYPE' => array(
								'type'     => 'select',
								'value'    => 'post',
								'readable' => 'Posts',
								'backup'   => array(
									'label'                    => 'Post type',
									'supports_custom_value'    => true,
									'supports_multiple_values' => false,
								),
							),
						) ),
					),
				);

			case Iteration_Type::TOKEN:
				return array(
					'code'             => 'ITEM_NOT_EMPTY',
					'integration_code' => 'GEN',
					'fields'           => array(
						'ITEM_NOT_EMPTY' => array(
							'type'     => 'select',
							'value'    => 'is_not_empty',
							'readable' => 'The item is not empty',
							'backup'   => array(
								'label'                    => 'Condition',
								'show_label_in_sentence'   => true,
								'supports_custom_value'    => false,
								'supports_multiple_values' => false,
							),
						),
					),
					'backup'           => array(
						'integration_name' => 'GEN',
						'sentence'         => 'The item is not empty',
						'sentence_html'    => $this->compose_filter_sentence_html( 'The item is not empty', array(
							'ITEM_NOT_EMPTY' => array(
								'type'     => 'select',
								'value'    => 'is_not_empty',
								'readable' => 'The item is not empty',
								'backup'   => array(
									'label'                    => 'Condition',
									'show_label_in_sentence'   => true,
									'supports_custom_value'    => false,
									'supports_multiple_values' => false,
								),
							),
						) ),
					),
				);

			default:
				return null;
		}
	}

	/**
	 * Compose encoded loop filter sentence HTML.
	 *
	 * @param string $sentence Sentence template.
	 * @param array  $fields   Nested field map.
	 *
	 * @return string
	 */
	private function compose_filter_sentence_html( string $sentence, array $fields ): string {
		$html = $this->filter_sentence_composer->compose( $sentence, $fields );

		return htmlspecialchars( $html, ENT_QUOTES, 'UTF-8' );
	}
}
