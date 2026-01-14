<?php
declare(strict_types=1);

namespace Uncanny_Automator\Api\Transports\Restful\Recipe\Blocks;

use Uncanny_Automator\Api\Transports\Restful\Recipe\Traits\Recipe_Entity;
use Uncanny_Automator\Api\Transports\Restful\Recipe\Blocks\Traits\Recipe_Block_Properties;
use Uncanny_Automator\Api\Transports\Restful\Utilities\Traits\Rest_Responses;

use WP_REST_Response;
use WP_Error;

/**
 * Base class for all recipe block REST mutation handlers.
 *
 * Concrete child classes MUST implement all required operations.
 */
abstract class Recipe_Block_Rest {

	use Recipe_Entity;
	use Recipe_Block_Properties;
	use Rest_Responses;

	/**
	 * Add a block to recipe.
	 *
	 * @return string|WP_Error Block ID on success.
	 */
	abstract protected function do_add_block();

	/**
	 * Update a block in recipe.
	 *
	 * @return bool|WP_Error
	 */
	abstract protected function do_update_block();

	/**
	 * Delete a block from recipe.
	 *
	 * @return bool|WP_Error
	 */
	abstract protected function do_delete_block();

	/**
	 * Handle add operation.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function add() {
		// Set required properties for adding a block.
		$this->set_parent_id( $this->get_request()->get_param( 'parent_id' ) ?? null );

		if ( empty( $this->get_parent_id() ) ) {
			return $this->failure( 'Missing parent_id.', 400, 'automator_missing_parent_id' );
		}

		$block_id = $this->do_add_block();
		if ( is_wp_error( $block_id ) ) {
			return $block_id;
		}

		return $this->success(
			array(
				'block_id'       => $block_id,
				'recipes_object' => $this->get_recipe_object_legacy(),
				'recipe'         => $this->get_recipe_object(),
			)
		);
	}

	/**
	 * Handle update operation.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function update() {
		// Validate block ID
		$validation = $this->validate_block_id();
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		$this->set_fields( $this->get_request()->get_param( 'fields' ) ?? array() );
		if ( empty( $this->get_fields() ) ) {
			return $this->failure( 'Missing fields.', 400, 'automator_missing_fields' );
		}

		$result = $this->do_update_block();
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return $this->success(
			array(
				'recipes_object' => $this->get_recipe_object_legacy(),
				'recipe'         => $this->get_recipe_object(),
			)
		);
	}

	/**
	 * Handle delete operation.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete() {
		// Validate block ID
		$validation = $this->validate_block_id();
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		// Delete block
		$result = $this->do_delete_block();
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return $this->success(
			array(
				'recipes_object' => $this->get_recipe_object_legacy(),
				'recipe'         => $this->get_recipe_object(),
			)
		);
	}

	/**
	 * Validate block ID.
	 *
	 * @return WP_Error|null Returns WP_Error on validation failure, null on success.
	 */
	private function validate_block_id() {
		$this->set_block_id( $this->get_request()->get_param( 'block_id' ) ?? null );
		if ( empty( $this->get_block_id() ) ) {
			return $this->failure( 'Missing block_id.', 400, 'automator_missing_block_id' );
		}
		return null;
	}
}
