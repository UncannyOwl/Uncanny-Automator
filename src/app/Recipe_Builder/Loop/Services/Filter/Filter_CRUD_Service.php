<?php
declare(strict_types=1);
namespace Uncanny_Automator\App\Recipe_Builder\Loop\Services\Filter;

use Uncanny_Automator\App\Events\Recipe_Change_Events;
use Uncanny_Automator\App\Events\Dtos\Recipe_Changed;
use Uncanny_Automator\App\Infrastructure\Database\Database;
use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Block_Modifier_Store;
use Uncanny_Automator\App\Recipe_Builder\Block\Enums\Block_Type;
use Uncanny_Automator\App\Events\Enums\Recipe_Change_Operation;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Wire_Serializer\Enums\Wire_Node_Type;

use WP_Error;

/**
 * Filter CRUD Service.
 *
 * Handles CRUD operations for loop filters, persisted as
 * `uo-block-modifier` rows (discriminator `loop_filter`) under unified
 * `uo-block(loop)` parents.
 *
 * @since 7.0.0
 */
class Filter_CRUD_Service {

	private static ?Filter_CRUD_Service $instance = null;
	private Filter_Registry_Service $registry_service;

	/**
	 * Unified modifier persistence boundary — `uo-block(loop)` parents carry
	 * their filters as `uo-block-modifier` rows.
	 *
	 * @var Block_Modifier_Store
	 */
	private Block_Modifier_Store $modifier_store;

	/**
	 * Constructor.
	 *
	 * @param Filter_Registry_Service|null $registry_service Optional registry service instance.
	 * @param Block_Modifier_Store|null    $modifier_store   Optional unified modifier store (tests).
	 */
	private function __construct(
		?Filter_Registry_Service $registry_service = null,
		?Block_Modifier_Store $modifier_store = null
	) {
		$this->registry_service = $registry_service ?? Filter_Registry_Service::instance();
		$this->modifier_store   = $modifier_store ?? Database::get_block_modifier_store();
	}
	/**
	 * Instance.
	 *
	 * @return self
	 */
	public static function instance(): self {
		return self::$instance ??= new self();
	}
	/**
	 * Create with dependencies.
	 *
	 * @param Filter_Registry_Service $registry_service The registry service.
	 * @param Block_Modifier_Store    $modifier_store   The unified modifier store.
	 * @return self
	 */
	public static function create_with_dependencies(
		?Filter_Registry_Service $registry_service = null,
		?Block_Modifier_Store $modifier_store = null
	): self {
		return new self( $registry_service, $modifier_store );
	}
	/**
	 * Validate loop exists.
	 *
	 * @param int $loop_id The ID.
	 * @return mixed
	 */
	public function validate_loop_exists( int $loop_id ) {
		return $this->resolve_unified_loop( $loop_id );
	}

	/**
	 * Resolve a unified `uo-block(loop)` row for the given id, or null when
	 * the id isn't a unified loop block.
	 *
	 * @param int $loop_id Candidate loop block post id.
	 * @return array|null Block row or null.
	 */
	private function resolve_unified_loop( int $loop_id ): ?array {
		$block = Database::get_block_store()->get( $loop_id );

		if ( null === $block || 'loop' !== (string) ( $block['block_type'] ?? '' ) ) {
			return null;
		}

		return $block;
	}

	/**
	 * Persist a loop filter as a `uo-block-modifier` row under a unified
	 * loop block. Registry validation and the iteration-type check already
	 * ran in {@see self::add_to_loop}.
	 *
	 * @param array  $loop             Unified loop block row.
	 * @param string $filter_code      Loop-filter code.
	 * @param string $integration_code Integration code.
	 * @param array  $fields           Initial fields.
	 * @param array  $backup           Initial backup payload.
	 *
	 * @return array|WP_Error
	 */
	private function add_to_unified_loop( array $loop, string $filter_code, string $integration_code, array $fields, array $backup ) {
		try {
			$loop_id     = (int) $loop['id'];
			$menu_order  = count( $this->modifier_store->get_by_block( $loop_id, 'loop_filter' ) );
			$modifier_id = $this->modifier_store->create(
				(int) ( $loop['recipe_id'] ?? 0 ),
				$loop_id,
				'loop_filter',
				$integration_code,
				$filter_code,
				$fields,
				$backup,
				$menu_order
			);

			return array(
				'success'   => true,
				'message'   => esc_html__( 'Filter successfully added to loop.', 'uncanny-automator' ),
				'filter_id' => $modifier_id,
				'filter'    => $this->modifier_store->get( $modifier_id ),
			);
		} catch ( \Exception $e ) {
			return new WP_Error(
				'filter_save_failed',
				sprintf(
					/* translators: %s Error message. */
					esc_html__( 'Failed to save filter: %s', 'uncanny-automator' ),
					$e->getMessage()
				)
			);
		}
	}

	/**
	 * Update a unified loop filter's fields/backup, merging into the
	 * existing row payload (legacy update parity).
	 *
	 * @param int   $modifier_id Modifier row id.
	 * @param array $modifier    Existing modifier row.
	 * @param array $fields      Field values to merge.
	 * @param array $backup      Backup payload to merge.
	 *
	 * @return array|WP_Error
	 */
	private function update_unified_filter( int $modifier_id, array $modifier, array $fields, array $backup ) {
		try {
			$this->modifier_store->update(
				$modifier_id,
				array(
					'fields' => ! empty( $fields ) ? array_replace( (array) ( $modifier['fields'] ?? array() ), $fields ) : (array) ( $modifier['fields'] ?? array() ),
					'backup' => ! empty( $backup ) ? array_replace( (array) ( $modifier['backup'] ?? array() ), $backup ) : (array) ( $modifier['backup'] ?? array() ),
				)
			);

			return array(
				'success' => true,
				'filter'  => $this->modifier_store->get( $modifier_id ),
			);
		} catch ( \Exception $e ) {
			return new WP_Error(
				'filter_update_failed',
				sprintf(
					/* translators: %s Error message. */
					esc_html__( 'Failed to update filter: %s', 'uncanny-automator' ),
					$e->getMessage()
				)
			);
		}
	}

	/**
	 * Add filter to loop.
	 *
	 * Hydrates the entity with registry data before persisting.
	 */
	public function add_to_loop(
		int $loop_id,
		string $filter_code,
		string $integration_code,
		array $fields = array(),
		array $backup = array()
	) {
		return Recipe_Change_Events::operation(
			function () use ( $loop_id, $filter_code, $integration_code, $fields, $backup ) {
				$unified = $this->resolve_unified_loop( $loop_id );
				if ( null === $unified ) {
					return new WP_Error(
						'loop_not_found',
						sprintf(
							/* translators: %d Loop ID. */
							esc_html__( 'Loop not found with ID: %d', 'uncanny-automator' ),
							$loop_id
						)
					);
				}

				// Validate filter code and integration code are not empty.
				if ( empty( $filter_code ) ) {
					return new WP_Error( 'invalid_filter_code', esc_html__( 'Filter code cannot be empty.', 'uncanny-automator' ) );
				}

				if ( empty( $integration_code ) ) {
					return new WP_Error( 'invalid_integration_code', esc_html__( 'Integration code cannot be empty.', 'uncanny-automator' ) );
				}

				$definition = $this->registry_service->validate_filter_code_and_get_definition( $filter_code );
				if ( is_wp_error( $definition ) ) {
					return $definition;
				}

				// Reject filters incompatible with the loop's iteration type.
				$type_mismatch = $this->check_iteration_type_mismatch( $loop_id, $definition );
				if ( is_wp_error( $type_mismatch ) ) {
					return $type_mismatch;
				}

				$result = $this->add_to_unified_loop( $unified, $filter_code, $integration_code, $fields, $backup );
				if ( ! is_wp_error( $result ) ) {
					$this->complete_filter_write( Recipe_Change_Operation::CREATED, $result['filter'] ?? null );
				}

				return $result;
			}
		);
	}

	/**
	 * Fire the recipe_changed event for a loop-filter write.
	 *
	 * @param string     $operation A Recipe_Change_Operation value.
	 * @param array|null $modifier  The modifier row as the store reads it (before the delete, for a delete).
	 *
	 * @return void
	 */
	private function complete_filter_write( string $operation, ?array $modifier ): void {
		$recipe_id = (int) ( $modifier['recipe_id'] ?? 0 );
		if ( null === $modifier || $recipe_id <= 0 ) {
			return;
		}

		Recipe_Change_Events::changed( new Recipe_Changed( $operation, Wire_Node_Type::BLOCK_MODIFIER, (int) $modifier['id'], $recipe_id ) );
	}
	/**
	 * Get a loop's filters as unified modifier rows.
	 *
	 * The read seam for filter discovery — runtime consumers (Pro's loop
	 * entity resolution via `automator_get_loop_filters()`) and any other
	 * outside-`src/app` caller get the store rows (id, code,
	 * integration_code, decoded fields/backup, menu_order) in one shot
	 * instead of re-assembling them from individual post meta.
	 *
	 * @param int $loop_id Unified loop block post id.
	 *
	 * @return array|WP_Error Modifier rows ordered by menu_order, or
	 *                        WP_Error when $loop_id isn't a unified loop.
	 */
	public function get_loop_filters( int $loop_id ) {
		$unified = $this->resolve_unified_loop( $loop_id );
		if ( null === $unified ) {
			return new WP_Error(
				'loop_not_found',
				sprintf(
					/* translators: %d Loop ID. */
					esc_html__( 'Loop not found with ID: %d', 'uncanny-automator' ),
					$loop_id
				)
			);
		}

		return $this->modifier_store->get_by_block( $loop_id, 'loop_filter' );
	}

	/**
	 * Get filter.
	 *
	 * @param mixed $filter_id The ID.
	 * @return mixed
	 */
	public function get_filter( $filter_id ) {
		$modifier = $this->modifier_store->get( (int) $filter_id );

		if ( null === $modifier || 'loop_filter' !== (string) ( $modifier['block_modifier_type'] ?? '' ) ) {
			return new WP_Error(
				'filter_not_found',
				sprintf(
					/* translators: %d Filter ID. */
					esc_html__( 'Filter not found with ID: %d', 'uncanny-automator' ),
					$filter_id
				)
			);
		}

		return array(
			'success' => true,
			'filter'  => $modifier,
		);
	}
	/**
	 * Update filter.
	 *
	 * @param mixed $filter_id The ID.
	 * @param array $fields The fields.
	 * @param array $backup The backup.
	 * @return mixed
	 */
	public function update_filter( $filter_id, array $fields = array(), array $backup = array() ) {
		return Recipe_Change_Events::operation(
			function () use ( $filter_id, $fields, $backup ) {
				$modifier = $this->modifier_store->get( (int) $filter_id );
				if ( null === $modifier || 'loop_filter' !== (string) ( $modifier['block_modifier_type'] ?? '' ) ) {
					return new WP_Error(
						'filter_not_found',
						sprintf(
							/* translators: %d Filter ID. */
							esc_html__( 'Filter not found with ID: %d', 'uncanny-automator' ),
							$filter_id
						)
					);
				}

				$result = $this->update_unified_filter( (int) $filter_id, $modifier, $fields, $backup );
				if ( ! is_wp_error( $result ) ) {
					$this->complete_filter_write( Recipe_Change_Operation::UPDATED, $result['filter'] ?? null );
				}

				return $result;
			}
		);
	}
	/**
	 * Delete filter.
	 *
	 * @param mixed $filter_id The ID.
	 * @param bool $confirmed The confirmed.
	 * @return mixed
	 */
	public function delete_filter( $filter_id, bool $confirmed = false ) {
		return Recipe_Change_Events::operation(
			function () use ( $filter_id, $confirmed ) {
				if ( ! $confirmed ) {
					return new WP_Error( 'confirmation_required', esc_html__( 'Deletion must be confirmed.', 'uncanny-automator' ) );
				}

				$modifier = $this->modifier_store->get( (int) $filter_id );
				if ( null === $modifier || 'loop_filter' !== (string) ( $modifier['block_modifier_type'] ?? '' ) ) {
					return new WP_Error(
						'filter_not_found',
						sprintf(
							/* translators: %d Filter ID. */
							esc_html__( 'Filter not found with ID: %d', 'uncanny-automator' ),
							$filter_id
						)
					);
				}

				try {
					$this->modifier_store->delete( (int) $filter_id );

					$this->complete_filter_write( Recipe_Change_Operation::DELETED, $modifier );

					return array(
						'success'           => true,
						'deleted_filter_id' => (int) $modifier['id'],
						'filter_code'       => (string) ( $modifier['code'] ?? '' ),
					);
				} catch ( \Exception $e ) {
					return new WP_Error(
						'delete_filter_failed',
						sprintf(
							/* translators: %s Error message. */
							esc_html__( 'Failed to delete filter: %s', 'uncanny-automator' ),
							$e->getMessage()
						)
					);
				}
			}
		);
	}
	/**
	 * Delete loop filters.
	 *
	 * @param int $loop_id The ID.
	 * @param bool $confirmed The confirmed.
	 * @return mixed
	 */
	public function delete_loop_filters( int $loop_id, bool $confirmed = false ) {
		return Recipe_Change_Events::operation(
			function () use ( $loop_id, $confirmed ) {
				if ( ! $confirmed ) {
					return new WP_Error( 'confirmation_required', esc_html__( 'Deletion must be confirmed.', 'uncanny-automator' ) );
				}

				if ( ! $this->validate_loop_exists( $loop_id ) ) {
					return new WP_Error(
						'loop_not_found',
						sprintf(
							/* translators: %d Loop ID. */
							esc_html__( 'Loop not found with ID: %d', 'uncanny-automator' ),
							$loop_id
						)
					);
				}

				$loop      = Database::get_block_store()->get( $loop_id );
				$recipe_id = (int) ( $loop['recipe_id'] ?? 0 );
				$deleted   = 0;

				try {
					$modifiers = $this->modifier_store->get_by_block( $loop_id, 'loop_filter' );
					foreach ( $modifiers as $modifier ) {
						$this->modifier_store->delete( (int) ( $modifier['id'] ?? 0 ) );
						++$deleted;
					}

					if ( $recipe_id > 0 && $deleted > 0 ) {
						Recipe_Change_Events::changed( new Recipe_Changed( Recipe_Change_Operation::UPDATED, Wire_Node_Type::BLOCK, $loop_id, $recipe_id ) );
					}

					return array(
						'success'       => true,
						'loop_id'       => $loop_id,
						'deleted_count' => count( $modifiers ),
					);
				} catch ( \Exception $e ) {
					// The filters deleted before the failure stay deleted.
					if ( $recipe_id > 0 && $deleted > 0 ) {
						Recipe_Change_Events::changed( new Recipe_Changed( Recipe_Change_Operation::UPDATED, Wire_Node_Type::BLOCK, $loop_id, $recipe_id ) );
					}

					return new WP_Error(
						'delete_filters_failed',
						sprintf(
							/* translators: %s Error message. */
							esc_html__( 'Failed to delete filters: %s', 'uncanny-automator' ),
							$e->getMessage()
						)
					);
				}
			}
		);
	}

	/**
	 * Check if a filter's iteration types are compatible with the loop.
	 *
	 * @param int   $loop_id    Loop post ID.
	 * @param array $definition Filter registry definition.
	 *
	 * @return \WP_Error|null WP_Error on mismatch, null when compatible.
	 */
	private function check_iteration_type_mismatch( int $loop_id, array $definition ) {

		$filter_types = $definition['iteration_types'] ?? array();

		if ( empty( $filter_types ) ) {
			return null;
		}

		$unified = $this->resolve_unified_loop( $loop_id );
		if ( null === $unified ) {
			return null;
		}
		$loop_type = $unified['iterable_expression']['type'] ?? 'users';

		if ( in_array( $loop_type, $filter_types, true ) ) {
			return null;
		}

		return new WP_Error(
			'filter_type_mismatch',
			sprintf(
				/* translators: %1$s Filter code, %2$s Filter types, %3$s Loop type. */
				esc_html__( 'Filter "%1$s" supports iteration types [%2$s] but the loop is type "%3$s".', 'uncanny-automator' ),
				$definition['code'] ?? '',
				implode( ', ', $filter_types ),
				$loop_type
			)
		);
	}
}
