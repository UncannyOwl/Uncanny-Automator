<?php

namespace Uncanny_Automator\Integrations\Gotowebinar;

use Uncanny_Automator\Recipe\App_Action;

/**
 * Class GTW_UNREGISTERUSER
 *
 * @property Gotowebinar_App_Helpers $helpers
 * @property Gotowebinar_Api_Caller $api
 *
 * @package Uncanny_Automator
 */
class GTW_UNREGISTERUSER extends App_Action {

	/**
	 * Setup the action.
	 *
	 * @return void
	 */
	protected function setup_action() {
		$this->set_integration( 'GTW' );
		$this->set_action_code( 'GTWUNREGISTERUSER' );
		$this->set_action_meta( 'GTWWEBINAR' );
		$this->set_requires_user( true );
		$this->set_is_pro( false );

		$this->set_sentence(
			sprintf(
				// translators: %s: Webinar name
				esc_html_x( 'Remove the user from {{a webinar:%s}}', 'GoToWebinar', 'uncanny-automator' ),
				$this->get_action_meta()
			)
		);

		$this->set_readable_sentence( esc_html_x( 'Remove the user from {{a webinar}}', 'GoToWebinar', 'uncanny-automator' ) );

		$this->set_background_processing( true );
	}

	/**
	 * Define action options.
	 *
	 * @return array
	 */
	public function options() {
		return array(
			$this->helpers->get_webinar_options_config( $this->get_action_meta() ),
		);
	}

	/**
	 * Process the action.
	 *
	 * @param int   $user_id     User ID.
	 * @param array $action_data Action data.
	 * @param int   $recipe_id   Recipe ID.
	 * @param array $args        Action arguments.
	 * @param array $parsed      Parsed action data.
	 *
	 * @return bool
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		$webinar_key = $this->helpers->get_webinar_from_parsed( $parsed, $this->get_action_meta() );

		$this->api->unregister_user_from_webinar( $user_id, $webinar_key, $action_data );

		return true;
	}
}
