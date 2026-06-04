<?php
namespace Uncanny_Automator\Integrations\Mailchimp;

/**
 * Class AUDIENCE_UNSUBSCRIBEAUSER
 *
 * @package Uncanny_Automator
 * @property Mailchimp_App_Helpers $helpers
 * @property Mailchimp_Api_Caller $api
 */
class AUDIENCE_UNSUBSCRIBEAUSER extends \Uncanny_Automator\Recipe\App_Action {

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
		$this->set_action_code( 'MCHIMPAUDIENCEUNSUBSCRIBEAUSER' );
		$this->set_action_meta( 'AUDIENCEUNSUBSCRIBEAUSER' );
		$this->set_is_pro( false );
		$this->set_requires_user( true );
		$this->set_background_processing( true );
		$this->set_support_link( Automator()->get_author_support_link( $this->get_action_code(), 'knowledge-base/mailchimp/' ) );
		$this->set_readable_sentence( esc_html_x( 'Unsubscribe the user from {{an audience}}', 'Mailchimp', 'uncanny-automator' ) );
		$this->set_sentence(
			sprintf(
				// translators: %1$s is the audience
				esc_html_x( 'Unsubscribe the user from {{an audience:%1$s}}', 'Mailchimp', 'uncanny-automator' ),
				$this->get_action_meta()
			)
		);
	}

	/**
	 * Define action options.
	 *
	 * @return array
	 */
	public function options() {
		return array(
			$this->get_audience_select_config(),
			$this->get_delete_member_config(),
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
		$list_id    = $this->get_audience_from_parsed();
		$user_email = $this->get_email_from_user( $user_id );

		if ( $this->should_delete_member() ) {
			$this->api->delete_subscriber( $list_id, $user_email );
		} else {
			$this->api->unsubscribe_contact( $list_id, $user_email );
		}

		return true;
	}
}
