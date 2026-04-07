<?php

namespace Uncanny_Automator\Integrations\Drip;

use Exception;

/**
 * Class DRIP_REMOVE_FROM_CAMPAIGN
 *
 * @package Uncanny_Automator
 *
 * @property Drip_App_Helpers $helpers
 * @property Drip_Api_Caller $api
 */
class DRIP_REMOVE_FROM_CAMPAIGN extends \Uncanny_Automator\Recipe\App_Action {

	/**
	 * Setup the action.
	 *
	 * @return void
	 */
	public function setup_action() {
		$this->set_integration( 'DRIP' );
		$this->set_action_code( 'REMOVE_FROM_CAMPAIGN' );
		$this->set_action_meta( 'EMAIL' );
		$this->set_is_pro( false );
		$this->set_requires_user( false );
		$this->set_background_processing( true );
		$this->set_support_link( Automator()->get_author_support_link( $this->get_action_code(), 'knowledge-base/drip/' ) );
		$this->set_readable_sentence( esc_attr_x( 'Remove {{a subscriber}} from {{a campaign}}', 'Drip', 'uncanny-automator' ) );
		$this->set_sentence(
			sprintf(
				// translators: %1$s: email, %2$s: campaign
				esc_attr_x( 'Remove {{a subscriber:%1$s}} from {{a campaign:%2$s}}', 'Drip', 'uncanny-automator' ),
				$this->get_action_meta(),
				'CAMPAIGN:' . $this->get_action_meta()
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
			$this->helpers->get_campaign_option_config( 'automator_drip_get_campaigns_with_unsubscribe_options' ),
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

		$email       = $this->helpers->validate_email( $parsed['EMAIL'] ?? '' );
		$campaign_id = sanitize_text_field( $action_data['meta']['CAMPAIGN'] ?? '' );

		if ( empty( $campaign_id ) ) {
			throw new Exception( esc_html_x( 'Invalid campaign selected', 'Drip', 'uncanny-automator' ) );
		}

		$this->api->remove_from_campaign( $email, $campaign_id, $action_data );

		return true;
	}
}
