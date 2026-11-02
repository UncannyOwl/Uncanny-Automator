<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Bridge;

/**
 * Default implementation of {@see Recipe_Object_Bridge}.
 *
 * Only place in `src/app/` permitted to call
 * `Automator()->get->recipes_from_trigger_code()`.
 *
 * @since 7.4.0
 */
final class Automator_Recipe_Object_Bridge implements Recipe_Object_Bridge {

	/**
	 * @inheritDoc
	 */
	public function get_recipes_for_trigger_code( string $trigger_code, ?int $recipe_id = null ): array {
		$result = \Automator()->get->recipes_from_trigger_code( $trigger_code, $recipe_id );

		return is_array( $result ) ? $result : array();
	}
}
