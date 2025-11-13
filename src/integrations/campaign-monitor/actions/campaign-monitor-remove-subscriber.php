<?php

namespace Uncanny_Automator\Integrations\Campaign_Monitor;

use Exception;

/**
 * Class CAMPAIGN_MONITOR_REMOVE_SUBSCRIBER
 *
 * @package Uncanny_Automator
 *
 * @property Campaign_Monitor_App_Helpers $helpers
 * @property Campaign_Monitor_Api_Caller $api
 */
class CAMPAIGN_MONITOR_REMOVE_SUBSCRIBER extends \Uncanny_Automator\Recipe\App_Action {

	/**
	 * Prefix for action code / meta.
	 *
	 * @var string
	 */
	public $prefix = 'CAMPAIGN_MONITOR_REMOVE_SUBSCRIBER';

	/**
	 * Set up action.
	 *
	 * @return void
	 */
	public function setup_action() {

		$this->set_integration( 'CAMPAIGN_MONITOR' );
		$this->set_action_code( $this->prefix . '_CODE' );
		$this->set_action_meta( $this->prefix . '_META' );
		$this->set_is_pro( false );
		$this->set_support_link( Automator()->get_author_support_link( $this->action_code, 'knowledge-base/campaign-monitor/' ) );
		$this->set_requires_user( false );
		$this->set_sentence(
			sprintf(
				/* translators: %1$s Subscriber Email, %2$s List*/
				esc_attr_x( 'Remove {{a subscriber:%1$s}} from {{a list:%2$s}}', 'Campaign Monitor', 'uncanny-automator' ),
				$this->get_action_meta(),
				'LIST:' . $this->get_action_meta()
			)
		);
		$this->set_readable_sentence( esc_attr_x( 'Remove {{a subscriber}} from {{a list}}', 'Campaign Monitor', 'uncanny-automator' ) );
		$this->set_background_processing( true );
	}

	/**
	 * Define options.
	 *
	 * @return array
	 */
	public function options() {

		$fields = array(
			array(
				'option_code' => $this->get_action_meta(),
				'label'       => esc_html_x( 'Email', 'Campaign Monitor', 'uncanny-automator' ),
				'input_type'  => 'email',
				'required'    => true,
			),
		);

		// Hidden field or select list depending on # of clients.
		$fields[] = $this->helpers->get_client_field();

		// List select field based on client.
		$fields[] = $this->helpers->get_client_list_field();

		return $fields;
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

		// Required fields - throws error if not set and valid.
		$list_id = $this->helpers->get_list_id_from_parsed( $parsed, 'LIST' );
		$email   = $this->helpers->get_email_from_parsed( $parsed, $this->get_action_meta() );

		// Send request.
		$this->api->api_request(
			array(
				'action'  => 'remove_subscriber',
				'email'   => $email,
				'list_id' => $list_id,
			),
			$action_data
		);

		return true;
	}
}
