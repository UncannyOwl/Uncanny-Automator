<?php
namespace Uncanny_Automator\Integrations\Advanced;

use Uncanny_Automator\Tokens\Universal_Token;

class Recipe_Run_Total_Token extends Universal_Token {

	/**
	 * setup
	 *
	 * @return void
	 */
	public function setup() {
		$this->integration   = 'ADVANCED';
		$this->id            = 'RECIPE_RUN_TOTAL';
		$this->name          = esc_attr_x( 'Recipe run # (total)', 'Token', 'uncanny-automator' );
		$this->requires_user = false;
		$this->type          = 'int';
		$this->cacheable     = true;
	}

	/**
	 * parse_integration_token
	 */
	public function parse_integration_token( $return, $pieces, $recipe_id, $trigger_data, $user_id, $replace_args ) {

		return Automator()->get->recipe_completed_times( $recipe_id );
	}
}
