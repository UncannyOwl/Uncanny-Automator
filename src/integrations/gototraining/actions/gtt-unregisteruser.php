<?php

namespace Uncanny_Automator\Integrations\Gototraining;

use Uncanny_Automator\Recipe\App_Action;

/**
 * Class GTT_UNREGISTERUSER
 *
 * @property Gototraining_App_Helpers $helpers
 * @property Gototraining_Api_Caller $api
 *
 * @package Uncanny_Automator
 */
class GTT_UNREGISTERUSER extends App_Action {

	/**
	 * Setup the action.
	 *
	 * @return void
	 */
	protected function setup_action() {
		$this->set_integration( 'GTT' );
		$this->set_action_code( 'GTTUNREGISTERUSER' );
		$this->set_action_meta( 'GTTTRAINING' );
		$this->set_requires_user( true );
		$this->set_is_pro( false );

		$this->set_sentence(
			sprintf(
				// translators: %s: Training session name
				esc_html_x( 'Remove the user from {{a training session:%s}}', 'GoToTraining', 'uncanny-automator' ),
				$this->get_action_meta()
			)
		);

		$this->set_readable_sentence( esc_html_x( 'Remove the user from {{a training session}}', 'GoToTraining', 'uncanny-automator' ) );

		$this->set_background_processing( true );
	}

	/**
	 * Define action options.
	 *
	 * @return array
	 */
	public function options() {
		return array(
			$this->helpers->get_training_options_config( $this->get_action_meta() ),
		);
	}

	/**
	 * Process the action.
	 *
	 * @param int   $user_id     User ID.
	 * @param array $action_data Action data.
	 * @param int   $recipe_id   Recipe ID.
	 * @param array $args        Action arguments.
	 * @param array $parsed      Parsed action data.
	 *
	 * @return bool
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		$training_key = $this->helpers->get_training_from_parsed( $parsed, $this->get_action_meta() );

		$this->api->unregister_user_from_training( $user_id, $training_key, $action_data );

		return true;
	}
}
