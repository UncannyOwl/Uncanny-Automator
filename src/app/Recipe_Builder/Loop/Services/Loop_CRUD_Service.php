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

namespace Uncanny_Automator\App\Recipe_Builder\Loop\Services;

use Uncanny_Automator\App\Events\Recipe_Change_Events;
use Uncanny_Automator\App\Events\Dtos\Recipe_Changed;
use Uncanny_Automator\App\Integration_Catalog\Integration\Enums\Block_Modifier_Types;
use Uncanny_Automator\App\Infrastructure\Database\Database;
use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Recipe_Store;
use Uncanny_Automator\App\Recipe_Builder\Block\Services\Block_Crud_Service;
use Uncanny_Automator\App\Recipe_Builder\Loop\Value_Objects\Loop_Field_Translator;
use Uncanny_Automator\App\Infrastructure\Database\Stores\WP_Recipe_Store;
use Uncanny_Automator\App\Recipe_Builder\Loop\Enums\Loop_Status;
use Uncanny_Automator\App\Recipe_Builder\Loop\Iterable_Expression\Enums\Iteration_Type;
use Uncanny_Automator\App\Presentation\Recipe_Builder\Loop\Filters\Loop_Filter_Sentence_Composer;
use Uncanny_Automator\App\Events\Enums\Recipe_Change_Operation;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Wire_Serializer\Enums\Wire_Node_Type;
use Uncanny_Automator\App\Recipe_Builder\Block\Enums\Block_Type;
use Uncanny_Automator\App\Recipe_Builder\Loop\Services\Loop_Filter_Reconciliation_Service;
use Uncanny_Automator\App\Recipe_Builder\Loop\Services\Filter\Filter_CRUD_Service;
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
	 * Recipe store instance.
	 *
	 * @var Recipe_Store
	 */
	private Recipe_Store $recipe_store;

	/**
	 * Loop filter sentence composer.
	 *
	 * @var Loop_Filter_Sentence_Composer
	 */
	private Loop_Filter_Sentence_Composer $filter_sentence_composer;

	/**
	 * Loop-filter reconciliation service (clears modifiers invalidated by a loop-type change).
	 *
	 * @var Loop_Filter_Reconciliation_Service
	 */
	private Loop_Filter_Reconciliation_Service $reconciliation;

	/**
	 * Constructor.
	 *
	 * @param Recipe_Store|null                  $recipe_store             Optional recipe store instance.
	 * @param Loop_Filter_Sentence_Composer|null $filter_sentence_composer Optional filter sentence composer instance.
	 * @param Loop_Filter_Reconciliation_Service|null $reconciliation       Optional reconciliation service (lazy default).
	 */
	private function __construct(
		?Recipe_Store $recipe_store = null,
		?Loop_Filter_Sentence_Composer $filter_sentence_composer = null,
		?Loop_Filter_Reconciliation_Service $reconciliation = null
	) {
		global $wpdb;

		$this->recipe_store             = $recipe_store ?? new WP_Recipe_Store( $wpdb );
		$this->filter_sentence_composer = $filter_sentence_composer ?? new Loop_Filter_Sentence_Composer();
		$this->reconciliation           = $reconciliation ?? new Loop_Filter_Reconciliation_Service();
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
	 * @param Recipe_Store|null                  $recipe_store             Custom recipe store.
	 * @param Loop_Filter_Sentence_Composer|null $filter_sentence_composer Custom filter sentence composer.
	 * @param Loop_Filter_Reconciliation_Service|null $reconciliation       Custom reconciliation service.
	 * @return self
	 */
	public static function create_with_dependencies(
		?Recipe_Store $recipe_store = null,
		?Loop_Filter_Sentence_Composer $filter_sentence_composer = null,
		?Loop_Filter_Reconciliation_Service $reconciliation = null
	): self {
		return new self( $recipe_store, $filter_sentence_composer, $reconciliation );
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
	 * Add an empty loop block to a parent.
	 *
	 * Creates a bare uo-block(loop) row under the given parent (recipe or
	 * block) through the block service. All configuration is set later via
	 * update once the user configures the loop in the UI.
	 * @param int $parent_id Parent post ID (recipe or block).
	 * @return array|\WP_Error Success data with loop_id or error.
	 */
	public function add_empty_to_parent( int $parent_id ) {
		// Loops are unified blocks — resolve the owning recipe from the
		// parent (a recipe, or a block carrying its recipe_id stamp) and
		// create through the block service like every other block type.
		if ( WP_Recipe_Store::POST_TYPE === get_post_type( $parent_id ) ) {
			$recipe_id = $parent_id;
		} else {
			$parent_block = Database::get_block_store()->get( $parent_id );
			$recipe_id    = null !== $parent_block ? (int) ( $parent_block['recipe_id'] ?? 0 ) : 0;
		}

		if ( $recipe_id <= 0 ) {
			return new WP_Error(
				'invalid_parent',
				esc_html_x( 'Loops may only be added to a recipe or one of its blocks.', 'Loop CRUD error', 'uncanny-automator' )
			);
		}

		$created = Block_Crud_Service::instance()->add_block( $recipe_id, 'loop', $parent_id );
		if ( is_wp_error( $created ) ) {
			return $created;
		}

		return array(
			'success' => true,
			'loop_id' => (int) $created['id'],
		);
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
		return Recipe_Change_Events::operation(
			function () use ( $recipe_id, $iterable_expression, $status, $filters, $run_on ) {
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

				// Loops are unified blocks: create the uo-block(loop) row, then
				// configure it through the same dual-aware update path the REST and
				// MCP transports share. The create, the configuration and the filters
				// are one operation, so only the finished loop fires an event.
				$created = Block_Crud_Service::instance()->add_block( $recipe_id, Block_Type::LOOP, $recipe_id );
				if ( is_wp_error( $created ) ) {
					return $created;
				}

				$loop_id               = (int) $created['id'];
				$default_filter_result = Recipe_Change_Events::operation(
					fn() => $this->configure_new_loop( $loop_id, $iterable_expression, $status, $run_on, $filters, $type )
				);

				// A loop and its filters land together or not at all: a loop whose filter was refused is taken back.
				if ( is_wp_error( $default_filter_result ) ) {
					return $this->discard_new_loop( $loop_id, $recipe_id, $default_filter_result );
				}

				Recipe_Change_Events::changed( new Recipe_Changed( Recipe_Change_Operation::CREATED, Wire_Node_Type::BLOCK, $loop_id, $recipe_id ) );

				return array(
					'success'        => true,
					'message'        => esc_html_x( 'Loop successfully added to recipe.', 'Loop CRUD success message', 'uncanny-automator' ),
					'loop_id'        => $loop_id,
					'loop'           => Database::get_block_store()->get( $loop_id ),
					'default_filter' => $default_filter_result,
				);
			}
		);
	}

	/**
	 * Configure a loop block just created: its iteration, then its filters.
	 *
	 * Initial filters (MCP authoring) ride the dual-aware filter service; when none are supplied, the iteration
	 * type's default filter is seeded.
	 *
	 * @param int             $loop_id             Loop block post id.
	 * @param array           $iterable_expression Iteration settings.
	 * @param string          $status              Loop status.
	 * @param mixed           $run_on              Run-on setting, or null.
	 * @param array           $filters             Initial filters; empty seeds the default.
	 * @param string          $type                Iteration type.
	 *
	 * @return array|null|WP_Error The default filter's result (null when none was seeded), or the first error.
	 */
	private function configure_new_loop( int $loop_id, array $iterable_expression, string $status, $run_on, array $filters, string $type ) {
		$configured = $this->update_existing_loop( $loop_id, $iterable_expression, $status, $run_on );
		if ( is_wp_error( $configured ) ) {
			return $configured;
		}

		if ( empty( $filters ) ) {
			return $this->assign_default_filter( $loop_id, $type );
		}

		$filter_error = $this->add_initial_filters( $loop_id, $filters );

		return is_wp_error( $filter_error ) ? $filter_error : null;
	}

	/**
	 * Take back a loop block whose configuration or filters were refused, with the filters it already has.
	 *
	 * Nothing is announced when the loop is gone: the recipe is as it was. When the
	 * store cannot remove it, the loop landed, so it is announced and the refusal
	 * says it stays.
	 *
	 * @since 8.0.0
	 *
	 * @param int      $loop_id   Loop block post id.
	 * @param int      $recipe_id Recipe post id.
	 * @param WP_Error $refusal   Why the loop's configuration or filters were refused.
	 *
	 * @return WP_Error The refusal.
	 */
	private function discard_new_loop( int $loop_id, int $recipe_id, WP_Error $refusal ): WP_Error {
		try {
			Database::get_block_store()->delete( $loop_id );
		} catch ( \Exception $e ) {
			Recipe_Change_Events::changed( new Recipe_Changed( Recipe_Change_Operation::CREATED, Wire_Node_Type::BLOCK, $loop_id, $recipe_id ) );

			$refusal->add(
				'loop_discard_failed',
				sprintf(
					/* translators: 1: Loop ID, 2: Error message. */
					esc_html_x( 'Loop %1$d could not be removed after it was refused, so it stays in the recipe: %2$s', 'Loop CRUD error', 'uncanny-automator' ),
					$loop_id,
					$e->getMessage()
				)
			);
		}

		return $refusal;
	}

	/**
	 * Resolve a unified `uo-block(loop)` row, or null when the id is a
	 * not a loop block (or unknown).
	 *
	 * @param int $loop_id Candidate loop block post id.
	 * @return array|null Block row or null.
	 */
	private function resolve_unified_loop_row( int $loop_id ): ?array {
		$block = Database::get_block_store()->get( $loop_id );

		if ( null === $block || 'loop' !== (string) ( $block['block_type'] ?? '' ) ) {
			return null;
		}

		return $block;
	}

	/**
	 * Update a unified loop row: merge the iteration config into the stored
	 * `iterable_expression` (legacy parity) and keep the stored structured
	 * fields coherent with it — callers that send the FE's structured shape
	 * get it stored verbatim (the wire round-trips it); expression-only
	 * callers (MCP) get fields re-derived from the merged expression so the
	 * pair never drifts. Reconciles filters whose ancestor rules no longer
	 * hold under the new loop type.
	 *
	 * @param array       $unified             Unified loop block row.
	 * @param array       $iterable_expression Iteration config to merge (may be empty).
	 * @param string|null $status              Requested post status, when provided.
	 * @param array       $structured_fields   FE wire fields, when the caller has them.
	 * @param mixed       $run_on              Run-on condition, when provided.
	 *
	 * @return array|WP_Error Legacy-shaped success payload or error.
	 */
	private function update_unified_loop( array $unified, array $iterable_expression, ?string $status, array $structured_fields, $run_on = null ) {
		$loop_id = (int) $unified['id'];

		$merged = ! empty( $iterable_expression )
			? array_merge( (array) ( $unified['iterable_expression'] ?? array() ), $iterable_expression )
			: (array) ( $unified['iterable_expression'] ?? array() );

		$data = array( 'iterable_expression' => $merged );

		if ( ! empty( $structured_fields ) ) {
			$data['fields'] = array_replace( (array) ( $unified['fields'] ?? array() ), $structured_fields );
		} elseif ( ! empty( $iterable_expression ) ) {
			$data['fields'] = Loop_Field_Translator::to_fields( $merged );
		}

		if ( null !== $status ) {
			$data['status'] = $status;
		}

		if ( null !== $run_on ) {
			$data['run_on'] = $run_on;
		}

		try {
			Database::get_block_store()->update( $loop_id, $data );
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

		if ( ! empty( $iterable_expression ) ) {
			$this->reconciliation->reconcile( $loop_id, $merged );
		}

		$recipe_id = (int) ( $unified['recipe_id'] ?? 0 );
		if ( $recipe_id > 0 ) {
			Recipe_Change_Events::changed( new Recipe_Changed( Recipe_Change_Operation::UPDATED, Wire_Node_Type::BLOCK, $loop_id, $recipe_id ) );
		}

		return array(
			'success' => true,
			'message' => esc_html_x( 'Loop successfully updated.', 'Loop CRUD success message', 'uncanny-automator' ),
			'loop'    => Database::get_block_store()->get( $loop_id ),
		);
	}

	/**
	 * Add caller-supplied initial filters to a freshly created loop through
	 * the dual-aware filter service (registry validation + iteration-type
	 * checks included).
	 *
	 * @param int   $loop_id Loop block post id.
	 * @param array $filters Filter rows: each carries code + integration (+ fields/backup).
	 *
	 * @return WP_Error|null Error from the first failing filter, null on success.
	 */
	private function add_initial_filters( int $loop_id, array $filters ) {
		$filter_service = Filter_CRUD_Service::instance();

		foreach ( $filters as $filter ) {
			if ( ! is_array( $filter ) ) {
				continue;
			}

			$result = $filter_service->add_to_loop(
				$loop_id,
				(string) ( $filter['code'] ?? $filter['meta'] ?? '' ),
				(string) ( $filter['integration_code'] ?? $filter['integration'] ?? '' ),
				is_array( $filter['fields'] ?? null ) ? $filter['fields'] : array(),
				is_array( $filter['backup'] ?? null ) ? $filter['backup'] : array()
			);

			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}

		return null;
	}

	/**
	 * Get a specific loop by ID.
	 * @param int|string $loop_id Loop ID.
	 * @return array|\WP_Error Loop data or error.
	 */
	public function get_loop( $loop_id ) {
		$unified = $this->resolve_unified_loop_row( $this->coerce_loop_id( $loop_id ) );
		if ( null === $unified ) {
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
			'loop'    => $unified,
		);
	}

	/**
	 * Update an existing loop. A loop saved with a type and no filter gets
	 * its type's default filter: a loop takes its items from its filters only.
	 *
	 * @since 7.0.0
	 * @param int|string  $loop_id             Loop ID.
	 * @param array       $iterable_expression Optional new iterable expression.
	 * @param string|null $status              Optional new status.
	 * @param mixed       $run_on              Optional new run_on condition.
	 * @param array       $structured_fields   Optional FE wire fields, stored verbatim on unified rows.
	 * @return array|\WP_Error Updated loop data or error.
	 */
	public function update_loop( $loop_id, array $iterable_expression = array(), ?string $status = null, $run_on = null, array $structured_fields = array() ) {
		return Recipe_Change_Events::operation(
			function () use ( $loop_id, $iterable_expression, $status, $run_on, $structured_fields ) {
				$updated = $this->update_existing_loop( $this->coerce_loop_id( $loop_id ), $iterable_expression, $status, $run_on, $structured_fields );
				if ( is_wp_error( $updated ) ) {
					return $updated;
				}

				$loop_type = (string) ( $updated['loop']['iterable_expression']['type'] ?? '' );
				$loop_id   = (int) ( $updated['loop']['id'] ?? 0 );
				if ( '' !== $loop_type && empty( Database::get_block_modifier_store()->get_by_block( $loop_id, Block_Modifier_Types::LOOP_FILTER ) ) ) {
					$this->assign_default_filter( $loop_id, $loop_type );
				}

				return $updated;
			}
		);
	}

	/**
	 * Update a loop row that already exists, without seeding a filter: the
	 * create path adds the caller's filters or the default itself.
	 *
	 * @since 8.0.0
	 * @param int         $loop_id             Loop block post id.
	 * @param array       $iterable_expression Iteration config to merge (may be empty).
	 * @param string|null $status              Requested post status, when provided.
	 * @param mixed       $run_on              Run-on condition, when provided.
	 * @param array       $structured_fields   FE wire fields, when the caller has them.
	 * @return array|WP_Error Updated loop data or error.
	 */
	private function update_existing_loop( int $loop_id, array $iterable_expression, ?string $status, $run_on, array $structured_fields = array() ) {
		$unified = $this->resolve_unified_loop_row( $loop_id );
		if ( null === $unified ) {
			return new WP_Error(
				'loop_not_found',
				sprintf(
					/* translators: %d Loop ID. */
					esc_html_x( 'Loop not found with ID: %d', 'Loop CRUD error', 'uncanny-automator' ),
					$loop_id
				)
			);
		}

		return $this->update_unified_loop( $unified, $iterable_expression, $status, $structured_fields, $run_on );
	}

	/**
	 * Delete a loop from a recipe.
	 *
	 * @since 7.0.0
	 * @param int|string $loop_id   Loop ID.
	 * @param bool       $confirmed Confirmation flag for safety.
	 * @param bool       $delete_content Flag to delete loop content.
	 * @return array|\WP_Error Success confirmation or error.
	 */
	public function delete_loop( $loop_id, bool $confirmed = false, bool $delete_content = false ) {
		if ( ! $confirmed ) {
			return new WP_Error(
				'confirmation_required',
				esc_html_x( 'You must confirm deletion by setting $confirmed parameter to true', 'Loop CRUD error', 'uncanny-automator' )
			);
		}

		$unified = $this->resolve_unified_loop_row( $this->coerce_loop_id( $loop_id ) );
		if ( null === $unified ) {
			return new WP_Error(
				'loop_not_found',
				sprintf(
					/* translators: %d Loop ID. */
					esc_html_x( 'Loop not found with ID: %d', 'Loop CRUD error', 'uncanny-automator' ),
					$loop_id
				)
			);
		}

		$unified_id = (int) $unified['id'];

		$deleted = Block_Crud_Service::instance()->delete_block( $unified_id, $delete_content );
		if ( is_wp_error( $deleted ) ) {
			return $deleted;
		}

		return array(
			'success'         => true,
			'deleted_loop_id' => $unified_id,
		);
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
	 * Assign a default filter to a loop based on iteration type.
	 *
	 * Only adds default filter if the filter code is registered (requires Pro).
	 * Returns null when the filter is not registered; a registered default
	 * filter that is refused returns the refusal, so the loop is taken back.
	 *
	 * @since 7.0.0
	 * @param int    $loop_id Loop ID.
	 * @param string $type    Iteration type (users, posts, token).
	 * @return array|null|WP_Error Filter result, null if not registered, or the refusal.
	 */
	protected function assign_default_filter( int $loop_id, string $type ) {
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

		// The default filter is Pro's: without it registered, the loop has no default filter.
		if ( is_wp_error( $result ) && 'filter_not_found' === $result->get_error_code() ) {
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
						'sentence_html'    => $this->compose_filter_sentence_html(
							'User {{has:CRITERIA}} {{a specific role:WP_USER_HAS_ROLE}}',
							array(
								'CRITERIA'         => array(
									'type'     => 'select',
									'value'    => 'does-not-have',
									'readable' => 'does not have',
									'backup'   => array(
										'label' => 'Criteria',
										'supports_custom_value' => false,
										'supports_multiple_values' => false,
									),
								),
								'WP_USER_HAS_ROLE' => array(
									'type'     => 'select',
									'value'    => 'administrator',
									'readable' => 'Administrator',
									'backup'   => array(
										'label' => 'Role',
										'supports_custom_value' => false,
										'supports_multiple_values' => false,
									),
								),
							)
						),
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
						'sentence_html'    => $this->compose_filter_sentence_html(
							'Post type is {{a specific post type:WP_POST_EQUALS_POST_TYPE}}',
							array(
								'WP_POST_EQUALS_POST_TYPE' => array(
									'type'     => 'select',
									'value'    => 'post',
									'readable' => 'Posts',
									'backup'   => array(
										'label' => 'Post type',
										'supports_custom_value' => true,
										'supports_multiple_values' => false,
									),
								),
							)
						),
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
						'sentence_html'    => $this->compose_filter_sentence_html(
							'The item is not empty',
							array(
								'ITEM_NOT_EMPTY' => array(
									'type'     => 'select',
									'value'    => 'is_not_empty',
									'readable' => 'The item is not empty',
									'backup'   => array(
										'label' => 'Condition',
										'show_label_in_sentence' => true,
										'supports_custom_value' => false,
										'supports_multiple_values' => false,
									),
								),
							)
						),
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

		return htmlspecialchars( html_entity_decode( $html, ENT_QUOTES, 'UTF-8' ), ENT_QUOTES, 'UTF-8' );
	}
}
