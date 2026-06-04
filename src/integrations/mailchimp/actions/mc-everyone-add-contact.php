<?php
namespace Uncanny_Automator\Integrations\Mailchimp;

/**
 * Class MC_EVERYONE_ADD_CONTACT
 *
 * @package Uncanny_Automator
 * @property Mailchimp_App_Helpers $helpers
 * @property Mailchimp_Api_Caller $api
 */
class MC_EVERYONE_ADD_CONTACT extends \Uncanny_Automator\Recipe\App_Action {

	use Mailchimp_Audience_Fields;
	use Mailchimp_Email_Fields;
	use Mailchimp_Subscriber_Fields;

	/**
	 * Setup action.
	 *
	 * @return void
	 */
	protected function setup_action() {
		$this->set_integration( 'MAILCHIMP' );
		$this->set_action_code( 'MC_EVERYONE_ADD_CONTACT' );
		$this->set_action_meta( 'MC_EVERYONE_ADD_CONTACT_META' );
		$this->set_is_pro( false );
		$this->set_requires_user( false );
		$this->set_background_processing( true );
		$this->set_support_link( Automator()->get_author_support_link( $this->get_action_code(), 'knowledge-base/mailchimp/' ) );
		$this->set_readable_sentence( esc_html_x( 'Add {{a contact}} to {{an audience}}', 'Mailchimp', 'uncanny-automator' ) );
		$this->set_sentence(
			sprintf(
				// translators: %1$s is the audience, %2$s is the contact email
				esc_html_x( 'Add {{a contact:%2$s}} to {{an audience:%1$s}}', 'Mailchimp', 'uncanny-automator' ),
				$this->get_action_meta(),
				'EMAIL'
			)
		);
	}

	/**
	 * Define grouped action options.
	 *
	 * @return array
	 */
	public function load_options() {
		return array(
			'options'       => array(
				$this->get_email_field_config( 'EMAIL' ),
			),
			'options_group' => array(
				$this->get_action_meta() => array(
					$this->get_audience_select_config(),
					$this->get_double_optin_config(),
					$this->get_update_existing_config(),
					$this->get_change_groups_config(),
					$this->get_groups_select_config(),
					$this->get_language_code_config(),
					$this->get_merge_fields_repeater_config(),
				),
			),
		);
	}

	/**
	 * Get language code field configuration.
	 *
	 * @return array The field configuration.
	 */
	private function get_language_code_config() {
		return array(
			'option_code' => 'MCLANGUAGECODE',
			'label'       => esc_html_x( 'Language code', 'Mailchimp', 'uncanny-automator' ),
			'input_type'  => 'text',
			'required'    => false,
			'tokens'      => true,
		);
	}

	/**
	 * Process the action.
	 *
	 * @param int   $user_id     The user ID.
	 * @param array $action_data The action data.
	 * @param int   $recipe_id   The recipe ID.
	 * @param array $args        The arguments.
	 * @param array $parsed      The parsed values.
	 *
	 * @return bool
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {
		$email   = $this->get_email_from_parsed( 'EMAIL' );
		$list_id = $this->get_audience_from_parsed();

		$subscriber_data = array(
			'email'           => $email,
			'list_id'         => $list_id,
			'double_optin'    => $this->is_double_optin_enabled() ? 'yes' : 'no',
			'update_existing' => $this->is_update_existing_enabled() ? 'yes' : 'no',
			'change_groups'   => $this->get_change_groups_from_parsed(),
			'groups_list'     => $this->get_groups_from_parsed(),
			'lang_code'       => sanitize_text_field( trim( $this->get_parsed_meta_value( 'MCLANGUAGECODE' ) ) ),
			'merge_fields'    => $this->get_merge_fields_from_parsed(),
		);

		$this->api->add_subscriber_to_list( $subscriber_data );

		return true;
	}
}
