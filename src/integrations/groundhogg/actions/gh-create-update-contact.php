<?php

namespace Uncanny_Automator;

use Groundhogg\Contact;

/**
 * Class GH_CREATE_UPDATE_CONTACT
 *
 * @package Uncanny_Automator
 */
class GH_CREATE_UPDATE_CONTACT {

	use Recipe\Actions;

	/**
	 * Set up Automator action constructor.
	 */
	public function __construct() {
		$this->setup_action();
	}

	/**
	 * Define and register the action by pushing it into the Automator object
	 */
	protected function setup_action() {
		$this->set_integration( 'GH' );
		$this->set_action_code( 'GH_CREATE_UPDATE_CONTACT' );
		$this->set_action_meta( 'GH_CONTACT' );
		$this->set_requires_user( false );
		/* translators: Action - Groundhogg */
		$this->set_sentence( sprintf( esc_attr_x( 'Create or update {{a contact:%1$s}}', 'Groundhogg', 'uncanny-automator' ), $this->get_action_meta() ) );
		/* translators: Action - Groundhogg */
		$this->set_readable_sentence( esc_attr_x( 'Create or update {{a contact}}', 'Groundhogg', 'uncanny-automator' ) );
		$this->set_options_callback( array( $this, 'load_options' ) );
		$this->register_action();
	}

	/**
	 * load_options
	 *
	 * @return array
	 */
	public function load_options() {

		return Automator()->utilities->keep_order_of_options(
			array(
				'options_group' => array(
					$this->get_action_meta() => array(
						Automator()->helpers->recipe->field->text(
							array(
								'option_code' => $this->get_action_meta(),
								'input_type'  => 'email',
								'label'       => esc_attr_x( 'Email', 'Groundhogg', 'uncanny-automator' ),
							)
						),
						Automator()->helpers->recipe->field->text(
							array(
								'option_code' => 'first_name',
								'label'       => esc_attr_x( 'First name', 'Groundhogg', 'uncanny-automator' ),
								'required'    => false,
							)
						),
						Automator()->helpers->recipe->field->text(
							array(
								'option_code' => 'last_name',
								'label'       => esc_attr_x( 'Last name', 'Groundhogg', 'uncanny-automator' ),
								'required'    => false,
							)
						),
						Automator()->helpers->recipe->field->text(
							array(
								'option_code' => 'mobile_phone',
								'label'       => esc_attr_x( 'Mobile phone', 'Groundhogg', 'uncanny-automator' ),
								'required'    => false,
							)
						),
						Automator()->helpers->recipe->field->text(
							array(
								'option_code' => 'primary_phone',
								'label'       => esc_attr_x( 'Primary phone', 'Groundhogg', 'uncanny-automator' ),
								'required'    => false,
							)
						),
						Automator()->helpers->recipe->field->select(
							array(
								'option_code'           => 'optin_status',
								'label'                 => esc_attr_x( 'Opt-in status', 'Groundhogg', 'uncanny-automator' ),
								'required'              => false,
								'options'               => array(
									1 => __( 'Unconfirmed', 'uncanny-automator' ),
									2 => __( 'Confirmed', 'uncanny-automator' ),
									3 => __( 'Unsubscribed', 'uncanny-automator' ),
									4 => __( 'Subscribed Weekly', 'uncanny-automator' ),
									5 => __( 'Subscribed Monthly', 'uncanny-automator' ),
									6 => __( 'Bounced', 'uncanny-automator' ),
									7 => __( 'Spam', 'uncanny-automator' ),
									8 => __( 'Complained', 'uncanny-automator' ),
								),
								'supports_custom_value' => false,
							)
						),
					),
				),
			)
		);

	}

	/**
	 * Process the action.
	 *
	 * @param $user_id
	 * @param $action_data
	 * @param $recipe_id
	 * @param $args
	 * @param $parsed
	 *
	 * @return void.
	 * @throws \Exception
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {
		$contact_email = isset( $parsed[ $this->get_action_meta() ] ) ? sanitize_text_field( $parsed[ $this->get_action_meta() ] ) : '';
		$first_name    = isset( $parsed['first_name'] ) ? sanitize_text_field( $parsed['first_name'] ) : '';
		$last_name     = isset( $parsed['last_name'] ) ? sanitize_text_field( $parsed['last_name'] ) : '';
		$mobile_phone  = isset( $parsed['mobile_phone'] ) ? sanitize_text_field( $parsed['mobile_phone'] ) : '';
		$primary_phone = isset( $parsed['primary_phone'] ) ? sanitize_text_field( $parsed['primary_phone'] ) : '';
		$optin_status  = isset( $parsed['optin_status'] ) ? sanitize_text_field( $parsed['optin_status'] ) : '';

		if ( empty( $contact_email ) ) {
			$action_data['complete_with_errors'] = true;
			$action_data['do-nothing']           = true;
			Automator()->complete->action( $user_id, $action_data, $recipe_id, _x( 'Email is required.', 'Groundhogg', 'uncanny-automator' ) );

			return;
		}
		$contact_details = array(
			'email'         => $contact_email,
			'first_name'    => $first_name,
			'last_name'     => $last_name,
			'mobile_phone'  => $mobile_phone,
			'primary_phone' => $primary_phone,
			'optin_status'  => $optin_status,
		);

		$contact = new Contact( $contact_email );

		if ( true === $contact->exists() ) {
			$contact->update( $contact_details );
		} else {
			$contact->create( $contact_details );
		}

		Automator()->complete->action( $user_id, $action_data, $recipe_id );
	}
}
