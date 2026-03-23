<?php
declare(strict_types=1);

namespace Uncanny_Automator\Api\Transports\Restful\Recipe\Blocks;

use WP_Error;

/**
 * Loop Block REST handler.
 *
 * Handles CRUD operations for loop blocks.
 *
 * @package Uncanny_Automator\Api\Transports\Restful\Recipe\Blocks
 */
class Loop_Block_Rest extends Recipe_Block_Rest {

	/**
	 * Add a loop block to recipe.
	 *
	 * @return string|WP_Error Block ID on success.
	 */
	protected function do_add_block() {
		// TODO: Implement loop block add logic
		return $this->failure(
			'Loop block add operation not yet implemented.',
			501,
			'automator_not_implemented'
		);
	}

	/**
	 * Update loop block configuration.
	 *
	 * @return bool|WP_Error
	 */
	protected function do_update_block() {
		// TODO: Implement loop block update logic
		return $this->failure(
			'Loop block update operation not yet implemented.',
			501,
			'automator_not_implemented'
		);
	}

	/**
	 * Delete loop block from recipe.
	 *
	 * @return bool|WP_Error
	 */
	protected function do_delete_block() {
		// TODO: Implement loop block delete logic
		return $this->failure(
			'Loop block delete operation not yet implemented.',
			501,
			'automator_not_implemented'
		);
	}
}
