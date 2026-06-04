<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Recipe_Runner\Services;

use Uncanny_Automator\App\Recipe_Runner\Value_Objects\User_Resolution;

/**
 * Resolves a WordPress user for an anonymous ("Everyone") recipe run.
 *
 * Pro implements this to create or find users before actions execute.
 *
 * @package Uncanny_Automator\App\Recipe_Runner\Services
 * @since   7.2
 */
interface User_Resolver {

	/**
	 * Resolve a WordPress user for an anonymous recipe run.
	 *
	 * @param array $process Filter context from automator_maybe_continue_recipe_process.
	 *
	 * @return User_Resolution
	 */
	public function resolve( array $process ): User_Resolution;
}
