<?php

namespace Uncanny_Automator\Integrations\ConvertKit;

/**
 * ConvertKit - Unsubscribe a subscriber (v4 only)
 *
 * @property ConvertKit_App_Helpers $helpers
 * @property ConvertKit_Api_Caller $api
 */
class CONVERTKIT_SUBSCRIBER_UNSUBSCRIBE extends \Uncanny_Automator\Recipe\App_Action {

	/**
	 * Setup Action.
	 *
	 * @return void
	 */
	protected function setup_action() {
		$this->set_integration( 'CONVERTKIT' );
		$this->set_action_code( 'CONVERTKIT_SUBSCRIBER_UNSUBSCRIBE' );
		$this->set_action_meta( 'CONVERTKIT_SUBSCRIBER_UNSUBSCRIBE_META' );
		$this->set_is_pro( false );
		$this->set_requires_user( false );
		$this->set_background_processing( true );
		$this->set_support_link( Automator()->get_author_support_link( $this->get_action_code(), 'knowledge-base/convertkit/' ) );
		$this->set_readable_sentence( esc_attr_x( 'Unsubscribe {{a subscriber}}', 'ConvertKit', 'uncanny-automator' ) );
		$this->set_sentence(
			sprintf(
				// translators: %1$s is the email address
				esc_attr_x( 'Unsubscribe {{a subscriber:%1$s}}', 'ConvertKit', 'uncanny-automator' ),
				$this->get_action_meta()
			)
		);
	}

	/**
	 * Requires OAuth (v4) connection.
	 *
	 * @return bool
	 */
	public function requirements_met() {
		return ! $this->helpers->is_v3();
	}

	/**
	 * Define options.
	 *
	 * @return array
	 */
	public function options() {
		return array(
			$this->helpers->get_email_option_config( $this->get_action_meta() ),
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
	 * @throws \Exception
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		$email = $this->helpers->require_valid_email( $parsed[ $this->get_action_meta() ] ?? '' );

		$this->api->api_request(
			array(
				'action'        => 'unsubscribe_subscriber',
				'email_address' => $email,
			),
			$action_data
		);

		return true;
	}
}
