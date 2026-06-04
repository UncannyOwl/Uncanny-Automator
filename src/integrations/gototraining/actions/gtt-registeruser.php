<?php

namespace Uncanny_Automator\Integrations\Gototraining;

use Uncanny_Automator\Recipe\App_Action;

/**
 * Class GTT_REGISTERUSER
 *
 * @property Gototraining_App_Helpers $helpers
 * @property Gototraining_Api_Caller $api
 *
 * @package Uncanny_Automator
 */
class GTT_REGISTERUSER extends App_Action {

	/**
	 * Action code constant.
	 *
	 * @var string
	 */
	const ACTION_CODE = 'GTTREGISTERUSER';

	/**
	 * Action meta constant.
	 *
	 * @var string
	 */
	const ACTION_META = 'GTTTRAINING';

	/**
	 * Setup the action.
	 *
	 * @return void
	 */
	protected function setup_action() {
		$this->set_integration( 'GTT' );
		$this->set_action_code( self::ACTION_CODE );
		$this->set_action_meta( self::ACTION_META );
		$this->set_requires_user( true );
		$this->set_is_pro( false );

		$this->set_sentence(
			sprintf(
				// translators: %s: Training session name
				esc_html_x( 'Add the user to {{a training session:%s}}', 'GoToTraining', 'uncanny-automator' ),
				$this->get_action_meta()
			)
		);

		$this->set_readable_sentence( esc_html_x( 'Add the user to {{a training session}}', 'GoToTraining', 'uncanny-automator' ) );

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
	 * Define the action tokens.
	 *
	 * @return array
	 */
	public function define_tokens() {
		return array(
			'JOIN_URL'         => array(
				'name' => esc_html_x( 'Join URL', 'GoToTraining', 'uncanny-automator' ),
				'type' => 'url',
			),
			'CONFIRMATION_URL' => array(
				'name' => esc_html_x( 'Confirmation URL', 'GoToTraining', 'uncanny-automator' ),
				'type' => 'url',
			),
			'REGISTRANT_KEY'   => array(
				'name' => esc_html_x( 'Registrant key', 'GoToTraining', 'uncanny-automator' ),
				'type' => 'text',
			),
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

		$registration = $this->api->register_user_to_training( $user_id, $training_key, $action_data );

		$this->hydrate_tokens(
			array(
				'JOIN_URL'         => $registration['joinUrl'] ?? '',
				'CONFIRMATION_URL' => $registration['confirmationUrl'] ?? '',
				'REGISTRANT_KEY'   => $registration['registrantKey'] ?? '',
			)
		);

		return true;
	}
}
