<?php

namespace Uncanny_Automator\Integrations\Gotomeeting;

use Uncanny_Automator\Recipe\App_Action;

/**
 * Class GTM_DELETEMEETING
 *
 * @property Gotomeeting_App_Helpers $helpers
 * @property Gotomeeting_Api_Caller $api
 *
 * @package Uncanny_Automator
 */
class GTM_DELETEMEETING extends App_Action {

	/**
	 * Setup the action.
	 *
	 * @return void
	 */
	protected function setup_action() {
		$this->set_integration( 'GTM' );
		$this->set_action_code( 'GTMDELETEMEETING' );
		$this->set_action_meta( 'GTMMEETING' );
		$this->set_requires_user( false );
		$this->set_is_pro( false );

		$this->set_sentence(
			sprintf(
				// translators: %s: Meeting name
				esc_html_x( 'Delete {{a meeting:%s}}', 'GoToMeeting', 'uncanny-automator' ),
				$this->get_action_meta()
			)
		);

		$this->set_readable_sentence( esc_html_x( 'Delete {{a meeting}}', 'GoToMeeting', 'uncanny-automator' ) );

		$this->set_background_processing( true );
	}

	/**
	 * Define action options.
	 *
	 * @return array
	 */
	public function options() {
		return array(
			$this->helpers->get_meeting_options_config( $this->get_action_meta() ),
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

		$meeting_id = $this->helpers->get_meeting_from_parsed( $parsed, $this->get_action_meta() );

		$this->api->delete_meeting( $meeting_id, $action_data );

		return true;
	}
}
