<?php

namespace Uncanny_Automator\Integrations\Drip;

use Exception;

/**
 * Class DRIP_ADD_TAG
 *
 * @package Uncanny_Automator
 *
 * @property Drip_App_Helpers $helpers
 * @property Drip_Api_Caller $api
 */
class DRIP_ADD_TAG extends \Uncanny_Automator\Recipe\App_Action {

	/**
	 * Setup the action.
	 *
	 * @return void
	 */
	public function setup_action() {
		$this->set_integration( 'DRIP' );
		$this->set_action_code( 'ADD_TAG' );
		$this->set_action_meta( 'TAG' );
		$this->set_is_pro( false );
		$this->set_requires_user( false );
		$this->set_background_processing( true );
		$this->set_support_link( Automator()->get_author_support_link( $this->get_action_code(), 'knowledge-base/drip/' ) );
		$this->set_readable_sentence( esc_attr_x( 'Add {{a tag}} to {{a subscriber}}', 'Drip', 'uncanny-automator' ) );
		$this->set_sentence(
			sprintf(
				// translators: %1$s: tag, %2$s: email address
				esc_attr_x( 'Add {{a tag:%1$s}} to {{a subscriber:%2$s}}', 'Drip', 'uncanny-automator' ),
				$this->get_action_meta(),
				'EMAIL:' . $this->action_meta
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
			$this->helpers->get_tag_option_config( $this->action_meta ),
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
		$tag   = sanitize_text_field( $parsed[ $this->action_meta ] ?? '' );

		if ( empty( $tag ) ) {
			throw new Exception( esc_html_x( 'Invalid tag selected', 'Drip', 'uncanny-automator' ) );
		}

		$this->api->add_tag( $email, $tag, $action_data );

		return true;
	}
}
