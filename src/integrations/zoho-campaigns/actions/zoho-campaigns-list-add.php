<?php
namespace Uncanny_Automator\Integrations\Zoho_Campaigns;

/**
 * Create a new list in Zoho Campaigns.
 *
 * @property Zoho_Campaigns_App_Helpers $helpers
 * @property Zoho_Campaigns_Api_Caller $api
 */
class ZOHO_CAMPAIGNS_LIST_ADD extends \Uncanny_Automator\Recipe\App_Action {

	/**
	 * Setup action.
	 *
	 * @return void
	 */
	protected function setup_action() {
		$this->set_integration( 'ZOHO_CAMPAIGNS' );
		$this->set_action_code( 'ZOHO_CAMPAIGNS_LIST_ADD' );
		$this->set_action_meta( 'ZOHO_CAMPAIGNS_LIST_ADD_META' );
		$this->set_is_pro( false );
		$this->set_support_link( Automator()->get_author_support_link( $this->get_action_code(), 'knowledge-base/zoho-campaigns/' ) );
		$this->set_requires_user( false );
		$this->set_background_processing( true );
		$this->set_readable_sentence( esc_html_x( 'Create {{a list}}', 'Zoho Campaigns', 'uncanny-automator' ) );
		$this->set_sentence(
			sprintf(
				// translators: %s: list meta
				esc_html_x( 'Create {{a list:%s}}', 'Zoho Campaigns', 'uncanny-automator' ),
				$this->get_action_meta()
			)
		);

		$this->set_action_tokens(
			array(
				'LIST_KEY'  => array(
					'name' => esc_html_x( 'List key', 'Zoho Campaigns', 'uncanny-automator' ),
					'type' => 'text',
				),
				'LIST_NAME' => array(
					'name' => esc_html_x( 'List name', 'Zoho Campaigns', 'uncanny-automator' ),
					'type' => 'text',
				),
			),
			$this->get_action_code()
		);
	}

	/**
	 * Loads options.
	 *
	 * @return array
	 */
	public function options() {
		return array(
			array(
				'option_code' => $this->get_action_meta(),
				'label'       => esc_html_x( 'List name', 'Zoho Campaigns', 'uncanny-automator' ),
				'input_type'  => 'text',
				'required'    => true,
			),
			array(
				'option_code' => 'SIGNUP_FORM',
				'label'       => esc_html_x( 'Signup form type', 'Zoho Campaigns', 'uncanny-automator' ),
				'input_type'  => 'select',
				'required'    => true,
				'options'     => array(
					array(
						'text'  => esc_html_x( 'Private', 'Zoho Campaigns', 'uncanny-automator' ),
						'value' => 'private',
					),
					array(
						'text'  => esc_html_x( 'Public', 'Zoho Campaigns', 'uncanny-automator' ),
						'value' => 'public',
					),
				),
			),
			array(
				'option_code' => 'EMAILS',
				'label'       => esc_html_x( 'Email addresses', 'Zoho Campaigns', 'uncanny-automator' ),
				'input_type'  => 'textarea',
				'required'    => true,
				'description' => esc_html_x( 'Max 10 emails separated by comma.', 'Zoho Campaigns', 'uncanny-automator' ),
			),
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

		$list_name   = sanitize_text_field( $parsed[ $this->get_action_meta() ] ?? '' );
		$signup_form = sanitize_text_field( $parsed['SIGNUP_FORM'] ?? 'private' );
		$emails      = sanitize_text_field( $parsed['EMAILS'] ?? '' );

		$response = $this->api->list_add(
			array(
				'list_name'   => $list_name,
				'signup_form' => $signup_form,
				'email_ids'   => $emails,
			),
			$action_data
		);

		$this->hydrate_tokens(
			array(
				'LIST_NAME' => $list_name,
				'LIST_KEY'  => $response['data']['list_key'] ?? '',
			)
		);

		return true;
	}
}
