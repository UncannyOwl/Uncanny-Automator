<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Recipe_Runner\Services;

use Uncanny_Automator\Services\Recipe\Process\Throttler;
use Uncanny_Automator\App\Events\Dispatcher;

/**
 * Recipe throttle checks for the recipe runner.
 *
 * Thin wrapper around the existing Throttler — decouples the recipe runner
 * from \Automator()->is_recipe_throttled().
 *
 * @package Uncanny_Automator\App\Recipe_Runner\Services
 * @since   7.2
 */
class Recipe_Throttle_Service {

	/**
	 * Check if a recipe is throttled for a user.
	 *
	 * @param int $recipe_id The recipe ID.
	 * @param int $user_id   The user ID.
	 *
	 * @return bool True if throttled (should NOT execute), false if clear.
	 */
	public function is_throttled( int $recipe_id, int $user_id ): bool {

		$data = (array) get_post_meta( $recipe_id, 'field_recipe_throttle', true );

		try {
			$throttler = new Throttler( $recipe_id, $data );

			$filter_args = array(
				'throttler' => $throttler,
				'data'      => $data,
				'user_id'   => $user_id,
				'recipe_id' => $recipe_id,
			);

			$can_execute = Dispatcher::filter(
				'automator_recipe_throttler_can_execute',
				$throttler->can_execute( $user_id ),
				$filter_args
			);

			if ( $can_execute ) {
				return false;
			}
		} catch ( \Exception $e ) {
			automator_log( 'Error creating throttler: ' . $e->getMessage(), 'error' );
			return false;
		}

		return true;
	}
}
