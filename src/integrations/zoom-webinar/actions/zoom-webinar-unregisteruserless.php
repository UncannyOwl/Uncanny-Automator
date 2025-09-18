<?php

namespace Uncanny_Automator\Integrations\Zoom_Webinar;

use Uncanny_Automator\Recipe\App_Action;
use Exception;

/**
 * Class ZOOM_WEBINAR_UNREGISTERUSERLESS
 *
 * @package Uncanny_Automator
 * @property Zoom_Webinar_App_Helpers $helpers
 * @property Zoom_Webinar_Api_Caller $api
 */
class ZOOM_WEBINAR_UNREGISTERUSERLESS extends App_Action {

	use Zoom_Webinar_Registration_Trait;

	/**
	 * Setup action.
	 *
	 * @return void
	 */
	protected function setup_action() {
		$this->set_integration( 'ZOOMWEBINAR' );
		$this->set_action_code( 'ZOOMWEBINARUNREGISTERUSERLESS' );
		$this->set_action_meta( 'ZOOMWEBINAR' );
		$this->set_is_pro( false );
		$this->set_requires_user( false );
		// translators: %1$s Webinar topic
		$this->set_sentence( sprintf( esc_html_x( 'Remove an attendee from {{a webinar:%1$s}}', 'Zoom Webinar', 'uncanny-automator' ), $this->get_action_meta() ) );
		$this->set_readable_sentence( esc_html_x( 'Remove an attendee from {{a webinar}}', 'Zoom Webinar', 'uncanny-automator' ) );
		$this->set_background_processing( true );
	}

	/**
	 * Define options.
	 *
	 * @return array
	 */
	public function options() {
		return array(
			$this->get_email_field(),
			$this->get_account_user_field(),
			$this->get_webinar_selection_field( $this->get_action_meta() ),
		);
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
	 * @throws Exception
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {
		$webinar_key = $this->get_parsed_meta_value( $this->get_action_meta() );
		$email       = $this->get_parsed_meta_value( 'EMAIL' );

		if ( empty( $webinar_key ) ) {
			throw new Exception( esc_html_x( 'Webinar was not found.', 'Zoom Webinar', 'uncanny-automator' ) );
		}

		if ( empty( $email ) ) {
			throw new Exception( esc_html_x( 'Email address is missing.', 'Zoom Webinar', 'uncanny-automator' ) );
		}

		if ( false === is_email( $email ) ) {
			throw new Exception( esc_html_x( 'Invalid email address.', 'Zoom Webinar', 'uncanny-automator' ) );
		}

		$webinar_key = $this->parse_webinar_key( $webinar_key );

		$this->api->unregister_user_from_webinar( $email, $webinar_key, $action_data );

		return true;
	}
}
