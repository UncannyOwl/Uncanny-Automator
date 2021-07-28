<?php

namespace Uncanny_Automator;

/**
 * Class AUDIENCE_ADDUSERTAG
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

		global $uncanny_automator;

		$action = array(
			'author'             => $uncanny_automator->get_author_name( $this->action_code ),
			'support_link'       => $uncanny_automator->get_author_support_link( $this->action_code, 'knowledge-base/mailchimp/' ),
			'is_pro'             => false,
			'integration'        => self::$integration,
			'code'               => $this->action_code,
			'sentence'           => sprintf( __( 'Add {{a tag:%1$s}} to the user', 'uncanny-automator' ), $this->action_meta ),
			'select_option_name' => __( 'Add {{a tag}} to the user', 'uncanny-automator' ),
			'priority'           => 10,
			'accepted_args'      => 1,
			'execution_function' => array( $this, 'add_tag_audience_member' ),
			'options_group'      => array(
				$this->action_meta => array(
					$uncanny_automator->helpers->recipe->mailchimp->options->get_all_lists(
						__( 'Audience', 'uncanny-automator' ),
						'MCLIST',
						array(
							'is_ajax'      => true,
							'target_field' => 'MCLISTTAGS',
							'endpoint'     => 'select_mctagslist_from_mclist',
						)
					),
					$uncanny_automator->helpers->recipe->mailchimp->options->get_list_tags(
						__( 'Tags', 'uncanny-automator' ),
						'MCLISTTAGS',
						array(
							'is_ajax' => true,
						)
					),

				),
			),
		);

		$uncanny_automator->register->action( $action );
	}


	/**
	 * Validation function when the action is hit
	 *
	 * @param $user_id
	 * @param $action_data
	 * @param $recipe_id
	 */
	public function add_tag_audience_member( $user_id, $action_data, $recipe_id, $args ) {

		global $uncanny_automator;

		try {
			// Here add note
			$list_id = $action_data['meta']['MCLIST'];
			$tag     = $action_data['meta']['MCLISTTAGS'];

			if ( empty( $tag ) ) {
				// log error when no token found.
				$error_msg                           = __( 'No tag selected.', 'uncanny-automator' );
				$action_data['do-nothing']           = true;
				$action_data['complete_with_errors'] = true;
				$uncanny_automator->complete_action( $user_id, $action_data, $recipe_id, $error_msg );

				return;
			}

			// get current user email
			$user      = get_userdata( $user_id );
			$user_hash = md5( strtolower( trim( $user->user_email ) ) );

			$mc_client = $uncanny_automator->helpers->recipe->mailchimp->options->get_mailchimp_client();
			if ( $mc_client ) {

				$tags_body = array(
					'tags' => array(
						array(
							'name'   => $tag,
							'status' => 'active',
						),
					),
				);

				$request_params = array(
					'action'    => 'update_subscriber_tags',
					'list_id'   => $list_id,
					'user_hash' => $user_hash,
					'tags'      => json_encode( $tags_body ),
				);

				$response = $uncanny_automator->helpers->recipe->mailchimp->options->api_request( $request_params );

				// prepare meeting lists
				if ( $response === null ) {

					$uncanny_automator->complete_action( $user_id, $action_data, $recipe_id );

					return;
				} else {
					$uncanny_automator->helpers->recipe->mailchimp->options->log_action_error( $response, $user_id, $action_data, $recipe_id );

					return;
				}
			} else {
				// log error when no token found.
				$error_msg                           = __( 'Mailchimp account is not connected.', 'uncanny-automator' );
				$action_data['do-nothing']           = true;
				$action_data['complete_with_errors'] = true;
				$uncanny_automator->complete_action( $user_id, $action_data, $recipe_id, $error_msg );

				return;
			}
		} catch ( \Exception $e ) {
			$error_msg = $e->getMessage();
			if ( $json = json_decode( $error_msg ) ) {
				if ( isset( $json->error ) && isset( $json->error->message ) ) {
					$error_msg = $json->error->message;
				}
			}
			$action_data['do-nothing']           = true;
			$action_data['complete_with_errors'] = true;
			$uncanny_automator->complete_action( $user_id, $action_data, $recipe_id, $error_msg );

			return;
		}
	}

}
