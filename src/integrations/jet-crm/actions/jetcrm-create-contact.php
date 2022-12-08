<?php

namespace Uncanny_Automator;

/**
 * Class JETCRM_CREATE_CONTACT
 *
 * @package Uncanny_Automator
 */
class JETCRM_CREATE_CONTACT {

	use Recipe\Actions;
	use Recipe\Action_Tokens;

	/**
	 * Set up Automator action constructor.
	 */
	public function __construct() {
		$this->setup_action();
		$this->set_helpers( new Jet_Crm_Helpers() );
	}

	/**
	 * Define and register the action by pushing it into the Automator object
	 */
	protected function setup_action() {
		$this->set_integration( 'JETCRM' );
		$this->set_action_code( 'JETCRM_CREATE_CONTACT' );
		$this->set_action_meta( 'JETCRM_CONTACT' );
		$this->set_requires_user( false );
		/* translators: Action - JetPack CRM */
		$this->set_sentence( sprintf( esc_attr__( 'Create {{a contact:%1$s}}', 'uncanny-automator' ), $this->get_action_meta() ) );
		/* translators: Action - JetPack CRM */
		$this->set_readable_sentence( esc_attr__( 'Create {{a contact}}', 'uncanny-automator' ) );
		$this->set_options_callback( array( $this, 'load_options' ) );
		$this->set_action_tokens(
			array(
				'CUSTOMER_ID' => array(
					'name' => __( 'Customer ID', 'uncanny-automator' ),
					'type' => 'int',
				),
			),
			$this->get_action_code()
		);

		$this->register_action();
	}

	/**
	 * load_options
	 *
	 * @return array
	 */
	public function load_options() {

		$fields_meta_label = $this->get_helpers()->get_contact_fields();
		$fields            = array(
			$this->get_helpers()->contact_statuses( 'status' ),
		);
		foreach ( $fields_meta_label as $meta => $label ) {
			$fields[] = Automator()->helpers->recipe->field->text(
				array(
					'option_code' => $meta,
					'label'       => $label,
					'required'    => false,
				)
			);
		}
		$fields[] = $this->get_helpers()->get_all_jetpack_tags( 'tags', false, array(), ZBS_TYPE_CONTACT, true );
		$fields[] = $this->get_helpers()->get_all_jetpack_companies( 'companies', false, false, true );

		return Automator()->utilities->keep_order_of_options(
			array(
				'options_group' => array(
					$this->get_action_meta() => $fields,
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
		$fields_meta = $this->get_helpers()->get_contact_fields();
		foreach ( $fields_meta as $meta => $label ) {
			$contact_details['data'][ $meta ] = isset( $parsed[ $meta ] ) ? sanitize_text_field( $parsed[ $meta ] ) : '';
		}

		$contact_details['data']['status']    = isset( $parsed['status'] ) ? sanitize_text_field( $parsed['status'] ) : '';
		$tags                                 = isset( $parsed['tags'] ) ? sanitize_text_field( $parsed['tags'] ) : '';
		$companies                            = isset( $parsed['companies'] ) ? sanitize_text_field( $parsed['companies'] ) : '';
		$contact_details['data']['tags']      = array( $tags );
		$contact_details['data']['companies'] = array( $companies );

		if ( empty( $contact_details ) ) {
			return;
		}

		global $zbs;
		$contact_id = $zbs->DAL->contacts->addUpdateContact( $contact_details );
		$this->hydrate_tokens(
			array(
				'CUSTOMER_ID' => $contact_id,
			)
		);
		Automator()->complete->action( $user_id, $action_data, $recipe_id );
	}
}
