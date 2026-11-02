<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Restful\Recipe\Blocks;

use WP_Error;
use Uncanny_Automator\App\Recipe_Builder\Block\Enums\Block_Type;
use Uncanny_Automator\App\Recipe_Builder\Block\Services\Block_Crud_Service;
use Uncanny_Automator\App\Recipe_Builder\Loop\Services\Loop_CRUD_Service;
use Uncanny_Automator\App\Recipe_Builder\Loop\Value_Objects\Loop_Field_Translator;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Portability\Recipe_Duplication_Service;

/**
 * Loop Block REST handler.
 *
 * Thin dispatcher: adds create unified `uo-block` (`block_type='loop'`)
 * rows via Block_Crud_Service; update/delete go through
 * Loop_CRUD_Service.
 *
 * @package Uncanny_Automator\App\Transports\Restful\Recipe\Blocks
 */
class Loop_Block_Rest extends Recipe_Block_Rest {

	/**
	 * Add a loop block to recipe.
	 *
	 * Creates a `uo-block` (`block_type='loop'`) via the unified block
	 * service — nested parents, explicit `path_code` placement, and the
	 * `recipe_id` stamp ride the same machinery as every other block. The
	 * service adds it as a Users loop with no filter; its type's default
	 * filter is added when it is first saved (Loop_CRUD_Service::update_loop()).
	 *
	 * @return int|WP_Error Loop block post ID on success.
	 */
	protected function do_add_block() {
		$result = Block_Crud_Service::instance()->add_block(
			$this->get_recipe_id(),
			Block_Type::LOOP,
			(int) $this->get_parent_id(),
			array(),
			$this->get_path_code()
		);

		if ( is_wp_error( $result ) ) {
			return $this->add_block_failure( $result, 'automator_add_loop_block_failed' );
		}

		return (int) $result['id'];
	}

	/**
	 * Update loop block configuration.
	 *
	 * Accepts unified block fields (LOOP_TYPE, LOOP_TOKEN) and delegates the
	 * field-shape translation to {@see Loop_Field_Translator::from_fields}
	 * — the shared VO that also powers the schema v3 cache's read-side
	 * inverse projection.
	 *
	 * @return bool|WP_Error
	 */
	protected function do_update_block() {
		$fields = $this->get_fields();
		$status = $fields['status'] ?? null;

		$result = Loop_CRUD_Service::instance()->update_loop(
			(int) $this->get_post_id(),
			Loop_Field_Translator::from_fields( $fields ),
			is_string( $status ) ? $status : null,
			null,
			$fields
		);

		if ( is_wp_error( $result ) ) {
			return $this->failure(
				$result->get_error_message(),
				400,
				'automator_update_loop_block_failed'
			);
		}

		return true;
	}

	/**
	 * Delete loop block from recipe.
	 *
	 * Rides the block machinery: modifiers removed, child actions
	 * re-parented with their path codes.
	 *
	 * @return bool|WP_Error
	 */
	protected function do_delete_block() {
		$result = Loop_CRUD_Service::instance()->delete_loop( (int) $this->get_post_id(), true, $this->get_delete_content() );

		if ( is_wp_error( $result ) ) {
			return $this->failure(
				$result->get_error_message(),
				400,
				'automator_delete_loop_block_failed'
			);
		}

		return true;
	}

	/**
	 * Duplicate this loop block (and its whole subtree) within the recipe.
	 *
	 * @return int|WP_Error New root block id.
	 */
	protected function do_duplicate_block() {
		$service = new Recipe_Duplication_Service();
		$result  = $service->duplicate_entity( $this->get_recipe_id(), $this->get_post_id() );
		if ( is_wp_error( $result ) ) {
			return $this->failure(
				$result->get_error_message(),
				400,
				'automator_duplicate_loop_block_failed'
			);
		}

		return (int) $result->get_root_id();
	}
}
