<?php

namespace Uncanny_Automator;

/**
 * Class CAMPAIGN_CREATEANDSEND
 *
 * @package Uncanny_Automator
 */
class CAMPAIGN_CREATEANDSEND {

	/**
	 * Integration code
	 *
	 * @var string
	 */
	public static $integration = 'MAILCHIMP';

	private $action_code;
	private $action_meta;

	/**
	 * Set up Automator action constructor.
	 */
	public function __construct() {

		$this->action_code = 'MCHIMPCAMPAIGNCREATEANDSEND';

		$this->action_meta = 'CAMPAIGNCREATEANDSEND';

		add_filter( 'automator_option_text_field', array( $this, 'add_supports_fullpage_editing' ), 10, 1 );

		$this->define_action();

	}

	/**
	 * Adds fullpage editing to `MCEMAILCONTENT` field.
	 *
	 * @param $option The accepted argument from `automator_option_text_field` filter.
	 * @since 4.1.1
	 *
	 * @return array $option The option.
	 */
	public function add_supports_fullpage_editing( $option ) {

		if ( 'MCEMAILCONTENT' === $option['option_code'] ) {

			$option['supports_fullpage_editing'] = true;

		}

		return $option;

	}

	/**
	 * Define and register the action by pushing it into the Automator object
	 */
	public function define_action() {

		$action = array(
			'author'             => Automator()->get_author_name( $this->action_code ),
			'support_link'       => Automator()->get_author_support_link( $this->action_code, 'knowledge-base/mailchimp/' ),
			'is_pro'             => false,
			'integration'        => self::$integration,
			'code'               => $this->action_code,
			'requires_user'      => false,
			// translators: Campaign
			'sentence'           => sprintf( __( 'Create and send {{a campaign:%1$s}}', 'uncanny-automator' ), $this->action_meta ),
			'select_option_name' => __( 'Create and send {{a campaign}}', 'uncanny-automator' ),
			'priority'           => 10,
			'accepted_args'      => 1,
			'requires_user'      => false,
			'options_callback'   => array( $this, 'load_options' ),
			'execution_function' => array( $this, 'create_send_campaign' ),
		);

		Automator()->register->action( $action );
	}

	public function load_options() {
		return array(
			'options_group' => array(
				$this->action_meta => array(
					Automator()->helpers->recipe->field->text_field( 'MCCAMPAIGNTITLE', __( 'Campaign name', 'uncanny-automator' ), true, 'text', null ),
					Automator()->helpers->recipe->mailchimp->options->get_all_lists(
						__( 'Audience', 'uncanny-automator' ),
						'MCLIST',
						array(
							'is_ajax'      => true,
							'target_field' => 'MCLISTTAGS',
							'endpoint'     => 'select_segments_from_list',
						)
					),
					Automator()->helpers->recipe->mailchimp->options->get_list_tags(
						__( 'Segment or Tag', 'uncanny-automator' ),
						'MCLISTTAGS',
						array(
							'required'    => false,
							'is_ajax'     => true,
							'description' => __( 'If no segment/tag is selected, the campaign will be sent to the entire audience.', 'uncanny-automator' ),
						)
					),
					Automator()->helpers->recipe->field->text_field( 'MCEMAILSUBJECT', __( 'Email subject', 'uncanny-automator' ), true, 'text', null ),
					Automator()->helpers->recipe->field->text_field( 'MCPREVIEWTEXT', __( 'Preview text', 'uncanny-automator' ), true, 'text', null, false ),
					Automator()->helpers->recipe->field->text_field( 'MCFROMNAME', __( 'From name', 'uncanny-automator' ), true, 'text', null ),
					Automator()->helpers->recipe->field->text_field( 'MCFROMEMAILADDRESS', __( 'From email address', 'uncanny-automator' ), true, 'email', null ),
					Automator()->helpers->recipe->field->text_field( 'MCTONAME', __( 'To name', 'uncanny-automator' ), true, 'text', null, false, __( 'Supports merge tags such as *|FNAME|*, *|LNAME|*, *|FNAME|* *|LNAME|*, etc.', 'uncanny-automator' ) ),
					Automator()->helpers->recipe->mailchimp->options->get_all_email_templates(
						__( 'Template', 'uncanny-automator' ),
						'MCEMAILTEMPLATE',
						array(
							'description' => __( 'If a template is selected, the Email Content field below will be ignored.', 'uncanny-automator' ),
						)
					),
					Automator()->helpers->recipe->field->text_field( 'MCEMAILCONTENT', __( 'Email content', 'uncanny-automator' ), true, 'textarea', null, false ),
				),
			),
		);
	}


	/**
	 * Validation function when the action is hit
	 *
	 * @param $user_id
	 * @param $action_data
	 * @param $recipe_id
	 */
	public function create_send_campaign( $user_id, $action_data, $recipe_id, $args ) {

		$helpers = Automator()->helpers->recipe->mailchimp->options;

		try {

			// Here campaign info.
			$campaign_title     = Automator()->parse->text( $action_data['meta']['MCCAMPAIGNTITLE'], $recipe_id, $user_id, $args );
			$list_id            = $action_data['meta']['MCLIST'];
			$segment_id         = $action_data['meta']['MCLISTTAGS'];
			$template_id        = $action_data['meta']['MCEMAILTEMPLATE'];
			$email_subject      = Automator()->parse->text( $action_data['meta']['MCEMAILSUBJECT'], $recipe_id, $user_id, $args );
			$preview_text       = Automator()->parse->text( $action_data['meta']['MCPREVIEWTEXT'], $recipe_id, $user_id, $args );
			$from_name          = Automator()->parse->text( $action_data['meta']['MCFROMNAME'], $recipe_id, $user_id, $args );
			$from_email_address = Automator()->parse->text( $action_data['meta']['MCFROMEMAILADDRESS'], $recipe_id, $user_id, $args );
			$to_name            = Automator()->parse->text( $action_data['meta']['MCTONAME'], $recipe_id, $user_id, $args );
			$email_content      = Automator()->parse->text( $action_data['meta']['MCEMAILCONTENT'], $recipe_id, $user_id, $args );

			// First create a campaign
			$campaign_schema = array(
				'type'       => 'regular',
				'recipients' => array(
					'list_id' => $list_id,
				),
				'settings'   => array(
					'subject_line' => $email_subject,
					'preview_text' => $preview_text,
					'title'        => $campaign_title,
					'from_name'    => $from_name,
					'reply_to'     => $from_email_address,
					'to_name'      => $to_name,
				),
			);

			if ( ! empty( $segment_id ) && '-1' !== $segment_id ) {
				$campaign_schema['recipients']['segment_opts']['saved_segment_id'] = (int) $segment_id;
			}

			if ( ! empty( $template_id ) && '-1' !== $template_id ) {
				$campaign_schema['settings']['template_id'] = (int) $template_id;
				$campaign_schema['content_type']            = 'template';
			} else {
				$campaign_schema['content_type'] = 'multichannel';
			}

			$request_params = array(
				'action'          => 'add_campaign',
				'campaign_schema' => wp_json_encode( $campaign_schema ),
			);

			$add_campaign_response = $helpers->api_request( $request_params );

			$campaign_id = $add_campaign_response['data']['id'];

			// Put content if template was not set.
			if ( empty( $template_id ) || '-1' === $template_id ) {
				$campaign_content = array(
					'html' => $email_content,
				);

				$request_params = array(
					'action'           => 'update_campaign_content',
					'campaign_content' => wp_json_encode( $campaign_content ),
					'campaign_id'      => $campaign_id,
				);

				$update_campaign_content_response = $helpers->api_request( $request_params );
			}

			$request_params = array(
				'action'      => 'send_campaign',
				'campaign_id' => $campaign_id,
			);

			// Now all set so send campaign.
			$send_campaign_response = $helpers->api_request( $request_params, $action_data );

			Automator()->complete_action( $user_id, $action_data, $recipe_id );

		} catch ( \Exception $e ) {
			$helpers->complete_with_error( $e->getMessage(), $user_id, $action_data, $recipe_id );
		}
	}


}
