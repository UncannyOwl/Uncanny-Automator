<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
namespace Uncanny_Automator\Integrations\Active_Campaign;

/**
 * Class AC_USER_LIST_REMOVE
 *
 * @package Uncanny_Automator
 *
 * @property Active_Campaign_App_Helpers $helpers
 * @property Active_Campaign_Api_Caller $api
 */
class AC_USER_LIST_REMOVE extends \Uncanny_Automator\Recipe\App_Action {

	/**
	 * Meta key prefix.
	 *
	 * @var string
	 */
	protected $prefix = 'AC_USER_LIST_REMOVE';

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
		$this->set_requires_user( true );
		$this->set_sentence(
			sprintf(
				// translators: %1$s: List ID
				esc_attr_x( 'Remove the user from {{a list:%1$s}}', 'ActiveCampaign', 'uncanny-automator' ),
				$this->get_action_meta()
			)
		);
		$this->set_readable_sentence( esc_attr_x( 'Remove the user from {{a list}}', 'ActiveCampaign', 'uncanny-automator' ) );
		$this->set_background_processing( true );
	}

	/**
	 * Define the options for the action.
	 *
	 * @return array
	 */
	public function options() {
		return array(
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
		$user    = get_user_by( 'ID', $user_id );

		$this->api->remove_contact_from_list( $user->data->user_email, $list_id, $action_data );

		return true;
	}
}
