<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
namespace Uncanny_Automator\Integrations\Active_Campaign;

/**
 * Class AC_ANNON_LIST_ADD
 *
 * @package Uncanny_Automator
 *
 * @property Active_Campaign_App_Helpers $helpers
 * @property Active_Campaign_Api_Caller $api
 */
class AC_ANNON_LIST_ADD extends \Uncanny_Automator\Recipe\App_Action {

	/**
	 * Meta key prefix.
	 *
	 * @var string
	 */
	protected $prefix = 'AC_ANNON_LIST_ADD';

	/**
	 * Setup Action.
	 *
	 * @return void.
	 */
	protected function setup_action() {
		$this->set_integration( 'ACTIVE_CAMPAIGN' );
		$this->set_action_code( $this->prefix . '_CODE' );
		$this->set_action_meta( $this->prefix . '_META' );
		$this->set_is_pro( false );
		$this->set_requires_user( false );
		$this->set_sentence(
			sprintf(
				// translators: %1$s: Contact Email, %2$s: List ID
				esc_attr_x( 'Add {{a contact:%1$s}} to {{a list:%2$s}}', 'ActiveCampaign', 'uncanny-automator' ),
				$this->prefix . '_CONTACT_ID:' . $this->get_action_meta(),
				$this->get_action_meta()
			)
		);
		$this->set_readable_sentence( esc_attr_x( 'Add {{a contact}} to {{a list}}', 'ActiveCampaign', 'uncanny-automator' ) );
		$this->set_background_processing( true );
	}

	/**
	 * Define the options for the action.
	 *
	 * @return array
	 */
	public function options() {
		return array(
			$this->helpers->get_email_field_config( $this->prefix . '_CONTACT_ID' ),
			$this->helpers->get_list_select_config( $this->get_action_meta() ),
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
		$list_id = sanitize_text_field( $parsed[ $this->get_action_meta() ] ?? 0 );
		$email   = sanitize_text_field( $parsed[ $this->prefix . '_CONTACT_ID' ] ?? 0 );

		$this->api->add_contact_to_list( $email, $list_id, $action_data );

		return true;
	}
}
