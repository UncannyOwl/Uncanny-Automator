<?php

namespace Uncanny_Automator\Integrations\HubSpot;

use Uncanny_Automator\Recipe\App_Action;
use Exception;

/**
 * Class HUBSPOT_REMOVEUSERFROMLIST
 *
 * @package Uncanny_Automator
 *
 * @property HubSpot_App_Helpers $helpers
 * @property HubSpot_Api_Caller $api
 */
class HUBSPOT_REMOVEUSERFROMLIST extends App_Action {

	/**
	 * Set up action
	 *
	 * @return void
	 */
	protected function setup_action() {
		$this->set_integration( 'HUBSPOT' );
		$this->set_action_code( 'HUBSPOTREMOVEUSERFROMLIST' );
		$this->set_action_meta( 'HUBSPOTLIST' );
		$this->set_requires_user( true );
		$this->set_sentence(
			sprintf(
				// translators: %s: segment field name
				esc_html_x( "Remove the user's HubSpot contact from {{a static segment:%s}}", 'HubSpot', 'uncanny-automator' ),
				$this->get_action_meta()
			)
		);
		$this->set_readable_sentence( esc_html_x( "Remove the user's HubSpot contact from {{a static segment}}", 'HubSpot', 'uncanny-automator' ) );
		$this->set_background_processing( true );
	}

	/**
	 * Load options
	 *
	 * @return array
	 */
	public function options() {
		return array(
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
	 * @throws Exception If the action fails.
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		$user = get_userdata( $user_id );

		if ( ! $user ) {
			throw new Exception( esc_html_x( 'User not found.', 'HubSpot', 'uncanny-automator' ) );
		}

		$list_id = $this->get_parsed_meta_value( $this->get_action_meta(), '' );

		$this->api->remove_contact_from_list( $list_id, $user->user_email, $action_data );

		return true;
	}
}
