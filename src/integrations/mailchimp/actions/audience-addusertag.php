<?php

namespace Uncanny_Automator;

/**
 * Class AUDIENCE_ADDUSERTAG
 *
 * @package Uncanny_Automator
 */
class AUDIENCE_ADDUSERTAG {

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
		$this->action_code = 'MCHIMPAUDIENCEADDUSERTAG';
		$this->action_meta = 'AUDIENCEADDUSERTAG';
		$this->define_action();
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
			// translators: Mailchimp tag
			'sentence'           => sprintf( __( 'Add {{a tag:%1$s}} to the user', 'uncanny-automator' ), $this->action_meta ),
			'select_option_name' => __( 'Add {{a tag}} to the user', 'uncanny-automator' ),
			'priority'           => 10,
			'accepted_args'      => 1,
			'options_callback'   => array( $this, 'load_options' ),
			'execution_function' => array( $this, 'add_tag_audience_member' ),
		);

		Automator()->register->action( $action );
	}

	/**
	 * The options_callback method.
	 *
	 * @return void
	 */
	public function load_options() {
		return array(
			'options_group' => array(
				$this->action_meta => array(
					Automator()->helpers->recipe->mailchimp->options->get_all_lists(
						__( 'Audience', 'uncanny-automator' ),
						'MCLIST',
						array(
							'is_ajax'      => true,
							'target_field' => 'MCLISTTAGS',
							'endpoint'     => 'select_mctagslist_from_mclist',
						)
					),
					Automator()->helpers->recipe->mailchimp->options->get_list_tags(
						__( 'Tags', 'uncanny-automator' ),
						'MCLISTTAGS',
						array(
							'is_ajax'                  => true,
							'token'                    => true,
							'custom_value_description' => esc_html__( 'Enter a tag name. If a matching tag does not exist a new one will be created.', 'uncanny-automator' ),
						)
					),

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
	public function add_tag_audience_member( $user_id, $action_data, $recipe_id, $args ) {

		$helpers = Automator()->helpers->recipe->mailchimp->options;

		try {
			// Here add note
			$list_id = $action_data['meta']['MCLIST'];
			$tag     = $action_data['meta']['MCLISTTAGS'];

			if ( empty( $tag ) ) {
				throw new \Exception( __( 'No tag selected.', 'uncanny-automator' ) );
			}

			// get current user email
			$user      = get_userdata( $user_id );
			$user_hash = md5( strtolower( trim( $user->user_email ) ) );

			$tags_body = array(
				'tags' => array(
					array(
						'name'   => $tag,
						'status' => 'active',
					),
				),
			);

			$request_params = array(
				'action'     => 'update_subscriber_tags_add_list',
				'list_id'    => $list_id,
				'user_hash'  => $user_hash,
				'user_email' => $user->user_email,
				'tags'       => wp_json_encode( $tags_body ),
			);

			$response = $helpers->api_request( $request_params, $action_data );

			Automator()->complete_action( $user_id, $action_data, $recipe_id );

		} catch ( \Exception $e ) {
			$helpers->complete_with_error( $e->getMessage(), $user_id, $action_data, $recipe_id );
		}
	}
}
