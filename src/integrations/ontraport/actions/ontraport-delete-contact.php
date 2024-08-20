<?php

namespace Uncanny_Automator\Integrations\Ontraport;

use Exception;

/**
 * Class Ontraport_Delete_Contact
 *
 * @package Uncanny_Automator
 */
class Ontraport_Delete_Contact extends \Uncanny_Automator\Recipe\Action {

	public $prefix = 'ONTRAPORT_DELETE_CONTACT';

	/**
	 * Spins up new action inside "ONTRAPORT" integration.
	 *
	 * @return void
	 */
	public function setup_action() {

		$this->helpers = array_shift( $this->dependencies );

		$this->set_integration( 'ONTRAPORT' );
		$this->set_action_code( $this->prefix . '_CODE' );
		$this->set_action_meta( $this->prefix . '_META' );
		$this->set_is_pro( false );
		$this->set_support_link( Automator()->get_author_support_link( $this->action_code, 'knowledge-base/ontraport/' ) );
		$this->set_requires_user( false );

		$sentence = sprintf(
			/* translators: Action sentence */
			esc_attr_x( 'Delete {{a contact:%1$s}}', 'Ontraport', 'uncanny-automator' ),
			$this->get_action_meta()
		);

		$this->set_sentence( $sentence );
		$this->set_readable_sentence( esc_attr_x( 'Delete {{a contact}}', 'Ontraport', 'uncanny-automator' ) );
		$this->set_background_processing( true );

	}

	/**
	 * Define options.
	 *
	 * @return array
	 */
	public function options() {

		$email = array(
			'option_code' => $this->get_action_meta(),
			'label'       => _x( 'Email', 'Ontraport', 'uncanny-automator' ),
			'input_type'  => 'text',
			'required'    => true,
		);

		return array( $email );

	}

	/**
	 * Process the action.
	 *
	 * @param int $user_id
	 * @param array $action_data
	 * @param int $recipe_id
	 * @param array $args
	 * @param array $parsed
	 *
	 * @return bool
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		$email = $this->get_parsed_meta_value( $this->get_action_meta(), '' );

		if ( ! filter_var( $email, FILTER_VALIDATE_EMAIL ) ) {
			throw new Exception( "Invalid email provided: {$email}", 400 );
		}

		$body = array(
			'contact_email' => $email,
		);

		$this->helpers->api_request( 'contact_delete', $body, $action_data );

	}

}
