<?php
/**
 * Class Name
 *
 * Short description
 *
 * @class   Actions
 * @since   3.0
 * @version 3.0
 * @package Uncanny_Automator
 * @author  Saad S.
 */


namespace Uncanny_Automator\Recipe;

/**
 * Trait Actions
 * @package Uncanny_Automator\Recipe
 */
trait Actions {
	/**
	 * Action Setup. This trait handles action definitions.
	 */
	use Action_Setup;

	/**
	 * Action Conditions. This trait handles action conditions. This is where action conditionally executes. For
	 * example, a form ID has to be matched, a specific field needs to have a certain value.
	 */
	use Action_Conditions;

	/**
	 * Action Token Parser. This trait handles action meta's parser.
	 */
	use Action_Parser;

	/**
	 * Action Helpers. This trait repeated action helpers.
	 */
	use Action_Helpers;

	/**
	 * Action Process. This trait handles action execution.
	 */
	use Action_Process;

	/**
	 * @param int $user_id
	 * @param array $action_data
	 * @param int $recipe_id
	 * @param array $args
	 *
	 */
	public function do_action( int $user_id, array $action_data, int $recipe_id, array $args ) {

		$maybe_parsed                = $this->maybe_parse_tokens( $user_id, $action_data, $recipe_id, $args );
		$action_data['maybe_parsed'] = $maybe_parsed;

		$this->process_action( $user_id, $action_data, $recipe_id, $args, $maybe_parsed );
	}

	/**
	 * @param int $user_id
	 * @param array $action_data
	 * @param int $recipe_id
	 * @param array $args
	 * @param $parsed
	 *
	 * @return mixed
	 */
	abstract protected function process_action( int $user_id, array $action_data, int $recipe_id, array $args, $parsed );
}
