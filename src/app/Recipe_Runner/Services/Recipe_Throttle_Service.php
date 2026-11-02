<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Recipe_Runner\Services;

use Uncanny_Automator\Services\Recipe\Process\Throttler;
use Uncanny_Automator\App\Events\Dispatcher;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Settings\Services\Recipe_Settings_Service;

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

		$throttle = Recipe_Settings_Service::instance()->get_throttle( $recipe_id );

		// A disabled/unset throttle is never throttled. Short-circuit before the
		// legacy Throttler so the common disabled case doesn't round-trip through
		// to_throttler_input() + Throttler (which throws "Invalid settings" on the
		// empty input and logs a spurious error on every fire).
		if ( ! ( $throttle['enabled'] ?? false ) ) {
			return false;
		}

		$data = $this->to_throttler_input( $throttle );

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

	/**
	 * Map the service's clean throttle domain shape to the legacy on-disk
	 * format the \Automator Throttler constructor expects.
	 *
	 * Precondition: the throttle is enabled — `is_throttled()` short-circuits on a
	 * disabled one before calling this. Disappears when the legacy Throttler does.
	 *
	 * @param array{enabled: bool, duration: int|null, unit: string|null, scope: string|null} $throttle
	 * @return array
	 */
	private function to_throttler_input( array $throttle ): array {
		$input = array(
			'ENABLED' => array( 'value' => true ),
			'NUMBER'  => array( 'value' => (string) $throttle['duration'] ),
			'UNIT'    => array( 'value' => (string) $throttle['unit'] ),
		);
		if ( ! empty( $throttle['scope'] ) ) {
			$input['PER_RECIPE_OR_USER'] = array( 'value' => (string) $throttle['scope'] );
		}
		return $input;
	}
}
