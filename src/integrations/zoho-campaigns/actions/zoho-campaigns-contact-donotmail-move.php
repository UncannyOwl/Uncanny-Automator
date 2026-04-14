<?php
namespace Uncanny_Automator\Integrations\Zoho_Campaigns;

/**
 * @property Zoho_Campaigns_App_Helpers $helpers
 * @property Zoho_Campaigns_Api_Caller $api
 */
class ZOHO_CAMPAIGNS_CONTACT_DONOTMAIL_MOVE extends \Uncanny_Automator\Recipe\App_Action {

	/**
	 * Setup action.
	 *
	 * @return void
	 */
	protected function setup_action() {
		$this->set_integration( 'ZOHO_CAMPAIGNS' );
		$this->set_action_code( 'ZOHO_CAMPAIGNS_CONTACT_DONOTMAIL_MOVE' );
		$this->set_action_meta( 'ZOHO_CAMPAIGNS_CONTACT_DONOTMAIL_MOVE_META' );
		$this->set_is_pro( false );
		$this->set_support_link( Automator()->get_author_support_link( $this->get_action_code(), 'knowledge-base/zoho-campaigns/' ) );
		$this->set_requires_user( false );
		$this->set_background_processing( true );
		$this->set_readable_sentence( esc_html_x( 'Move {{a contact}} to Do-Not-Mail', 'Zoho Campaigns', 'uncanny-automator' ) );
		$this->set_sentence(
			sprintf(
				// translators: %s: contact meta
				esc_html_x( 'Move {{a contact:%s}} to Do-Not-Mail', 'Zoho Campaigns', 'uncanny-automator' ),
				$this->get_action_meta()
			)
		);
	}

	/**
	 * Loads options.
	 *
	 * @return array
	 */
	public function options() {
		return array(
			$this->helpers->get_email_option_config( $this->get_action_meta() ),
		);
	}

	/**
	 * Processes the action.
	 *
	 * @param int   $user_id
	 * @param array $action_data
	 * @param int   $recipe_id
	 * @param array $args
	 * @param array $parsed
	 *
	 * @return bool True on success.
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		$contact = sanitize_text_field( $parsed[ $this->get_action_meta() ] ?? '' );

		$this->api->contact_donotmail_move( $contact, $action_data );

		return true;
	}
}
