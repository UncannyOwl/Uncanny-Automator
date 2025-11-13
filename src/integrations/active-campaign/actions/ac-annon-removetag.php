<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
namespace Uncanny_Automator\Integrations\Active_Campaign;

/**
 * Class AC_ANNON_REMOVETAG
 *
 * @package Uncanny_Automator
 *
 * @property Active_Campaign_App_Helpers $helpers
 * @property Active_Campaign_Api_Caller $api
 */
class AC_ANNON_REMOVETAG extends \Uncanny_Automator\Recipe\App_Action {

	/**
	 * Meta key prefix.
	 *
	 * @var string
	 */
	protected $prefix = 'AC_ANNON_REMOVETAG';

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
				// translators: %1$s: Tag ID, %2$s: Contact Email
				esc_attr_x( 'Remove {{a tag:%1$s}} from {{a contact:%2$s}}', 'ActiveCampaign', 'uncanny-automator' ),
				$this->get_action_meta(),
				$this->prefix . '_CONTACT_ID:' . $this->get_action_meta()
			)
		);
		$this->set_readable_sentence( esc_attr_x( 'Remove {{a tag}} from {{a contact}}', 'Active Campaign', 'uncanny-automator' ) );
		$this->set_background_processing( true );
	}

	/**
	 * Define the options for the action.
	 *
	 * @return array
	 */
	public function options() {
		return array(
			$this->helpers->get_tag_select_config( $this->get_action_meta(), true ),
			$this->helpers->get_email_field_config( $this->prefix . '_CONTACT_ID' ),
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
		$contact_email = sanitize_text_field( $parsed[ $this->prefix . '_CONTACT_ID' ] ?? 0 );
		$tag_id        = sanitize_text_field( $parsed[ $this->get_action_meta() ] ?? 0 );

		$this->api->remove_tag( $contact_email, $tag_id, $action_data );

		return true;
	}
}
