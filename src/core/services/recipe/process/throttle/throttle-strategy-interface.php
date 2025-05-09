<?php

namespace Uncanny_Automator\Services\Recipe\Process\Throttle;

interface Throttle_Strategy_Interface {
	/**
	 * Set the recipe ID
	 *
	 * @param int $recipe_id
	 */
	public function set_recipe_id( int $recipe_id );

	/**
	 * Check if recipe can be executed based on throttling rules
	 *
	 * @param int $user_id
	 * @return bool
	 */
	public function can_execute( $user_id = 0 );
}
