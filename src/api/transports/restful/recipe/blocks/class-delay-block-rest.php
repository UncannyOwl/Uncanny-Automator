<?php
declare(strict_types=1);

namespace Uncanny_Automator\Api\Transports\Restful\Recipe\Blocks;

use WP_Error;

/**
 * Delay Block REST handler.
 *
 * Handles CRUD operations for delay blocks.
 *
 * @package Uncanny_Automator\Api\Transports\Restful\Recipe\Blocks
 */
class Delay_Block_Rest extends Recipe_Block_Rest {

	/**
	 * Add a delay block to recipe.
	 *
	 * @return string|WP_Error Block ID on success.
	 */
	protected function do_add_block() {
		// TODO: Implement delay block add logic
		return $this->failure(
			'Delay block add operation not yet implemented.',
			501,
			'automator_not_implemented'
		);
	}

	/**
	 * Update delay block configuration.
	 *
	 * @return bool|WP_Error
	 */
	protected function do_update_block() {
		// TODO: Implement delay block update logic
		return $this->failure(
			'Delay block update operation not yet implemented.',
			501,
			'automator_not_implemented'
		);
	}

	/**
	 * Delete delay block from recipe.
	 *
	 * @return bool|WP_Error
	 */
	protected function do_delete_block() {
		// TODO: Implement delay block delete logic
		return $this->failure(
			'Delay block delete operation not yet implemented.',
			501,
			'automator_not_implemented'
		);
	}
}
