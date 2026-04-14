<?php

namespace Uncanny_Automator\Integrations\Drip;

use Exception;

/**
 * Class DRIP_UNSUBSCRIBE_ALL
 *
 * @package Uncanny_Automator
 *
 * @property Drip_App_Helpers $helpers
 * @property Drip_Api_Caller $api
 */
class DRIP_UNSUBSCRIBE_ALL extends \Uncanny_Automator\Recipe\App_Action {

	/**
	 * Setup the action.
	 *
	 * @return void
	 */
	public function setup_action() {
		$this->set_integration( 'DRIP' );
		$this->set_action_code( 'UNSUBSCRIBE_ALL' );
		$this->set_action_meta( 'EMAIL' );
		$this->set_is_pro( false );
		$this->set_requires_user( false );
		$this->set_background_processing( true );
		$this->set_support_link( Automator()->get_author_support_link( $this->get_action_code(), 'knowledge-base/drip/' ) );
		$this->set_readable_sentence( esc_attr_x( 'Unsubscribe {{a subscriber}} from all the mailings', 'Drip', 'uncanny-automator' ) );
		$this->set_sentence(
			sprintf(
				// translators: %1$s: email
				esc_attr_x( 'Unsubscribe {{a subscriber:%1$s}} from all the mailings', 'Drip', 'uncanny-automator' ),
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
		return array(
			$this->helpers->get_email_option_config(),
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
	 * @throws Exception If the API request fails.
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		$email = $this->helpers->validate_email( $parsed['EMAIL'] ?? '' );

		$this->api->unsubscribe_all( $email, $action_data );

		return true;
	}
}
