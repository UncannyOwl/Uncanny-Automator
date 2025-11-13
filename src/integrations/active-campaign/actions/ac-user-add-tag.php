<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
namespace Uncanny_Automator\Integrations\Active_Campaign;

/**
 * Class AC_USER_ADD_TAG
 *
 * @package Uncanny_Automator
 *
 * @property Active_Campaign_App_Helpers $helpers
 * @property Active_Campaign_Api_Caller $api
 */
class AC_USER_ADD_TAG extends \Uncanny_Automator\Recipe\App_Action {

	/**
	 * Meta key prefix.
	 *
	 * @var string
	 */
	protected $prefix = 'AC_USER_ADD_TAG';

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
				// translators: %1$s: Tag which will be added to the user
				esc_attr_x( 'Add {{a tag:%1$s}} to the user', 'ActiveCampaign', 'uncanny-automator' ),
				$this->get_action_meta()
			)
		);
		$this->set_readable_sentence( esc_attr_x( 'Add {{a tag}} to the user', 'ActiveCampaign', 'uncanny-automator' ) );
		$this->set_background_processing( true );
	}

	/**
	 * Define the options.
	 *
	 * @return array
	 */
	public function options() {
		return array(
			$this->helpers->get_tag_select_config( $this->get_action_meta(), true ),
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
		$tag_id = sanitize_text_field( $parsed[ $this->get_action_meta() ] ?? 0 );
		$user   = get_user_by( 'ID', $user_id );

		$this->api->add_tag( $user->data->user_email, $tag_id, $action_data );

		return true;
	}
}
