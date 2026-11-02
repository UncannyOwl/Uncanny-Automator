<?php
/**
 * Block CRUD Service
 *
 * Business logic for creating, reading, updating, and deleting
 * unified blocks (filter, delay_schedule).
 *
 * @package Uncanny_Automator
 */
declare(strict_types=1);

namespace Uncanny_Automator\App\Recipe_Builder\Block\Services;

use Uncanny_Automator\App\Events\Recipe_Change_Events;
use Uncanny_Automator\App\Events\Dtos\Recipe_Changed;
use Uncanny_Automator\App\Recipe_Builder\Block\Enums\Block_Type;
use Uncanny_Automator\App\Recipe_Builder\Block\Enums\Block_Path_Code;
use Uncanny_Automator\App\Recipe_Builder\Block\Registry\Blocks\Delay_Block;
use Uncanny_Automator\App\Recipe_Builder\Block\Value_Objects\Fields_Bag;
use Uncanny_Automator\App\Recipe_Builder\Loop\Iterable_Expression\Enums\Iteration_Type;
use Uncanny_Automator\App\Recipe_Builder\Loop\Value_Objects\Loop_Field_Translator;
use Uncanny_Automator\App\Recipe_Builder\Action\Value_Objects\Delay_Unit;
use Uncanny_Automator\App\Recipe_Builder\Condition\Services\Condition_Registry_Service;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Services\Recipe_Reorder_Service;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Services\Recipe_Runnability_Service;
use Uncanny_Automator\App\Infrastructure\Database\Database;
use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Block_Modifier_Store;
use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Block_Store;
use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Recipe_Reorder_Store;
use Uncanny_Automator\App\Events\Enums\Recipe_Change_Operation;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Wire_Serializer\Enums\Wire_Node_Type;
use WP_Error;
use Exception;

/**
 * Block CRUD Service Class
 *
 * Handles block lifecycle operations with validation
 * and action path management.
 */
class Block_Crud_Service {

	/**
	 * Service instance (singleton).
	 *
	 * @var Block_Crud_Service|null
	 */
	private static ?Block_Crud_Service $instance = null;

	/**
	 * Persists and reads the block rows this service creates, moves and deletes.
	 *
	 * @var Block_Store
	 */
	private Block_Store $block_store;

	/**
	 * Writes `menu_order` when a moved run is resequenced into its new parent.
	 *
	 * @var Recipe_Reorder_Store
	 */
	private Recipe_Reorder_Store $reorder_store;

	/**
	 * Decides which kept steps a keep-mode delete must remove.
	 *
	 * @var Block_Keep_Content_Planner
	 */
	private Block_Keep_Content_Planner $keep_planner;

	/**
	 * Refuses a block where the placement rules reject it.
	 *
	 * @var Placement_Rule_Service
	 */
	private Placement_Rule_Service $placement;

	/**
	 * Reads the modifier rows (filter conditions, loop filters) that hang off blocks.
	 *
	 * @var Block_Modifier_Store
	 */
	private Block_Modifier_Store $modifier_store;

	/**
	 * Constructor.
	 *
	 * @param Block_Store|null                $block_store    Block store instance.
	 * @param Recipe_Reorder_Store|null       $reorder_store  Sequencing store instance.
	 * @param Block_Keep_Content_Planner|null $keep_planner   Keep-mode placement planner (defaults to one over the same store).
	 * @param Placement_Rule_Service|null     $placement      Placement rules.
	 * @param Block_Modifier_Store|null       $modifier_store Block-modifier store instance.
	 *
	 * @return void
	 */
	private function __construct( ?Block_Store $block_store = null, ?Recipe_Reorder_Store $reorder_store = null, ?Block_Keep_Content_Planner $keep_planner = null, ?Placement_Rule_Service $placement = null, ?Block_Modifier_Store $modifier_store = null ) {
		$this->block_store    = $block_store ?? Database::get_block_store();
		$this->reorder_store  = $reorder_store ?? Database::get_recipe_reorder_store();
		$this->keep_planner   = $keep_planner ?? new Block_Keep_Content_Planner( $this->block_store );
		$this->placement      = $placement ?? Placement_Rule_Service::instance();
		$this->modifier_store = $modifier_store ?? Database::get_block_modifier_store();
	}

	/**
	 * Get service instance (singleton).
	 *
	 * @return Block_Crud_Service
	 */
	public static function instance(): Block_Crud_Service {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Create a service instance with explicit dependencies (for testing).
	 *
	 * @param Block_Store|null                $block_store    Block store instance.
	 * @param Recipe_Reorder_Store|null       $reorder_store  Sequencing store instance.
	 * @param Block_Keep_Content_Planner|null $keep_planner   Keep-mode placement planner.
	 * @param Placement_Rule_Service|null     $placement      Placement rules.
	 * @param Block_Modifier_Store|null       $modifier_store Block-modifier store instance.
	 *
	 * @return self
	 */
	public static function create_with_dependencies(
		?Block_Store $block_store = null,
		?Recipe_Reorder_Store $reorder_store = null,
		?Block_Keep_Content_Planner $keep_planner = null,
		?Placement_Rule_Service $placement = null,
		?Block_Modifier_Store $modifier_store = null
	): self {
		return new self( $block_store, $reorder_store, $keep_planner, $placement, $modifier_store );
	}

	/**
	 * Add a new block to a recipe or parent block.
	 *
	 * @param int         $recipe_id  Recipe ID (for validation).
	 * @param string      $block_type Block type from Block_Type enum.
	 * @param int         $parent_id  Parent ID (recipe or parent block).
	 * @param array       $fields     Block fields.
	 * @param string|null $path_code  Target path of the parent block, when nesting into a specific branch.
	 *
	 * @return array|WP_Error Block data or error.
	 */
	public function add_block( int $recipe_id, string $block_type, int $parent_id, array $fields = array(), ?string $path_code = null ) {
		return Recipe_Change_Events::operation(
			function () use ( $recipe_id, $block_type, $parent_id, $fields, $path_code ) {

				if ( ! Block_Type::is_valid( $block_type ) ) {
					return new WP_Error(
						'invalid_block_type',
						// translators: %s is the block type.
						sprintf( esc_html_x( 'Invalid block type: %s', 'Block service error', 'uncanny-automator' ), esc_html( $block_type ) )
					);
				}

				// Validate it's a recipe post type.
				$recipe = Database::get_recipe_store()->get_wp_post( (int) $recipe_id );
				if ( ! $recipe ) {
					return new WP_Error( 'recipe_not_found', esc_html_x( 'Recipe not found', 'Block service error', 'uncanny-automator' ) );
				}

				if ( $parent_id !== $recipe_id ) {
					$ownership = $this->verify_block_recipe( $parent_id, $recipe_id );
					if ( is_wp_error( $ownership ) ) {
						return $ownership;
					}
				}

				// An explicit path only makes sense inside a path-aware parent block.
				// Validate before any write — the store's create-time reconcile stamps
				// the parent's DEFAULT path, so an unvalidated request was silently
				// dropped into the wrong branch.
				if ( null !== $path_code ) {
					if ( $parent_id === $recipe_id ) {
						return new WP_Error(
							'automator_path_code_forbidden',
							esc_html_x( 'path_code is not valid at the recipe root.', 'Block service error', 'uncanny-automator' )
						);
					}
					$path_valid = $this->validate_path_for_block( $parent_id, $path_code );
					if ( is_wp_error( $path_valid ) ) {
						return $path_valid;
					}
				}

				// The blocks above the slot decide whether this type may sit there; the recipe root allows every type.
				$violation = $this->placement->violation( $this->placement->chain_for( $parent_id, (string) ( $path_code ?? '' ) ), $this->placement->block_candidate( $block_type ) );
				if ( null !== $violation ) {
					return $this->placement->rejection( $violation, $block_type );
				}

				$menu_order = $this->get_next_menu_order( $parent_id );

				// A Loop block added without a type is a Users loop; its default filter is added when it is saved (Loop_CRUD_Service::update_loop()).
				if ( Block_Type::LOOP === $block_type && '' === Fields_Bag::from_array( $fields )->scalar( 'LOOP_TYPE' ) ) {
					$fields = array_merge( $fields, Loop_Field_Translator::to_fields( array( 'type' => Iteration_Type::USERS ) ) );
				}

				try {
					// The explicit path rides the insert so a create is one write.
					$block_id = $this->block_store->create( $parent_id, $block_type, $fields, $menu_order, null, null, null, $path_code );
				} catch ( Exception $e ) {
					return new WP_Error( 'block_creation_failed', $e->getMessage() );
				}

				$block = $this->block_store->get( $block_id );
				$this->complete_block_write( Recipe_Change_Operation::CREATED, $block );

				return $block;
			}
		);
	}

	/**
	 * Get a block by ID.
	 *
	 * @param int $block_id Block post ID.
	 *
	 * @return array|WP_Error Block data or error.
	 */
	public function get_block( int $block_id ) {
		$block = $this->block_store->get( $block_id );

		if ( null === $block ) {
			return new WP_Error( 'block_not_found', esc_html_x( 'Block not found', 'Block service error', 'uncanny-automator' ) );
		}

		return $block;
	}

	/**
	 * Confirm a block belongs to a recipe, for every transport that acts on a
	 * block named in a recipe-scoped request.
	 *
	 * Fails closed. Every block is stamped with its recipe at create — from the
	 * caller, or by walking its parents — so one that reads no recipe is an
	 * orphan, and an orphan is the last thing to wave through.
	 *
	 * @param int $block_id  Block post ID.
	 * @param int $recipe_id Recipe the request is scoped to.
	 *
	 * @return true|WP_Error True when owned; `block_not_found` or `block_recipe_mismatch` otherwise.
	 */
	public function verify_block_recipe( int $block_id, int $recipe_id ) {
		$block = $this->block_store->get( $block_id );

		if ( null === $block ) {
			return new WP_Error( 'block_not_found', esc_html_x( 'Block not found', 'Block service error', 'uncanny-automator' ) );
		}

		$owner_id = (int) ( $block['recipe_id'] ?? 0 );
		if ( $owner_id <= 0 || $owner_id !== $recipe_id ) {
			return new WP_Error( 'block_recipe_mismatch', esc_html_x( 'Block does not belong to this recipe.', 'Block service error', 'uncanny-automator' ) );
		}

		return true;
	}

	/**
	 * Confirm a block modifier (filter condition, loop filter) belongs to a
	 * recipe, for every transport that acts on a modifier named in a
	 * recipe-scoped request.
	 *
	 * A modifier belongs to the recipe of the block it hangs off. Fails closed
	 * like verify_block_recipe(): a modifier whose block reads no recipe, or
	 * no block, belongs to none.
	 *
	 * @since 8.0.0
	 *
	 * @param int $modifier_id Block-modifier post ID.
	 * @param int $recipe_id   Recipe the request is scoped to.
	 *
	 * @return true|WP_Error True when owned; `block_modifier_not_found` or `block_modifier_recipe_mismatch` otherwise.
	 */
	public function verify_block_modifier_recipe( int $modifier_id, int $recipe_id ) {
		$modifier = $this->modifier_store->get( $modifier_id );

		if ( null === $modifier ) {
			return new WP_Error( 'block_modifier_not_found', esc_html_x( 'Block modifier not found', 'Block service error', 'uncanny-automator' ) );
		}

		if ( is_wp_error( $this->verify_block_recipe( (int) ( $modifier['parent_block_id'] ?? 0 ), $recipe_id ) ) ) {
			return new WP_Error(
				'block_modifier_recipe_mismatch',
				sprintf(
					/* translators: %1$d Block modifier ID, %2$d Recipe ID. */
					esc_html_x( 'Block modifier %1$d does not belong to recipe %2$d.', 'Block service error', 'uncanny-automator' ),
					$modifier_id,
					$recipe_id
				)
			);
		}

		return true;
	}

	/**
	 * Update a block's fields or configuration.
	 *
	 * @param int   $block_id Block post ID.
	 * @param array $data     Data to update (fields, menu_order).
	 *
	 * @return array|WP_Error Updated block data or error.
	 */
	public function update_block( int $block_id, array $data ) {
		return Recipe_Change_Events::operation(
			function () use ( $block_id, $data ) {
				if ( ! $this->block_store->exists( $block_id ) ) {
					return new WP_Error( 'block_not_found', esc_html_x( 'Block not found', 'Block service error', 'uncanny-automator' ) );
				}

				try {
					$this->block_store->update( $block_id, $data );
				} catch ( Exception $e ) {
					return new WP_Error( 'block_update_failed', $e->getMessage() );
				}

				$block = $this->block_store->get( $block_id );
				$this->complete_block_write( Recipe_Change_Operation::UPDATED, $block );

				return $block;
			}
		);
	}

	/**
	 * Fire the recipe_changed event for a block write.
	 *
	 * @param string     $operation A Recipe_Change_Operation value.
	 * @param array|null $block     The block as the store reads it (before the delete, for a delete).
	 *
	 * @return void
	 */
	private function complete_block_write( string $operation, ?array $block ): void {
		$recipe_id = (int) ( $block['recipe_id'] ?? 0 );
		if ( null === $block || $recipe_id <= 0 ) {
			return;
		}

		Recipe_Change_Events::changed( new Recipe_Changed( $operation, Wire_Node_Type::BLOCK, (int) $block['id'], $recipe_id ) );
	}

	/**
	 * Delete a block.
	 *
	 * When $delete_content is false, every step inside the block — at every
	 * depth, including nested blocks moved as a unit — is relocated into the
	 * block's own slot on its parent (the block's path, when the parent is
	 * itself a block) and the parent's run is resequenced around them. When
	 * true, every action inside the block at every depth is deleted; nested
	 * blocks are removed by the store's recursive delete below.
	 *
	 * Modifiers (a filter's conditions, a loop's loop filters) are always
	 * deleted with their own block regardless of this flag, including the
	 * modifiers of a nested block in delete mode.
	 *
	 * Not atomic, in BOTH modes: the content pass is committed before
	 * `block_store->delete()` runs below — the moves and resequencing in keep
	 * mode, the descendant action deletes in delete mode. There is no
	 * transaction in `src/app` by design, so a throw from `delete()` reports
	 * failure while leaving that work in place — a known, accepted
	 * partial-write window, not a bug to route around here. What landed is
	 * reported: the block fires `updated` when a refusal follows a landed step.
	 *
	 * @param int  $block_id        Block post ID.
	 * @param bool $delete_content  Whether to delete every step inside the block (true) or move them into its slot (false).
	 *
	 * @return true|WP_Error True on success or error.
	 */
	public function delete_block( int $block_id, bool $delete_content = false ) {
		return Recipe_Change_Events::operation(
			function () use ( $block_id, $delete_content ) {
				$block = $this->block_store->get( $block_id );

				if ( null === $block ) {
					return new WP_Error( 'block_not_found', esc_html_x( 'Block not found', 'Block service error', 'uncanny-automator' ) );
				}

				// The content pass runs before the block is removed and can refuse.
				// It must: the store's delete() recurses, so falling through after a
				// failed keep would destroy the very steps the caller asked to keep.
				$landed  = 0;
				$content = $delete_content
					? $this->delete_descendant_actions( $block_id, $landed )
					: $this->move_children_to_parent( $block, $landed );

				if ( is_wp_error( $content ) ) {
					// Steps deleted or moved before the refusal stay where they are.
					if ( 0 < $landed ) {
						$this->complete_block_write( Recipe_Change_Operation::UPDATED, $block );
					}

					return $content;
				}

				try {
					$this->block_store->delete( $block_id );
				} catch ( Exception $e ) {
					// The store removes the block's modifiers and nested blocks before the block itself.
					$this->complete_block_write( Recipe_Change_Operation::UPDATED, $block );

					return new WP_Error( 'block_delete_failed', $e->getMessage() );
				}

				// Deleting a block's content can take a recipe's last live action, and
				// nothing else on this path demotes — action and trigger deletes call
				// this for the same reason. Without it the builder shows the recipe
				// live while execution refuses to run it. At the service layer so REST
				// and MCP both inherit it. A no-op when the recipe is already off or
				// still runnable.
				$recipe_id = (int) ( $block['recipe_id'] ?? 0 );
				if ( $recipe_id > 0 ) {
					Recipe_Runnability_Service::instance()->demote_if_empty( $recipe_id );
				}

				$this->complete_block_write( Recipe_Change_Operation::DELETED, $block );

				return true;
			}
		);
	}

	/**
	 * Get all blocks for a parent (recipe or block).
	 *
	 * @param int $parent_id Parent ID.
	 *
	 * @return array Array of block data arrays.
	 */
	public function get_blocks_for_parent( int $parent_id ): array {
		return $this->block_store->get_by_parent( $parent_id );
	}

	/**
	 * Validate a path code is valid for the given block's type.
	 *
	 * The single guardian for block-path validity (moved here out of the REST
	 * layer so every consumer shares it).
	 *
	 * @param int    $block_id  Parent block post id.
	 * @param string $path_code Requested path code.
	 * @return true|WP_Error
	 */
	public function validate_path_for_block( int $block_id, string $path_code ) {
		if ( ! $this->block_store->exists( $block_id ) ) {
			return new WP_Error( 'block_not_found', esc_html_x( 'Block not found', 'Block service error', 'uncanny-automator' ) );
		}
		if ( ! Block_Path_Code::is_valid( $path_code ) ) {
			return new WP_Error(
				'automator_invalid_path_code',
				// translators: %s is the path_code value.
				sprintf( esc_html_x( 'Invalid path_code "%s".', 'Block service error', 'uncanny-automator' ), $path_code )
			);
		}
		$block      = $this->block_store->get( $block_id );
		$block_type = (string) ( $block['block_type'] ?? '' );
		if ( ! in_array( $path_code, Block_Path_Code::for_block_type( $block_type ), true ) ) {
			return new WP_Error(
				'automator_invalid_path_code',
				// translators: 1: path_code, 2: block type.
				sprintf( esc_html_x( 'Path "%1$s" is not valid for block type "%2$s".', 'Block service error', 'uncanny-automator' ), $path_code, $block_type )
			);
		}
		return true;
	}

	/**
	 * Calculate the next available menu_order for a parent.
	 *
	 * Considers both blocks and actions under the same parent.
	 *
	 * @param int $parent_id Parent ID.
	 *
	 * @return int Next menu_order value.
	 */
	public function get_next_menu_order( int $parent_id ): int {
		// Single query fetches both blocks and actions under this parent.
		$children = $this->block_store->get_children( $parent_id );

		$max_order = 0;

		foreach ( $children as $child ) {
			if ( $child['menu_order'] > $max_order ) {
				$max_order = $child['menu_order'];
			}
		}

		return $max_order + 1;
	}

	/**
	 * Move every step directly inside a block into the block's own slot.
	 *
	 * A nested block moves by reparenting one row: its children keep
	 * pointing at it, so its fields, modifiers, paths and whole subtree
	 * follow without recursion.
	 *
	 * Refuses rather than returning silently when the landing parent cannot be
	 * resolved: `delete_block()` would otherwise run the store's recursive
	 * delete and destroy the steps the caller asked to keep.
	 *
	 * Steps that would break a placement rule at their new position are
	 * deleted, with their subtrees, before anything moves.
	 *
	 * @param array $block  Block row as the store returns it.
	 * @param int   $landed Counts the steps deleted or moved, so a refusal part-way still reports them.
	 *
	 * @return true|WP_Error True when the steps are placed, WP_Error when the keep is refused.
	 */
	private function move_children_to_parent( array $block, int &$landed ) {
		$block_id  = (int) $block['id'];
		$parent_id = (int) $block['parent_id'];

		$plan = $this->keep_plan( $block );
		if ( is_wp_error( $plan ) ) {
			return $plan;
		}

		$moved     = $plan['moved'];
		$path_code = $plan['path_code'];
		if ( empty( $moved ) ) {
			return true;
		}

		// Steps that would land somewhere a placement rule rejects are removed
		// first, as the builder's confirmation dialog already showed them. A
		// failed removal refuses before any step has moved.
		$removed = $this->delete_violating_steps( $plan['violators'], $landed );
		if ( is_wp_error( $removed ) ) {
			return $removed;
		}

		$moved = self::without( $moved, $plan['violators'] );
		if ( empty( $moved ) ) {
			return true;
		}

		// Capture the parent's running order BEFORE any reparent: afterwards
		// the moved rows are siblings carrying their old inner menu_order
		// values and the block's slot is no longer recoverable from them.
		$sequence = $this->sequence_with_block_replaced( $parent_id, $block_id, $moved );

		try {
			foreach ( $moved as $child ) {
				$child_id = self::child_id( $child );

				if ( '' !== $path_code ) {
					$this->block_store->reparent_to_path( $child_id, $parent_id, $path_code );
					++$landed;
					continue;
				}

				$this->block_store->reparent( $child_id, $parent_id );
				++$landed;
				$this->block_store->delete_path_code( $child_id );
			}
		} catch ( Exception $e ) {
			return new WP_Error(
				'block_move_failed',
				esc_html_x( 'The block was not deleted because a step inside it could not be moved. Steps moved before the failure are already outside the block; check the recipe before trying again.', 'Block service error', 'uncanny-automator' )
			);
		}

		$order = 0;
		foreach ( $sequence as $post_id ) {
			// Never hand set_menu_order() a 0: it falls through to
			// wp_update_post, which INSERTs rather than updates on id 0.
			if ( $post_id <= 0 ) {
				continue;
			}
			++$order;
			// Shared step-10 spacing: WP_Recipe_Reorder_Store::set_menu_order()
			// short-circuits only on an identical value, so matching the
			// system-wide 10/20/30 convention lets an untouched prefix no-op
			// instead of every sibling being rewritten.
			$this->reorder_store->set_menu_order( $post_id, $order * Recipe_Reorder_Service::MENU_ORDER_STEP );
		}

		return true;
	}

	/**
	 * Everything a keep-mode delete would do, worked out with no writes: the
	 * steps that move, the path they land on, and the steps a placement rule
	 * removes. The delete and its preview both read this, so a preview can
	 * never promise something the delete does not do.
	 *
	 * @param array $block Block row as the store returns it.
	 *
	 * @return array{moved:array,path_code:string,violators:array}|WP_Error The plan, or the refusal the delete would return.
	 */
	private function keep_plan( array $block ) {
		// A parent id of 0 would read every top-level block/action site-wide
		// via get_children( 0 ) and reparent the moved steps to it. Refuse
		// rather than fall through: delete_block() would then run the store's
		// recursive delete and destroy the steps we were keeping.
		if ( (int) $block['parent_id'] <= 0 ) {
			return new WP_Error(
				'block_keep_parent_unresolved',
				esc_html_x( 'Cannot keep the steps: the block has no resolvable parent.', 'Block service error', 'uncanny-automator' )
			);
		}

		$moved = $this->ordered_children( (int) $block['id'], (string) $block['block_type'] );
		if ( empty( $moved ) ) {
			return array(
				'moved'     => array(),
				'path_code' => '',
				'violators' => array(),
			);
		}

		// A row with no post id can't be reparented — reparent( 0, ... ) calls
		// wp_update_post( array( 'ID' => 0 ) ), which INSERTS a new post — and
		// skipping it would leave it under the block for the recursive delete
		// to destroy. Refuse before any write, like an unresolved parent.
		foreach ( $moved as $child ) {
			if ( self::child_id( $child ) <= 0 ) {
				return new WP_Error(
					'block_keep_child_unresolved',
					esc_html_x( 'Cannot keep the steps: a step inside the block could not be identified.', 'Block service error', 'uncanny-automator' )
				);
			}
		}

		$path_code = $this->landing_path_code( $block );

		return array(
			'moved'     => $moved,
			'path_code' => $path_code,
			'violators' => $this->keep_planner->violating_steps( $block, $moved, $path_code ),
		);
	}

	/**
	 * What deleting a block would remove, with no writes — so a caller that
	 * cannot show the builder's confirmation dialog can ask before it acts.
	 *
	 * @param int  $block_id       Block post ID.
	 * @param bool $delete_content Whether the delete would take every step inside (true) or keep them (false).
	 *
	 * @return array{would_remove:array,step_count:int}|WP_Error In keep mode `would_remove` lists each step a placement rule removes (id, entity_type, code, reason); in delete mode it is empty and `step_count` is every action inside. WP_Error when the block is missing or keep mode would refuse.
	 */
	public function plan_delete( int $block_id, bool $delete_content ) {
		$block = $this->block_store->get( $block_id );
		if ( null === $block ) {
			return new WP_Error( 'block_not_found', esc_html_x( 'Block not found', 'Block service error', 'uncanny-automator' ) );
		}

		if ( $delete_content ) {
			return array(
				'would_remove' => array(),
				'step_count'   => count( $this->block_store->collect_descendant_action_ids( $block_id ) ),
			);
		}

		$plan = $this->keep_plan( $block );
		if ( is_wp_error( $plan ) ) {
			return $plan;
		}

		return array(
			'would_remove' => $plan['violators'],
			'step_count'   => count( $plan['moved'] ),
		);
	}

	/**
	 * A block's direct steps in the order they land.
	 *
	 * The block's paths in the order the catalog declares them, then any
	 * path the instance carries that the catalog does not, and within each
	 * path the steps in their current order.
	 *
	 * @param int    $block_id   Block post ID.
	 * @param string $block_type Block type, for the declared path order.
	 *
	 * @return array Child rows in landing order.
	 */
	private function ordered_children( int $block_id, string $block_type ): array {
		$children = $this->block_store->get_children( $block_id );

		// An unstamped child reads as the block type's default path
		// everywhere else (Recipe_Tree_Builder), so it must group there.
		$default_path = Block_Path_Code::default_for_block_type( $block_type );

		$by_path = array();
		foreach ( $children as $child ) {
			$path = (string) ( $child['path_code'] ?? '' );
			if ( '' === $path ) {
				$path = $default_path;
			}
			$by_path[ $path ][] = $child;
		}

		$ordered = array();
		foreach ( Block_Path_Code::for_block_type( $block_type ) as $path ) {
			foreach ( $by_path[ $path ] ?? array() as $child ) {
				$ordered[] = $child;
			}
			unset( $by_path[ $path ] );
		}

		// Whatever is left sits on a path the catalog does not declare; it
		// still moves, in first-seen order, rather than being dropped.
		foreach ( $by_path as $remaining ) {
			foreach ( $remaining as $child ) {
				$ordered[] = $child;
			}
		}

		return $ordered;
	}

	/**
	 * The parent's child ids with the block's slot replaced by the moved run.
	 *
	 * @param int   $parent_id New parent post ID (recipe or block).
	 * @param int   $block_id  Block being deleted.
	 * @param array $moved     Child rows in landing order.
	 *
	 * @return int[] Post ids in their final order.
	 */
	private function sequence_with_block_replaced( int $parent_id, int $block_id, array $moved ): array {
		$sequence = array();
		$found    = false;

		foreach ( $this->block_store->get_children( $parent_id ) as $sibling ) {
			$sibling_id = self::child_id( $sibling );

			if ( $sibling_id !== $block_id ) {
				$sequence[] = $sibling_id;
				continue;
			}

			$found = true;

			foreach ( $moved as $child ) {
				$sequence[] = self::child_id( $child );
			}
		}

		// `get_children()` only sees draft + publish, so a block in any other
		// status is absent from the scan above and the moved ids would never
		// enter the sequence — they would keep their inner menu_order and
		// interleave with the parent's run, silently. Append instead.
		//
		// The flag, not a count comparison: with the block found and exactly
		// one step moving, the sequence is the same length as the sibling list
		// (one out, one in), so a count check appends that step a second time
		// and the resequence leaves it at the end of the parent. A filter
		// wrapping one action is the commonest block there is.
		if ( ! $found ) {
			foreach ( $moved as $child ) {
				$sequence[] = self::child_id( $child );
			}
		}

		return $sequence;
	}

	/**
	 * The path the moved steps land on.
	 *
	 * The deleted block's own path, which is by definition a path its
	 * parent declares. Empty at the recipe root, where steps carry no path.
	 *
	 * A block nested without a path stamp (rows adopted before the stamp
	 * existed) still reads as its parent's default branch everywhere else,
	 * so its steps land there rather than losing the branch.
	 *
	 * @param array $block Block row as the store returns it.
	 *
	 * @return string Path code, or an empty string for the recipe root.
	 */
	private function landing_path_code( array $block ): string {
		// get() is scoped to uo-block, so a recipe parent reads as null — and a
		// recipe root carries no path at all, whatever stamp the block held.
		$parent = $this->block_store->get( (int) $block['parent_id'] );
		if ( null === $parent ) {
			return '';
		}

		$parent_paths = Block_Path_Code::for_block_type( (string) $parent['block_type'] );
		$path_code    = (string) ( $block['path_code'] ?? '' );

		// A stamp the landing parent does not declare is stale (a move that
		// never cleared it, or a legacy row). Honouring it would push the
		// moved steps into the tree builder's undeclared-path bucket.
		if ( '' !== $path_code && in_array( $path_code, $parent_paths, true ) ) {
			return $path_code;
		}

		return Block_Path_Code::default_for_block_type( (string) $parent['block_type'] );
	}

	/**
	 * Read a child row's post id.
	 *
	 * `get_children()` builds block rows with `id` and item rows with `ID`.
	 *
	 * @param array $child Child row from `Block_Store::get_children()`.
	 *
	 * @return int Post id, or 0 when the row carries neither key.
	 */
	private static function child_id( array $child ): int {
		return (int) ( $child['id'] ?? $child['ID'] ?? 0 );
	}

	/**
	 * Delete every ACTION anywhere inside a block, at every depth.
	 *
	 * Named for what it actually removes: the store's `delete()` (called by
	 * the caller right after this) recurses over nested BLOCKS and their
	 * modifiers on its own, but never their action children, which would
	 * otherwise survive as orphans pointing at a deleted parent.
	 *
	 * The `path_code` meta is not cleared first — `wp_delete_post()` with
	 * force removes the row's meta anyway, and the extra write would
	 * invalidate the schema cache a second time per action.
	 *
	 * @param int $block_id Block post ID.
	 * @param int $landed   Counts the actions deleted, so a refusal part-way still reports them.
	 *
	 * @return true|WP_Error True when every descendant action is gone, WP_Error when one refused.
	 */
	private function delete_descendant_actions( int $block_id, int &$landed ) {
		foreach ( $this->block_store->collect_descendant_action_ids( $block_id ) as $action_id ) {
			$deleted = $this->delete_action( $action_id );
			if ( is_wp_error( $deleted ) ) {
				return $deleted;
			}
			++$landed;
		}

		return true;
	}

	/**
	 * Delete the steps the keep planner rejected: an action alone, a block with
	 * its whole subtree.
	 *
	 * @param array<int,array{id:int,entity_type:string}> $violators Steps to delete.
	 * @param int                                         $landed    Counts what was deleted, so a refusal part-way still reports it.
	 *
	 * @return true|WP_Error True when every one is gone, WP_Error on the first refusal.
	 */
	private function delete_violating_steps( array $violators, int &$landed ) {
		foreach ( $violators as $step ) {
			if ( 'block' === $step['entity_type'] ) {
				$result = $this->delete_block_subtree( (int) $step['id'], $landed );
			} else {
				$result = $this->delete_action( (int) $step['id'] );
				if ( true === $result ) {
					++$landed;
				}
			}

			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}

		return true;
	}

	/**
	 * Delete a block and everything inside it. The store's delete() recurses
	 * over nested blocks and modifiers but not their actions, so those go first.
	 *
	 * @param int $block_id Block post ID.
	 * @param int $landed   Counts what was deleted, so a refusal part-way still reports it.
	 *
	 * @return true|WP_Error
	 */
	private function delete_block_subtree( int $block_id, int &$landed ) {
		$content = $this->delete_descendant_actions( $block_id, $landed );
		if ( is_wp_error( $content ) ) {
			return $content;
		}

		// Counted even if the delete fails: the store removes the block's modifiers and nested blocks before the block itself.
		++$landed;
		try {
			$this->block_store->delete( $block_id );
		} catch ( Exception $e ) {
			return new WP_Error( 'block_delete_failed', $e->getMessage() );
		}

		return true;
	}

	/**
	 * Delete one action post.
	 *
	 * Reporting success on a refused delete would leave an orphan pointing at
	 * a parent that no longer exists.
	 *
	 * @param int $action_id Action post ID.
	 *
	 * @return true|WP_Error
	 */
	private function delete_action( int $action_id ) {
		if ( wp_delete_post( $action_id, true ) ) {
			return true;
		}

		return new WP_Error(
			'block_delete_content_failed',
			sprintf(
				// translators: %d is the action ID.
				esc_html_x( 'Failed to delete action %d inside the block.', 'Block service error', 'uncanny-automator' ),
				absint( $action_id )
			)
		);
	}

	/**
	 * The moved rows minus the ones already deleted.
	 *
	 * @param array                                     $moved     Direct child rows.
	 * @param array<int,array{id:int,entity_type:string}> $violators Deleted steps.
	 *
	 * @return array
	 */
	private static function without( array $moved, array $violators ): array {
		$ids = array_map( 'intval', array_column( $violators, 'id' ) );

		return array_values(
			array_filter(
				$moved,
				static function ( array $child ) use ( $ids ): bool {
					return ! in_array( self::child_id( $child ), $ids, true );
				}
			)
		);
	}

	/**
	 * Primary (default) child path per wrappable block type.
	 *
	 * The branch a wrapped action lands on: conditions gate the action, so
	 * the filter wrapper places it on the MET branch; the delay wrapper has
	 * a single branch.
	 *
	 * @var array<string, string>
	 */
	private const WRAP_PRIMARY_PATH = array(
		Block_Type::FILTER         => Block_Path_Code::FILTER_CONDITIONS_MET,
		Block_Type::DELAY_SCHEDULE => Block_Path_Code::DELAY_COMPLETED,
	);

	/**
	 * Wrap an existing action in a new delay_schedule block.
	 *
	 * Creates the block at the action's current position (same parent, same
	 * branch), then reparents the action onto the block's single path. The
	 * convenience seam for "delay this action" compound requests (MCP today;
	 * REST could reuse it). The block appends at the end of its siblings.
	 *
	 * @param int   $action_id    Action post ID.
	 * @param array $delay_fields Block-vocab fields (DELAY_MODE, DELAY_VALUE, ...).
	 * @return array{block_id: int, action_id: int}|WP_Error
	 */
	public function wrap_action_in_delay_block( int $action_id, array $delay_fields ) {
		return Recipe_Change_Events::operation(
			function () use ( $action_id, $delay_fields ) {
				$valid = $this->validate_delay_fields( $delay_fields );
				if ( is_wp_error( $valid ) ) {
					return $valid;
				}

				$result = $this->wrap_action( $action_id, Block_Type::DELAY_SCHEDULE, $delay_fields );
				$this->complete_wrap( $result );

				return $result;
			}
		);
	}

	/**
	 * Validate complete timing before an MCP block save or action wrapper writes.
	 *
	 * Builder-created draft blocks may be incomplete; MCP saves and action
	 * wrappers must describe the wait they will actually apply.
	 *
	 * @param array $fields Block-vocabulary timing fields.
	 * @return true|WP_Error
	 */
	public function validate_delay_fields( array $fields ) {
		$mode = $fields[ Delay_Block::FIELD_MODE ] ?? null;
		if ( Delay_Block::MODE_DELAY === $mode ) {
			$value = $fields['DELAY_VALUE'] ?? null;
			$unit  = $fields[ Delay_Block::FIELD_UNIT ] ?? null;
			if ( ! is_string( $unit ) || ! Delay_Unit::is_valid( $unit ) ) {
				return new WP_Error( 'invalid_delay_unit', 'DELAY_UNIT must be one of: ' . implode( ', ', Delay_Unit::accepted() ) . '.' );
			}
			if ( ( ! is_int( $value ) && ! is_string( $value ) ) || ! preg_match( '/^[0-9]+$/D', (string) $value ) || (float) $value > intdiv( PHP_INT_MAX, Delay_Unit::seconds_for( $unit ) ) ) {
				return new WP_Error( 'invalid_delay_value', 'DELAY_VALUE must be a non-negative whole number within the supported duration range.' );
			}
			return true;
		}

		if ( Delay_Block::MODE_SCHEDULE === $mode ) {
			$date = $fields['SCHEDULE_DATE'] ?? null;
			$time = $fields['SCHEDULE_TIME'] ?? null;
			if ( ! is_string( $date ) || ! is_string( $time ) || ! preg_match( '/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/D', $date ) || ! preg_match( '/^(0?[1-9]|1[0-2]):[0-5][0-9] [AP]M$/Di', $time ) ) {
				return new WP_Error( 'invalid_delay_schedule', 'Schedule requires SCHEDULE_DATE (Y-m-d) and SCHEDULE_TIME (h:i AM/PM).' );
			}
			$scheduled = \DateTimeImmutable::createFromFormat( '!Y-m-d g:i A', $date . ' ' . $time, wp_timezone() );
			$errors    = \DateTimeImmutable::getLastErrors();
			if ( false === $scheduled || ( false !== $errors && ( $errors['warning_count'] > 0 || $errors['error_count'] > 0 ) ) ) {
				return new WP_Error( 'invalid_delay_schedule', 'SCHEDULE_DATE and SCHEDULE_TIME must be a valid calendar date and time.' );
			}
			return true;
		}

		if ( Delay_Block::MODE_TOKEN === $mode ) {
			$token = $fields[ Delay_Block::FIELD_TOKEN ] ?? null;
			return is_string( $token ) && '' !== trim( $token )
				? true
				: new WP_Error( 'invalid_delay_token', 'Token timing requires a non-empty DELAY_TOKEN expression.' );
		}

		return new WP_Error( 'invalid_delay_mode', 'DELAY_MODE must be delay, schedule or token.' );
	}

	/**
	 * Validate every condition before a compound save creates or moves anything.
	 *
	 * @param array $conditions Condition rows from the caller.
	 * @return true|WP_Error
	 */
	public function validate_filter_conditions( array $conditions ) {
		$registry = Condition_Registry_Service::instance();
		foreach ( $conditions as $row ) {
			if ( ! is_array( $row ) || ! is_string( $row['integration'] ?? null ) || ! is_string( $row['condition_code'] ?? null ) || ( isset( $row['fields'] ) && ! is_array( $row['fields'] ) ) ) {
				return new WP_Error( 'invalid_condition', 'Each condition requires integration, condition_code and optional fields keyed by option code.' );
			}
			$exists = $registry->condition_exists( $row['integration'], $row['condition_code'] );
			if ( empty( $exists['exists'] ) ) {
				return new WP_Error( 'unknown_condition', sprintf( 'Unknown condition %s/%s. Discover codes with search type=condition.', $row['integration'], $row['condition_code'] ) );
			}
		}
		return true;
	}

	/**
	 * Wrap an existing action in a new filter block carrying the given conditions.
	 *
	 * Validates the full list, creates the block and moves the action onto its
	 * MET branch, then adds the modifiers. On a modifier failure, error data
	 * identifies the saved block, action, earlier conditions and failed row.
	 *
	 * @param int   $action_id  Action post ID.
	 * @param array $conditions List of { integration, condition_code, fields } rows.
	 * @return array{block_id: int, action_id: int, condition_ids: array<int>}|WP_Error
	 */
	public function wrap_action_in_filter_block( int $action_id, array $conditions ) {
		return Recipe_Change_Events::operation(
			function () use ( $action_id, $conditions ) {
				$valid = $this->validate_filter_conditions( $conditions );
				if ( is_wp_error( $valid ) ) {
					return $valid;
				}

				$result = $this->wrap_action( $action_id, Block_Type::FILTER, array() );
				if ( is_wp_error( $result ) ) {
					$this->complete_wrap( $result );
					return $result;
				}

				$condition_service = Filter_Condition_Crud_Service::instance();
				$condition_ids     = array();
				foreach ( $conditions as $row ) {
					$condition = $condition_service->add_condition(
						$result['block_id'],
						(string) ( $row['integration'] ?? '' ),
						(string) ( $row['condition_code'] ?? '' ),
						is_array( $row['fields'] ?? null ) ? $row['fields'] : array()
					);
					if ( is_wp_error( $condition ) ) {
						$this->complete_wrap( $result );
						return new WP_Error(
							'condition_wrap_incomplete',
							$condition->get_error_message(),
							array_merge(
								$result,
								array(
									'condition_ids' => $condition_ids,
									'failed_condition_index' => count( $condition_ids ),
								)
							)
						);
					}
					$condition_ids[] = (int) $condition['id'];
				}

				$result['condition_ids'] = $condition_ids;
				$this->complete_wrap( $result );

				return $result;
			}
		);
	}

	/**
	 * Finish a wrap: one created event for the new block, even when the wrap stopped part-way,
	 * because the block itself landed.
	 *
	 * @param array|WP_Error $result The wrap's result, or its error (whose data names the block when one was created).
	 *
	 * @return void
	 */
	private function complete_wrap( $result ): void {
		$data     = is_wp_error( $result ) ? (array) $result->get_error_data() : $result;
		$block_id = (int) ( $data['block_id'] ?? 0 );
		if ( $block_id <= 0 ) {
			return;
		}

		$this->complete_block_write( Recipe_Change_Operation::CREATED, $this->block_store->get( $block_id ) );
	}

	/**
	 * Shared wrap mechanics: block into the action's old slot, action onto
	 * the block's primary path.
	 *
	 * @param int    $action_id  Action post ID.
	 * @param string $block_type Block type from Block_Type enum (must have a WRAP_PRIMARY_PATH entry).
	 * @param array  $fields     Block fields.
	 * @return array{block_id: int, action_id: int}|WP_Error
	 */
	private function wrap_action( int $action_id, string $block_type, array $fields ) {
		$action = Database::get_action_store()->get( $action_id );
		if ( null === $action ) {
			return new WP_Error( 'invalid_action_id', esc_html_x( 'Action not found.', 'Block service error', 'uncanny-automator' ) );
		}

		$recipe_id      = $action->get_action_recipe_id()->get_value();
		$parent_id      = null !== $action->get_parent_id() && null !== $action->get_parent_id()->get_value()
			? (int) $action->get_parent_id()->get_value()
			: $recipe_id;
		$inherited_path = null !== $action->get_path_code() ? $action->get_path_code()->get_value() : null;

		$block = $this->add_block( $recipe_id, $block_type, $parent_id, $fields, $inherited_path );
		if ( is_wp_error( $block ) ) {
			return $block;
		}

		try {
			$this->block_store->reparent_to_path( $action_id, (int) $block['id'], self::WRAP_PRIMARY_PATH[ $block_type ] );
		} catch ( Exception $e ) {
			return new WP_Error(
				'block_wrap_incomplete',
				$e->getMessage(),
				array(
					'block_id'  => (int) $block['id'],
					'action_id' => $action_id,
				)
			);
		}

		return array(
			'block_id'  => (int) $block['id'],
			'action_id' => $action_id,
		);
	}
}
