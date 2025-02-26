<?php
namespace Uncanny_Automator\Integrations\Advanced;

use Uncanny_Automator\Tokens\Universal_Token;

class Recipe_Run_Token extends Universal_Token {

	/**
	 * setup
	 *
	 * @return void
	 */
	public function setup() {
		$this->integration   = 'ADVANCED';
		$this->id            = 'RECIPE_RUN';
		$this->name          = esc_attr_x( 'Recipe run # (user)', 'Token', 'uncanny-automator' );
		$this->requires_user = false;
		$this->type          = 'int';
		$this->cacheable     = true;
	}

	public function parse_integration_token( $return, $pieces, $recipe_id, $trigger_data, $user_id, $replace_args ) {

		if ( ! empty( $replace_args['run_number'] ) ) {
			$return = $replace_args['run_number'];
		}

		return $return;
	}
}
