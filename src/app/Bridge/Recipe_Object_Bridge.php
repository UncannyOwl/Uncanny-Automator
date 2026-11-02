<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Bridge;

/**
 * Anti-corruption boundary for legacy recipe-object lookups.
 *
 * Wraps `Automator()->get->recipes_from_trigger_code()`.
 * Consumers depend on this contract instead of the global so the legacy
 * recipe-object surface can be deprecated incrementally.
 *
 * @since 7.4.0
 */
interface Recipe_Object_Bridge {

	/**
	 * Fetch every recipe row that consumes a given trigger code.
	 *
	 * Wraps `Automator()->get->recipes_from_trigger_code( $trigger_code, $recipe_id )`.
	 *
	 * @param string   $trigger_code Trigger code.
	 * @param int|null $recipe_id    Optional recipe filter.
	 * @return array Recipe rows.
	 */
	public function get_recipes_for_trigger_code( string $trigger_code, ?int $recipe_id = null ): array;
}
