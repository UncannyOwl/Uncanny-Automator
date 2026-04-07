<?php
namespace Uncanny_Automator\Integrations\Zoho_Campaigns;

/**
 * @property Zoho_Campaigns_App_Helpers $helpers
 * @property Zoho_Campaigns_Api_Caller $api
 */
class ZOHO_CAMPAIGNS_CONTACT_LIST_UNSUB extends \Uncanny_Automator\Recipe\App_Action {

	/**
	 * Setup action.
	 *
	 * @return void
	 */
	protected function setup_action() {
		$this->set_integration( 'ZOHO_CAMPAIGNS' );
		$this->set_action_code( 'ZOHO_CAMPAIGNS_CONTACT_LIST_UNSUB' );
		$this->set_action_meta( 'ZOHO_CAMPAIGNS_CONTACT_LIST_UNSUB_META' );
		$this->set_is_pro( false );
		$this->set_support_link( Automator()->get_author_support_link( $this->get_action_code(), 'knowledge-base/zoho-campaigns/' ) );
		$this->set_requires_user( false );
		$this->set_background_processing( true );
		$this->set_readable_sentence( esc_attr_x( 'Unsubscribe {{a contact}} from {{a list}}', 'ZohoCampaigns', 'uncanny-automator' ) );
		$this->set_sentence(
			sprintf(
				// translators: %1$s: contact meta, %2$s: list meta
				esc_attr_x( 'Unsubscribe {{a contact:%1$s}} from {{a list:%2$s}}', 'ZohoCampaigns', 'uncanny-automator' ),
				$this->get_action_meta(),
				'LIST:' . $this->get_action_meta()
			)
		);

		$this->set_action_tokens(
			array(
				'LIST_NAME' => array(
					'name' => esc_html_x( 'List name', 'ZohoCampaigns', 'uncanny-automator' ),
					'type' => 'text',
				),
			),
			$this->get_action_code()
		);
	}

	/**
	 * Loads options.
	 *
	 * @return void.
	 */
	public function options() {

		return array(
			$this->helpers->get_list_option_config(),
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

		$list    = sanitize_text_field( $parsed['LIST'] ?? '' );
		$contact = sanitize_text_field( $parsed[ $this->get_action_meta() ] ?? '' );

		$this->api->contact_list_unsub(
			array(
				'contact'  => $contact,
				'list_key' => $list,
			),
			$action_data
		);

		$this->hydrate_tokens(
			array(
				'LIST_NAME' => $action_data['meta']['LIST_readable'] ?? '',
			)
		);

		return true;
	}
}
