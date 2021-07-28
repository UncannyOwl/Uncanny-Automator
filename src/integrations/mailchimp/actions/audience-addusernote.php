<?php

namespace Uncanny_Automator;

/**
 * Class AUDIENCE_ADDUSERNOTE
 * @package Uncanny_Automator
 */
class AUDIENCE_ADDUSERNOTE {

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
		$this->action_code = 'MCHIMPAUDIENCEADDUSERNOTE';
		$this->action_meta = 'AUDIENCEADDUSERNOTE';
		$this->define_action();
	}

	/**
	 * Define and register the action by pushing it into the Automator object
	 */
	public function define_action() {

		global $uncanny_automator;
		$textarea                     = $uncanny_automator->helpers->recipe->field->text_field( 'MCNOTE', __( 'Note', 'uncanny-automator' ), true, 'textarea', null, false, __( 'Note length is limited to 1,000 characters.', 'uncanny-automator' ) );
		$textarea['supports_tinymce'] = false;

		$action = array(
			'author'             => $uncanny_automator->get_author_name( $this->action_code ),
			'support_link'       => $uncanny_automator->get_author_support_link( $this->action_code, 'knowledge-base/mailchimp/' ),
			'is_pro'             => false,
			'integration'        => self::$integration,
			'code'               => $this->action_code,
			'sentence'           => sprintf( __( 'Add {{a note:%1$s}} to the user', 'uncanny-automator' ), $this->action_meta ),
			'select_option_name' => __( 'Add {{a note}} to the user', 'uncanny-automator' ),
			'priority'           => 10,
			'accepted_args'      => 1,
			'execution_function' => array( $this, 'add_note_audience_member' ),
			'options_group'      => array(
				$this->action_meta => array(
					$uncanny_automator->helpers->recipe->mailchimp->options->get_all_lists(
						__( 'Audience', 'uncanny-automator' ),
						'MCLIST'
					),
					$textarea,

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
	public function add_note_audience_member( $user_id, $action_data, $recipe_id, $args ) {

		global $uncanny_automator;

		try {
			// Here add note
			$list_id = $action_data['meta']['MCLIST'];
			$note    = $uncanny_automator->parse->text( $action_data['meta']['MCNOTE'], $recipe_id, $user_id, $args );

			// get current user email
			$user      = get_userdata( $user_id );
			$user_hash = md5( strtolower( trim( $user->user_email ) ) );

			$mc_client = $uncanny_automator->helpers->recipe->mailchimp->options->get_mailchimp_client();

			if ( $mc_client ) {

				$note_body = array(
					'note' => substr( strip_tags( $note ), 0, 1000 ),
				);

				$request_params = array(
					'action'    => 'add_subscriber_note',
					'list_id'   => $list_id,
					'user_hash' => $user_hash,
					'note'      => json_encode( $note_body ),
				);

				$response = $uncanny_automator->helpers->recipe->mailchimp->options->api_request( $request_params );

				// prepare meeting lists
				if ( $response->statusCode === 200 ) {

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
