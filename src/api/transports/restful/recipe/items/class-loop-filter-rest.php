<?php
declare(strict_types=1);

namespace Uncanny_Automator\Api\Transports\Restful\Recipe\Items;

use WP_REST_Request;
use WP_Error;
//use Uncanny_Automator\Api\Services\Loop_Filter\Services\Loop_Filter_CRUD_Service;
//use Uncanny_Automator\Api\Database\Database;

/**
 * Loop filter REST handler.
 *
 * @package Uncanny_Automator\Api\Transports\Restful\Recipe\Items
 */
class Loop_Filter_Rest extends Recipe_Item_Rest {

	/**
	 * Add an action to recipe.
	 *
	 * Mimics the legacy add() behavior from Recipe_Post_Rest_Api by creating
	 * loop filters in draft state without requiring full configuration.
	 *
	 * Bypasses service layer to avoid validation - this is infrastructure CRUD.
	 *
	 * @return int|WP_Error
	 */
	protected function do_add_item() {

		return $this->failure(
			'Loop filter addition is not supported yet.',
			400,
			'automator_add_loop_filter_to_recipe_not_supported'
		);

		/*
		$service  = Loop_Filter_CRUD_Service::instance();
		$result   = $service->add_to_recipe(
			$this->get_recipe_id(),
			$this->get_item_code(),
			array(),
			array(),
			true
		);

		if ( is_wp_error( $result ) ) {
			return $this->failure(
				$result->get_error_message(),
				400,
				'automator_add_loop_filter_to_recipe_failed'
			);
		}

		$loop_filter_id = $result['loop_filter_id'];

		return (int) $loop_filter_id;
		*/
	}

	/**
	 * Update action config.
	 *
	 * @return bool|WP_Error
	 */
	protected function do_update_item() {

		return $this->failure(
			'Loop filter update is not supported yet.',
			400,
			'automator_update_loop_filter_to_recipe_not_supported'
		);

		/*
		$fields = $this->get_fields();
		// Transform fields from structured format to flat config
		$config = $this->transform_fields_to_config( $fields);

		$service = Loop_Filter_CRUD_Service::instance();
		$result  = $service->update_loop_filter(
			$this->get_item_id(),
			$config,
		);

		if ( is_wp_error( $result ) ) {
			return $this->failure(
				$result->get_error_message(),
				400,
				'automator_update_loop_filter_failed'
			);
		}

		return true;
		*/
	}

	/**
	 * Delete action from recipe.
	 *
	 * @return bool|WP_Error
	 */
	protected function do_delete_item() {

		return $this->failure(
			'Loop filter deletion is not supported yet.',
			400,
			'automator_delete_loop_filter_to_recipe_not_supported'
		);

		/*

		$service = Loop_Filter_CRUD_Service::instance();
		$result  = $service->delete_loop_filter( $this->get_item_id(), true );

		if ( is_wp_error( $result ) ) {
			return $this->failure(
				$result->get_error_message(),
				400,
				'automator_delete_loop_filter_failed'
			);
		}

		// Set item post type for legacy action response.
		$this->set_item_post_type( Database::get_loop_filter_store()->get_post_type() );

		return true;
		*/
	}
}
