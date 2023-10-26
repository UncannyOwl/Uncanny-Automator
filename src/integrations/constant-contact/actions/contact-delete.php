<?php
namespace Uncanny_Automator\Integrations\Constant_Contact;

use Exception;

/**
 * Class Uncanny_Automator\Integrations\Constant_Contact\CONTACT_DELETE
 *
 * @package Uncanny_Automator
 */
class CONTACT_DELETE extends \Uncanny_Automator\Recipe\Action {

	/**
	 * Define and register the action by pushing it into the Automator object.
	 *
	 * @return void
	 */
	public function setup_action() {

		$this->helpers = array_shift( $this->dependencies );

		$this->set_integration( 'CONSTANT_CONTACT' );
		$this->set_action_code( 'CONTACT_DELETE' );
		$this->set_action_meta( 'CONTACT_DELETE_META' );
		$this->set_is_pro( false );
		$this->set_support_link( Automator()->get_author_support_link( $this->action_code, 'knowledge-base/constant-contact/' ) );
		$this->set_requires_user( false );
		$this->set_sentence(
			sprintf(
				/* translators: Action sentence */
				esc_attr_x(
					'Delete {{a contact:%1$s}}',
					'Constant Contact',
					'uncanny-automator'
				),
				$this->get_action_meta()
			)
		);

		$this->set_readable_sentence( esc_attr_x( 'Delete {{a contact}}', 'Constant Contact', 'uncanny-automator' ) );

	}

	/**
	 * Define options.
	 *
	 * @return array
	 */
	public function options() {

		return array(
			array(
				'option_code' => $this->get_action_meta(),
				'label'       => _x( 'Email', 'Constant Contact', 'uncanny-automator' ),
				'input_type'  => 'email',
				'required'    => true,
			),
		);
	}

	/**
	 * Process the action.
	 *
	 * @param int   $user_id
	 * @param array $action_data
	 * @param int   $recipe_id
	 * @param array $args
	 * @param array $parsed
	 *
	 * @return bool
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		$helpers     = $this->helpers;
		$credentials = $helpers->get_credentials();
		$email       = isset( $parsed[ $this->get_action_meta() ] ) ? sanitize_text_field( $parsed[ $this->get_action_meta() ] ) : '';
		$list        = isset( $parsed['LIST'] ) ? sanitize_text_field( $parsed['LIST'] ) : '';

		if ( false === filter_var( $email, FILTER_VALIDATE_EMAIL ) ) {
			throw new \Exception( 'Invalid email address: ' . $email, 400 );
		}

		$body = array(
			'action'        => 'contact_delete',
			'access_token'  => $credentials['access_token'],
			'email_address' => $email,
		);

		$helpers->api_request( $body, $action_data );

	}

}
