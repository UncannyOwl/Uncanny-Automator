<?php

namespace Uncanny_Automator\Integrations\HubSpot;

use Uncanny_Automator\Recipe\App_Action;

/**
 * Class HUBSPOT_REMOVECONTACTFROMLIST
 *
 * @package Uncanny_Automator
 *
 * @property HubSpot_App_Helpers $helpers
 * @property HubSpot_Api_Caller $api
 */
class HUBSPOT_REMOVECONTACTFROMLIST extends App_Action {

	/**
	 * Set up action
	 *
	 * @return void
	 */
	protected function setup_action() {
		$this->set_integration( 'HUBSPOT' );
		$this->set_action_code( 'HUBSPOTREMOVECONTACTFROMLIST' );
		$this->set_action_meta( 'HUBSPOTLIST' );
		$this->set_requires_user( false );
		$this->set_sentence(
			sprintf(
				// translators: %1$s: segment field name
				esc_html_x( 'Remove a HubSpot contact from {{a static segment:%1$s}}', 'HubSpot', 'uncanny-automator' ),
				$this->get_action_meta()
			)
		);
		$this->set_readable_sentence( esc_html_x( 'Remove a HubSpot contact from {{a static segment}}', 'HubSpot', 'uncanny-automator' ) );
		$this->set_background_processing( true );
	}

	/**
	 * Load options
	 *
	 * @return array
	 */
	public function options() {
		return array(
			$this->helpers->get_email_option_config( 'HUBSPOTEMAIL', esc_attr_x( 'Contact email address', 'HubSpot', 'uncanny-automator' ) ),
			$this->helpers->get_list_option_config( $this->get_action_meta() ),
		);
	}

	/**
	 * Process action
	 *
	 * @param int $user_id
	 * @param array $action_data
	 * @param int $recipe_id
	 * @param array $args
	 * @param array $parsed
	 *
	 * @return bool
	 * @throws \Exception If the action fails.
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		$list_id = $this->get_parsed_meta_value( $this->get_action_meta(), '' );
		$email   = trim( $this->get_parsed_meta_value( 'HUBSPOTEMAIL', '' ) );

		$this->api->remove_contact_from_list( $list_id, $email, $action_data );

		return true;
	}
}
