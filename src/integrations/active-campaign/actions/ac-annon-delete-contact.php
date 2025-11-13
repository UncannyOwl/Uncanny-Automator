<?php
namespace Uncanny_Automator\Integrations\Active_Campaign;

/**
 * Class Contact_Delete
 *
 * @package Uncanny_Automator
 *
 * @property Active_Campaign_App_Helpers $helpers
 * @property Active_Campaign_Api_Caller $api
 */
class AC_ANNON_CONTACT_DELETE extends \Uncanny_Automator\Recipe\App_Action {

	/**
	 * Meta key prefix.
	 *
	 * @var string
	 */
	protected $prefix = 'AC_ANNON_CONTACT_DELETE';

	/**
	 * Setup the action.
	 *
	 * @return void
	 */
	protected function setup_action() {
		$this->set_integration( 'ACTIVE_CAMPAIGN' );
		$this->set_action_code( $this->prefix . '_CODE' );
		$this->set_action_meta( $this->prefix . '_META' );
		$this->set_requires_user( false );
		$this->set_sentence(
			sprintf(
				// translators: $1%s: The email address of the contact to delete
				esc_attr_x( 'Delete a contact that matches {{an email:%1$s}}', 'ActiveCampaign', 'uncanny-automator' ),
				$this->get_action_meta()
			)
		);
		$this->set_readable_sentence(
			esc_attr_x( 'Delete a contact that matches {{an email}}', 'ActiveCampaign', 'uncanny-automator' )
		);
	}

	/**
	 * Define the options for the action.
	 *
	 * @return array
	 */
	public function options() {
		return array(
			$this->helpers->get_email_field_config( $this->get_action_meta() ),
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
		$email = sanitize_text_field( $parsed[ $this->get_action_meta() ] ?? 0 );

		$this->api->delete_contact( $email, $action_data );

		return true;
	}
}
