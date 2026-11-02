<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Restful\Recipe\Blocks;

use WP_Error;
use Uncanny_Automator\App\Recipe_Builder\Block\Enums\Block_Type;
use Uncanny_Automator\App\Recipe_Builder\Block\Services\Block_Crud_Service;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Portability\Recipe_Duplication_Service;

/**
 * Filter Block REST handler.
 *
 * Handles CRUD operations for filter blocks stored as uo-block posts.
 *
 * @package Uncanny_Automator\App\Transports\Restful\Recipe\Blocks
 */
class Filter_Block_Rest extends Recipe_Block_Rest {

	/**
	 * Add a filter block to recipe.
	 *
	 * Creates a new uo-block post with block_type 'filter'.
	 *
	 * @return int|WP_Error Block post ID on success.
	 */
	protected function do_add_block() {
		$service = Block_Crud_Service::instance();
		$result  = $service->add_block(
			$this->get_recipe_id(),
			Block_Type::FILTER,
			(int) $this->get_parent_id(),
			array(),
			$this->get_path_code()
		);

		if ( is_wp_error( $result ) ) {
			return $this->add_block_failure( $result, 'automator_add_filter_block_failed' );
		}

		return $result['id'];
	}

	/**
	 * Update filter block configuration.
	 *
	 * Updates block fields (e.g. mode, priority).
	 *
	 * @return bool|WP_Error
	 */
	protected function do_update_block() {
		$service = Block_Crud_Service::instance();
		$result  = $service->update_block(
			$this->get_post_id(),
			array( 'fields' => $this->get_fields() )
		);

		if ( is_wp_error( $result ) ) {
			return $this->failure(
				$result->get_error_message(),
				400,
				'automator_update_filter_block_failed'
			);
		}

		return true;
	}

	/**
	 * Delete filter block from recipe.
	 *
	 * Removes the filter block post and its child conditions.
	 *
	 * @return bool|WP_Error
	 */
	protected function do_delete_block() {
		$service = Block_Crud_Service::instance();
		$result  = $service->delete_block( $this->get_post_id(), $this->get_delete_content() );

		if ( is_wp_error( $result ) ) {
			return $this->failure(
				$result->get_error_message(),
				400,
				'automator_delete_filter_block_failed'
			);
		}

		return true;
	}

	/**
	 * Duplicate this filter block (and its whole subtree) within the recipe.
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
				'automator_duplicate_filter_block_failed'
			);
		}

		return (int) $result->get_root_id();
	}
}
