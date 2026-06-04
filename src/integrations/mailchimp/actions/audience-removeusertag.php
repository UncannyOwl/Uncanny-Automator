<?php
namespace Uncanny_Automator\Integrations\Mailchimp;

/**
 * Class AUDIENCE_REMOVEUSERTAG
 *
 * @package Uncanny_Automator
 * @property Mailchimp_App_Helpers $helpers
 * @property Mailchimp_Api_Caller $api
 */
class AUDIENCE_REMOVEUSERTAG extends \Uncanny_Automator\Recipe\App_Action {

	use Mailchimp_Audience_Fields;
	use Mailchimp_Email_Fields;
	use Mailchimp_Tag_Fields;

	/**
	 * Setup action.
	 *
	 * @return void
	 */
	protected function setup_action() {
		$this->set_integration( 'MAILCHIMP' );
		$this->set_action_code( 'MCHIMPAUDIENCEREMOVEUSERTAG' );
		$this->set_action_meta( 'AUDIENCEREMOVEUSERTAG' );
		$this->set_is_pro( false );
		$this->set_requires_user( true );
		$this->set_background_processing( true );
		$this->set_support_link( Automator()->get_author_support_link( $this->get_action_code(), 'knowledge-base/mailchimp/' ) );
		$this->set_readable_sentence( esc_html_x( 'Remove {{a tag}} from the user', 'Mailchimp', 'uncanny-automator' ) );
		$this->set_sentence(
			sprintf(
				// translators: %1$s is the tag
				esc_html_x( 'Remove {{a tag:%1$s}} from the user', 'Mailchimp', 'uncanny-automator' ),
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
			$this->get_tags_select_config( 'MCLISTTAGS', true ),
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
		$tag        = $this->get_tag_from_parsed( 'MCLISTTAGS' );
		$user_email = $this->get_email_from_user( $user_id );

		$this->api->remove_tag_from_contact( $list_id, $user_email, array( $tag ) );

		return true;
	}
}
