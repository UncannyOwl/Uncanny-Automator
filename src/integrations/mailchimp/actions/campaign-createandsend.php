<?php
namespace Uncanny_Automator\Integrations\Mailchimp;

/**
 * Class CAMPAIGN_CREATEANDSEND
 *
 * @package Uncanny_Automator
 * @property Mailchimp_App_Helpers $helpers
 * @property Mailchimp_Api_Caller $api
 */
class CAMPAIGN_CREATEANDSEND extends \Uncanny_Automator\Recipe\App_Action {

	use Mailchimp_Audience_Fields;

	/**
	 * Setup action.
	 *
	 * @return void
	 */
	protected function setup_action() {
		$this->set_integration( 'MAILCHIMP' );
		$this->set_action_code( 'MCHIMPCAMPAIGNCREATEANDSEND' );
		$this->set_action_meta( 'CAMPAIGNCREATEANDSEND' );
		$this->set_is_pro( false );
		$this->set_requires_user( false );
		$this->set_background_processing( true );
		$this->set_support_link( Automator()->get_author_support_link( $this->get_action_code(), 'knowledge-base/mailchimp/' ) );
		$this->set_readable_sentence( esc_html_x( 'Create and send {{a campaign}}', 'Mailchimp', 'uncanny-automator' ) );
		$this->set_sentence(
			sprintf(
				// translators: %1$s is the campaign
				esc_html_x( 'Create and send {{a campaign:%1$s}}', 'Mailchimp', 'uncanny-automator' ),
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
		$from_email_description = sprintf(
			// translators: %1$s is the text, %2$s is the link URL, %3$s is the link text
			'%1$s. <a target="_blank" href="%2$s" title="%3$s">%3$s</a>',
			esc_html_x( 'The from email must be from a verified domain', 'Mailchimp', 'uncanny-automator' ),
			Automator()->get_author_support_link( $this->get_action_code(), 'knowledge-base/mailchimp/' ),
			esc_html_x( 'Learn more', 'Mailchimp', 'uncanny-automator' )
		);

		$account_info = $this->helpers->get_account_info();

		return array(
			array(
				'option_code' => 'MCCAMPAIGNTITLE',
				'label'       => esc_html_x( 'Campaign name', 'Mailchimp', 'uncanny-automator' ),
				'input_type'  => 'text',
				'required'    => true,
				'tokens'      => true,
			),
			$this->get_audience_select_config(),
			array(
				'option_code'     => 'MCLISTTAGS',
				'label'           => esc_html_x( 'Segment or Tag', 'Mailchimp', 'uncanny-automator' ),
				'input_type'      => 'select',
				'required'        => false,
				'options'         => array(),
				'options_show_id' => false,
				'description'     => esc_html_x( 'If no segment/tag is selected, the campaign will be sent to the entire audience.', 'Mailchimp', 'uncanny-automator' ),
				'remote_data'     => $this->helpers->remote_data_parent_config( 'segments', array( 'MCLIST' ) ),
			),
			array(
				'option_code' => 'MCEMAILSUBJECT',
				'label'       => esc_html_x( 'Email subject', 'Mailchimp', 'uncanny-automator' ),
				'input_type'  => 'text',
				'required'    => true,
				'tokens'      => true,
			),
			array(
				'option_code' => 'MCPREVIEWTEXT',
				'label'       => esc_html_x( 'Preview text', 'Mailchimp', 'uncanny-automator' ),
				'input_type'  => 'text',
				'required'    => false,
				'tokens'      => true,
			),
			array(
				'option_code' => 'MCFROMNAME',
				'label'       => esc_html_x( 'From name', 'Mailchimp', 'uncanny-automator' ),
				'input_type'  => 'text',
				'required'    => true,
				'tokens'      => true,
			),
			array(
				'option_code'   => 'MCFROMEMAILADDRESS',
				'label'         => esc_html_x( 'From email address', 'Mailchimp', 'uncanny-automator' ),
				'input_type'    => 'email',
				'required'      => true,
				'tokens'        => true,
				'default_value' => $account_info['email'] ?? '',
				'description'   => $from_email_description,
			),
			array(
				'option_code' => 'MCTONAME',
				'label'       => esc_html_x( 'To name', 'Mailchimp', 'uncanny-automator' ),
				'input_type'  => 'text',
				'required'    => false,
				'tokens'      => true,
				'description' => esc_html_x( 'Supports Mailchimp merge tags such as *|FNAME|* and *|LNAME|*.', 'Mailchimp', 'uncanny-automator' ),
			),
			array(
				'option_code'     => 'MCEMAILTEMPLATE',
				'label'           => esc_html_x( 'Template', 'Mailchimp', 'uncanny-automator' ),
				'input_type'      => 'select',
				'required'        => false,
				'options'         => array(),
				'options_show_id' => false,
				'description'     => esc_html_x( 'If a template is selected, the Email content field below will be ignored.', 'Mailchimp', 'uncanny-automator' ),
				'remote_data'     => $this->helpers->remote_data_load_config( 'templates' ),
			),
			array(
				'option_code'               => 'MCEMAILCONTENT',
				'label'                     => esc_html_x( 'Email content', 'Mailchimp', 'uncanny-automator' ),
				'input_type'                => 'textarea',
				'required'                  => false,
				'tokens'                    => true,
				'supports_tinymce'          => true,
				'supports_fullpage_editing' => true,
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
		$campaign_title     = sanitize_text_field( trim( $this->get_parsed_meta_value( 'MCCAMPAIGNTITLE' ) ) );
		$list_id            = $this->get_audience_from_parsed();
		$segment_id         = sanitize_text_field( $this->get_parsed_meta_value( 'MCLISTTAGS' ) );
		$template_id        = sanitize_text_field( $this->get_parsed_meta_value( 'MCEMAILTEMPLATE' ) );
		$email_subject      = sanitize_text_field( trim( $this->get_parsed_meta_value( 'MCEMAILSUBJECT' ) ) );
		$preview_text       = sanitize_text_field( trim( $this->get_parsed_meta_value( 'MCPREVIEWTEXT' ) ) );
		$from_name          = sanitize_text_field( trim( $this->get_parsed_meta_value( 'MCFROMNAME' ) ) );
		$from_email_address = sanitize_email( trim( $this->get_parsed_meta_value( 'MCFROMEMAILADDRESS' ) ) );
		$to_name            = sanitize_text_field( trim( $this->get_parsed_meta_value( 'MCTONAME' ) ) );
		$email_content      = wp_kses_post( $this->get_parsed_meta_value( 'MCEMAILCONTENT' ) );

		// "To name" is intentionally NOT required. Mailchimp treats it as optional
		// (it personalizes the recipient line, e.g. *|FNAME|*), and pre-7.3 sent it
		// blank without issue. Requiring it was a migration regression that broke
		// long-standing campaigns whose To-name was left empty.
		if ( empty( $campaign_title ) || empty( $email_subject ) || empty( $from_name ) || empty( $from_email_address ) ) {
			throw new \Exception( esc_html_x( 'Campaign title, email subject, from name, and from email address are required.', 'Mailchimp', 'uncanny-automator' ) );
		}

		$campaign_data = array(
			'title'              => $campaign_title,
			'list_id'            => $list_id,
			'segment_id'         => $segment_id,
			'template_id'        => $template_id,
			'subject_line'       => $email_subject,
			'preview_text'       => $preview_text,
			'from_name'          => $from_name,
			'from_email_address' => $from_email_address,
			'to_name'            => $to_name,
			'email_content'      => $email_content,
		);

		$this->api->create_and_send_campaign( $campaign_data );

		return true;
	}
}
