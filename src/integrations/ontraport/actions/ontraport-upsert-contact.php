<?php

namespace Uncanny_Automator\Integrations\Ontraport;

/**
 * Class Ontraport_Upsert_Contact ( deprecated )
 *
 * @package Uncanny_Automator
 *
 * @property Ontraport_App_Helpers $helpers
 * @property Ontraport_Api_Caller $api
 */
class Ontraport_Upsert_Contact extends \Uncanny_Automator\Recipe\App_Action {

	use Ontraport_Contact_Fields_Trait;

	/**
	 * Spins up new action inside "ONTRAPORT" integration.
	 *
	 * @return void
	 */
	public function setup_action() {
		$this->set_integration( 'ONTRAPORT' );
		$this->set_action_code( 'ONTRAPORT_UPSERT_CONTACT_CODE' );
		$this->set_action_meta( 'ONTRAPORT_UPSERT_CONTACT_META' );
		$this->set_is_pro( false );
		$this->set_is_deprecated( true );
		$this->set_support_link( Automator()->get_author_support_link( $this->action_code, 'knowledge-base/ontraport/' ) );
		$this->set_requires_user( false );
		$this->set_background_processing( true );
		$this->set_readable_sentence( esc_attr_x( 'Create or update {{a contact}}', 'Ontraport', 'uncanny-automator' ) );
		$this->set_sentence(
			sprintf(
				// translators: %1$s: Email address
				esc_attr_x( 'Create or update {{a contact:%1$s}}', 'Ontraport', 'uncanny-automator' ),
				$this->get_action_meta()
			)
		);
	}

	/**
	 * Define options.
	 *
	 * @return array
	 */
	public function options() {

		$fields   = array();
		$fields[] = $this->helpers->get_email_field( $this->get_action_meta() );
		$fields   = array_merge( $fields, $this->get_contact_field_options() );
		$fields[] = $this->get_status_field( false );

		return apply_filters( 'automator_ontraport_upsert_fields', $fields, $this );
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

		$email = $this->helpers->validate_email( $this->get_parsed_meta_value( $this->get_action_meta(), '' ) );

		$fields = array( 'email' => $email );

		foreach ( $this->get_contact_fields() as $field ) {
			$fields[ $field['api_key'] ] = $this->get_parsed_meta_value( $field['option_code'], '' );
		}

		$fields['status'] = $this->get_parsed_meta_value( 'STATUS', '' );

		$body = array(
			'fields' => wp_json_encode( $fields ),
		);

		$body = apply_filters(
			'uncanny_automator_ontraport_fields',
			$body,
			array(
				$action_data,
				$recipe_id,
				$args,
				$parsed,
			)
		);

		$this->api->send_request( 'contact_upsert', $body, $action_data );

		return true;
	}
}
