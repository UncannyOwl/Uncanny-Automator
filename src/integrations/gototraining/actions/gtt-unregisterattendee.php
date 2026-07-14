<?php

namespace Uncanny_Automator\Integrations\Gototraining;

use Uncanny_Automator\Recipe\App_Action;
use Exception;

/**
 * Class GTT_UNREGISTERATTENDEE
 *
 * Non-user variant of GTT_UNREGISTERUSER — removes a registrant by the
 * registrant key supplied as a field (typically chained from the
 * "Add an attendee" action's Registrant key token), rather than reading
 * it from the recipe user's meta.
 *
 * @property Gototraining_App_Helpers $helpers
 * @property Gototraining_Api_Caller $api
 *
 * @package Uncanny_Automator
 */
class GTT_UNREGISTERATTENDEE extends App_Action {

	/**
	 * Setup the action.
	 *
	 * @return void
	 */
	protected function setup_action() {
		$this->set_integration( 'GTT' );
		$this->set_action_code( 'GTTUNREGISTERATTENDEE' );
		$this->set_action_meta( 'GTTTRAINING' );
		$this->set_requires_user( false );
		$this->set_is_pro( false );

		$this->set_sentence(
			sprintf(
				// translators: %1$s: Registrant key, %2$s: Training session name
				esc_html_x( 'Remove {{an attendee:%1$s}} from {{a training session:%2$s}}', 'GoToTraining', 'uncanny-automator' ),
				'GTTREGISTRANTKEY:' . $this->get_action_meta(),
				$this->get_action_meta()
			)
		);

		$this->set_readable_sentence( esc_html_x( 'Remove {{an attendee}} from {{a training session}}', 'GoToTraining', 'uncanny-automator' ) );

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
				'option_code' => 'GTTREGISTRANTKEY',
				'label'       => esc_attr_x( 'Registrant key', 'GoToTraining', 'uncanny-automator' ),
				'input_type'  => 'text',
				'required'    => true,
				'description' => esc_attr_x( 'The registrant key returned by the "Add an attendee to a training session" action.', 'GoToTraining', 'uncanny-automator' ),
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
	 * @throws Exception If the registrant key is missing.
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		$training_key   = $this->helpers->get_training_from_parsed( $parsed, $this->get_action_meta() );
		$registrant_key = sanitize_text_field( $parsed['GTTREGISTRANTKEY'] ?? '' );

		if ( empty( $registrant_key ) ) {
			throw new Exception( esc_html_x( 'Registrant key is required.', 'GoToTraining', 'uncanny-automator' ) );
		}

		$this->api->unregister_registrant( $training_key, $registrant_key, $action_data );

		return true;
	}
}
