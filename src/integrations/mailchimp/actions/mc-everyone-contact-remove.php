<?php
namespace Uncanny_Automator\Integrations\Mailchimp;

/**
 * Class MC_EVERYONE_CONTACT_REMOVE
 *
 * @package Uncanny_Automator
 * @property Mailchimp_App_Helpers $helpers
 * @property Mailchimp_Api_Caller $api
 */
class MC_EVERYONE_CONTACT_REMOVE extends \Uncanny_Automator\Recipe\App_Action {

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
		$this->set_action_code( 'MC_EVERYONE_CONTACT_REMOVE' );
		// Note: Legacy used 'MET' (typo), not 'META'. Preserved for backward compatibility.
		$this->set_action_meta( 'MC_EVERYONE_CONTACT_REMOVE_MET' );
		$this->set_is_pro( false );
		$this->set_requires_user( false );
		$this->set_support_link( Automator()->get_author_support_link( $this->get_action_code(), 'knowledge-base/mailchimp/' ) );
		$this->set_readable_sentence( esc_html_x( 'Unsubscribe {{a contact}} from {{an audience}}', 'Mailchimp', 'uncanny-automator' ) );
		$this->set_sentence(
			sprintf(
				// translators: %1$s is the contact email, %2$s is the audience
				esc_html_x( 'Unsubscribe {{a contact:%1$s}} from {{an audience:%2$s}}', 'Mailchimp', 'uncanny-automator' ),
				$this->get_action_meta() . '_EMAIL',
				$this->get_action_meta()
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
				$this->get_email_field_config( $this->get_action_meta() . '_EMAIL' ),
			),
			'options_group' => array(
				$this->get_action_meta() => array(
					$this->get_audience_select_config(),
					$this->get_delete_member_config(),
				),
			),
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
		$email   = $this->get_email_from_parsed( $this->get_action_meta() . '_EMAIL' );
		$list_id = $this->get_audience_from_parsed();

		if ( $this->should_delete_member() ) {
			$this->api->delete_subscriber( $list_id, $email );
		} else {
			$this->api->unsubscribe_contact( $list_id, $email );
		}

		return true;
	}
}
