<?php

namespace Uncanny_Automator\Integrations\Gototraining;

use Uncanny_Automator\Recipe\App_Action;
use Exception;

/**
 * Class GTT_REGISTERATTENDEE
 *
 * Non-user variant of GTT_REGISTERUSER — registers a specific attendee
 * (email + name supplied as fields) rather than the recipe's WP user, so
 * it works in recipes with no user context (e.g. an anonymous form). The
 * registrant key is returned as a token for a later unregister action.
 *
 * @property Gototraining_App_Helpers $helpers
 * @property Gototraining_Api_Caller $api
 *
 * @package Uncanny_Automator
 */
class GTT_REGISTERATTENDEE extends App_Action {

	/**
	 * Setup the action.
	 *
	 * @return void
	 */
	protected function setup_action() {
		$this->set_integration( 'GTT' );
		$this->set_action_code( 'GTTREGISTERATTENDEE' );
		$this->set_action_meta( 'GTTTRAINING' );
		$this->set_requires_user( false );
		$this->set_is_pro( false );

		$this->set_sentence(
			sprintf(
				// translators: %1$s: Email address, %2$s: Training session name
				esc_html_x( 'Add {{an attendee:%1$s}} to {{a training session:%2$s}}', 'GoToTraining', 'uncanny-automator' ),
				'GTTEMAIL:' . $this->get_action_meta(),
				$this->get_action_meta()
			)
		);

		$this->set_readable_sentence( esc_html_x( 'Add {{an attendee}} to {{a training session}}', 'GoToTraining', 'uncanny-automator' ) );

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
			array(
				'option_code' => 'GTTEMAIL',
				'label'       => esc_attr_x( 'Email', 'GoToTraining', 'uncanny-automator' ),
				'input_type'  => 'email',
				'required'    => true,
			),
			array(
				'option_code' => 'GTTFIRSTNAME',
				'label'       => esc_attr_x( 'First name', 'GoToTraining', 'uncanny-automator' ),
				'input_type'  => 'text',
				'required'    => false,
			),
			array(
				'option_code' => 'GTTLASTNAME',
				'label'       => esc_attr_x( 'Last name', 'GoToTraining', 'uncanny-automator' ),
				'input_type'  => 'text',
				'required'    => false,
			),
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
	 * @throws Exception If the email is invalid.
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		$training_key = $this->helpers->get_training_from_parsed( $parsed, $this->get_action_meta() );

		$email = sanitize_email( $parsed['GTTEMAIL'] ?? '' );
		if ( empty( $email ) || ! is_email( $email ) ) {
			throw new Exception( esc_html_x( 'A valid email address is required.', 'GoToTraining', 'uncanny-automator' ) );
		}

		$first_name = sanitize_text_field( $parsed['GTTFIRSTNAME'] ?? '' );
		$last_name  = sanitize_text_field( $parsed['GTTLASTNAME'] ?? '' );

		$registration = $this->api->register_attendee( $training_key, $first_name, $last_name, $email, $action_data );

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
