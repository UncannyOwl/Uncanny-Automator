<?php
/**
 * Filter Condition CRUD Service
 *
 * Business logic for creating, reading, updating, and deleting individual
 * filter conditions within a filter block. Reads/writes through the unified
 * {@see Block_Modifier_Store}, narrowed on `block_modifier_type='filter_condition'`.
 *
 * @package Uncanny_Automator
 */
declare(strict_types=1);

namespace Uncanny_Automator\App\Recipe_Builder\Block\Services;

use Uncanny_Automator\App\Events\Recipe_Change_Events;
use Uncanny_Automator\App\Events\Dtos\Recipe_Changed;
use Exception;
use Uncanny_Automator\App\Infrastructure\Database\Database;
use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Block_Modifier_Store;
use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Block_Store;
use Uncanny_Automator\App\Recipe_Builder\Block\Enums\Block_Type;
use Uncanny_Automator\App\Events\Enums\Recipe_Change_Operation;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Wire_Serializer\Enums\Wire_Node_Type;

use WP_Error;

/**
 * Filter Condition CRUD Service Class
 *
 * Handles filter condition lifecycle within filter blocks. The "filter
 * condition" abstraction is the legacy wire shape — on disk these rows are
 * `uo-block-modifier` posts discriminated by
 * `block_modifier_type='filter_condition'`.
 */
class Filter_Condition_Crud_Service {

	/**
	 * Discriminator value for filter-condition modifier rows.
	 */
	private const MODIFIER_TYPE = 'filter_condition';

	/**
	 * Service instance (singleton).
	 *
	 * @var Filter_Condition_Crud_Service|null
	 */
	private static ?Filter_Condition_Crud_Service $instance = null;

	/**
	 * Block-modifier store (paradigm-agnostic).
	 *
	 * @var Block_Modifier_Store
	 */
	private Block_Modifier_Store $modifier_store;

	/**
	 * Reads the filter block a condition hangs off, so edits land on the right parent.
	 *
	 * @var Block_Store
	 */
	private Block_Store $block_store;

	/**
	 * Constructor.
	 *
	 * @param Block_Modifier_Store|null $modifier_store Modifier store instance.
	 * @param Block_Store|null          $block_store    Block store instance.
	 *
	 * @return void
	 */
	private function __construct(
		?Block_Modifier_Store $modifier_store = null,
		?Block_Store $block_store = null
	) {
		$this->modifier_store = $modifier_store ?? Database::get_block_modifier_store();
		$this->block_store    = $block_store ?? Database::get_block_store();
	}

	/**
	 * Get service instance (singleton).
	 *
	 * @return Filter_Condition_Crud_Service
	 */
	public static function instance(): Filter_Condition_Crud_Service {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Create a service instance with explicit dependencies (for testing).
	 *
	 * @param Block_Modifier_Store|null $modifier_store Modifier store instance.
	 * @param Block_Store|null          $block_store    Block store instance.
	 *
	 * @return self
	 */
	public static function create_with_dependencies(
		?Block_Modifier_Store $modifier_store = null,
		?Block_Store $block_store = null
	): self {
		return new self( $modifier_store, $block_store );
	}

	/**
	 * Add a filter condition to a filter block.
	 *
	 * @param int    $block_id       Parent filter block ID.
	 * @param string $integration    Integration code (e.g. WP, WC).
	 * @param string $condition_code Condition code.
	 * @param array  $fields         Condition fields.
	 *
	 * @return array|WP_Error Condition data or error.
	 */
	public function add_condition( int $block_id, string $integration, string $condition_code, array $fields = array() ) {
		return Recipe_Change_Events::operation(
			function () use ( $block_id, $integration, $condition_code, $fields ) {

				// Validate the parent is a filter block.
				$block = $this->block_store->get( $block_id );
				if ( null === $block ) {
					return new WP_Error( 'block_not_found', esc_html_x( 'Block not found', 'Filter condition service error', 'uncanny-automator' ) );
				}

				if ( Block_Type::FILTER !== $block['block_type'] ) {
					return new WP_Error( 'invalid_block_type', esc_html_x( 'Conditions can only be added to filter blocks', 'Filter condition service error', 'uncanny-automator' ) );
				}

				$menu_order = $this->get_next_menu_order( $block_id );
				$recipe_id  = (int) ( $block['recipe_id'] ?? 0 );

				try {
					$condition_id = $this->modifier_store->create(
						$recipe_id,
						$block_id,
						self::MODIFIER_TYPE,
						$integration,
						$condition_code,
						$fields,
						array(),
						$menu_order
					);
				} catch ( Exception $e ) {
					return new WP_Error( 'condition_creation_failed', $e->getMessage() );
				}

				$this->complete_condition_write( Recipe_Change_Operation::CREATED, $this->modifier_store->get( $condition_id ) );

				return $this->hydrate_condition( $this->modifier_store->get( $condition_id ) );
			}
		);
	}

	/**
	 * Get a filter condition by ID.
	 *
	 * @param int $condition_id Filter condition post ID.
	 *
	 * @return array|WP_Error Condition data or error.
	 */
	public function get_condition( int $condition_id ) {
		$condition = $this->modifier_store->get( $condition_id );

		if ( null === $condition || self::MODIFIER_TYPE !== ( $condition['block_modifier_type'] ?? '' ) ) {
			return new WP_Error( 'condition_not_found', esc_html_x( 'Filter condition not found', 'Filter condition service error', 'uncanny-automator' ) );
		}

		return $this->hydrate_condition( $condition );
	}

	/**
	 * Update a filter condition's fields.
	 *
	 * Accepts the legacy shape (`integration`, `condition_code`, `fields`,
	 * `menu_order`) and translates it to the unified Store's vocabulary.
	 *
	 * @param int   $condition_id Filter condition post ID.
	 * @param array $data         Data to update.
	 *
	 * @return array|WP_Error Updated condition data or error.
	 */
	public function update_condition( int $condition_id, array $data ) {
		return Recipe_Change_Events::operation(
			function () use ( $condition_id, $data ) {
				$condition = $this->get_condition( $condition_id );
				if ( is_wp_error( $condition ) ) {
					return $condition;
				}

				$translated = array();

				if ( isset( $data['integration'] ) ) {
					$translated['integration_code'] = (string) $data['integration'];
				}
				if ( isset( $data['condition_code'] ) ) {
					$translated['code'] = (string) $data['condition_code'];
				}
				if ( array_key_exists( 'fields', $data ) ) {
					$translated['fields'] = is_array( $data['fields'] ) ? $data['fields'] : array();
				}
				if ( isset( $data['menu_order'] ) ) {
					$translated['menu_order'] = (int) $data['menu_order'];
				}

				try {
					$this->modifier_store->update( $condition_id, $translated );
				} catch ( Exception $e ) {
					return new WP_Error( 'condition_update_failed', $e->getMessage() );
				}

				$this->complete_condition_write( Recipe_Change_Operation::UPDATED, $this->modifier_store->get( $condition_id ) );

				return $this->hydrate_condition( $this->modifier_store->get( $condition_id ) );
			}
		);
	}

	/**
	 * Delete a filter condition.
	 *
	 * @param int $condition_id Filter condition post ID.
	 *
	 * @return true|WP_Error True on success or error.
	 */
	public function delete_condition( int $condition_id ) {
		return Recipe_Change_Events::operation(
			function () use ( $condition_id ) {
				$condition = $this->get_condition( $condition_id );
				if ( is_wp_error( $condition ) ) {
					return $condition;
				}

				$row = $this->modifier_store->get( $condition_id );

				try {
					$this->modifier_store->delete( $condition_id );
				} catch ( Exception $e ) {
					return new WP_Error( 'condition_delete_failed', $e->getMessage() );
				}

				$this->complete_condition_write( Recipe_Change_Operation::DELETED, $row );

				return true;
			}
		);
	}

	/**
	 * Fire the recipe_changed event for a condition write.
	 *
	 * @param string     $operation A Recipe_Change_Operation value.
	 * @param array|null $row       The modifier row as the store reads it (before the delete, for a delete).
	 *
	 * @return void
	 */
	private function complete_condition_write( string $operation, ?array $row ): void {
		$recipe_id = (int) ( $row['recipe_id'] ?? 0 );
		if ( null === $row || $recipe_id <= 0 ) {
			return;
		}

		Recipe_Change_Events::changed( new Recipe_Changed( $operation, Wire_Node_Type::BLOCK_MODIFIER, (int) $row['id'], $recipe_id ) );
	}

	/**
	 * Get all filter conditions for a block.
	 *
	 * @param int $block_id Parent filter block ID.
	 *
	 * @return array Array of condition data arrays.
	 */
	public function get_conditions_for_block( int $block_id ): array {
		$rows = $this->modifier_store->get_by_block( $block_id, self::MODIFIER_TYPE );

		return array_map( array( $this, 'hydrate_condition' ), $rows );
	}

	/**
	 * Project a Block_Modifier_Store row down to the legacy filter-condition
	 * shape REST consumers (and existing tests) expect.
	 *
	 * @param array|null $row Modifier row.
	 *
	 * @return array Legacy shape (id, block_id, integration, condition_code, fields, menu_order).
	 */
	private function hydrate_condition( ?array $row ): array {
		if ( null === $row ) {
			return array();
		}

		return array(
			'id'             => (int) ( $row['id'] ?? 0 ),
			'block_id'       => (int) ( $row['parent_block_id'] ?? 0 ),
			'integration'    => (string) ( $row['integration_code'] ?? '' ),
			'condition_code' => (string) ( $row['code'] ?? '' ),
			'fields'         => is_array( $row['fields'] ?? null ) ? $row['fields'] : array(),
			'menu_order'     => (int) ( $row['menu_order'] ?? 0 ),
		);
	}

	/**
	 * Calculate the next available menu_order for a block's conditions.
	 *
	 * @param int $block_id Parent filter block ID.
	 *
	 * @return int Next menu_order value.
	 */
	private function get_next_menu_order( int $block_id ): int {
		$existing = $this->modifier_store->get_by_block( $block_id, self::MODIFIER_TYPE );

		$max_order = 0;

		foreach ( $existing as $condition ) {
			$order = (int) ( $condition['menu_order'] ?? 0 );
			if ( $order > $max_order ) {
				$max_order = $order;
			}
		}

		return $max_order + 1;
	}
}
