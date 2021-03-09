<?php

namespace Uncanny_Automator;

/**
 * Class TWITTER_POSTSTATUS
 *
 * @package Uncanny_Automator
 */
class TWITTER_POSTSTATUS {
	/**
	 * Integration code
	 *
	 * @var string
	 */
	public static $integration = 'TWITTER';

	/**
	 *
	 * @var string
	 */
	private $action_code;

	/**
	 *
	 * @var string
	 */
	private $action_meta;

	/**
	 * Set up Automator action constructor.
	 */
	public function __construct() {
		$this->action_code = 'TWITTERPOSTSTATUS';
		$this->action_meta = 'TWITTERSTATUS';
		$this->define_action();

	}

	/**
	 * Define and register the action by pushing it into the Automator object.
	 */
	public function define_action() {

		global $uncanny_automator;

		$action = array(
			'author'             => $uncanny_automator->get_author_name( $this->action_code ),
			'support_link'       => $uncanny_automator->get_author_support_link( $this->action_code ),
			'integration'        => self::$integration,
			'code'               => $this->action_code,
			'sentence'           => sprintf( __( 'Post {{a status:%1$s}} to Twitter', 'uncanny-automator' ), $this->action_meta ),
			'select_option_name' => __( 'Post {{a status}} to Twitter', 'uncanny-automator' ),
			'priority'           => 10,
			'accepted_args'      => 1,
			'execution_function' => array( $this, 'post_status' ),
			'options_group'      => array(
				$this->action_meta => array(
					$uncanny_automator->helpers->recipe->twitter->textarea_field( 'TWITTERSTATUSCONTENT', esc_attr__( 'Status', 'uncanny-automator' ), true, 'textarea', '', true, esc_attr__( "Messages posted to Twitter have a 280 character limit.", 'uncanny-automator' ), __( 'Enter the message', 'uncanny-automator' ), 278 ),
					//Temporary fix for the UI
					array(
						'input_type'  => 'text',
						'option_code' => 'TWITTERSTATUSCONTENTHIDDEN',
						'is_hidden'   => true,
					),
				),
			),
		);

		$uncanny_automator->register->action( $action );
	}

	/**
	 * Action validation function.
	 *
	 *  @return mixed
	 */
	public function post_status( $user_id, $action_data, $recipe_id, $args ) {
		global $uncanny_automator;

		$status = $uncanny_automator->parse->text( $action_data['meta']['TWITTERSTATUSCONTENT'], $recipe_id, $user_id, $args );

		try {
			$response = $this->statuses_update( $status );

			if ( is_array( $response ) && ! is_wp_error( $response ) ) {
				$body = json_decode( wp_remote_retrieve_body( $response ) );
				if ( ! isset( $body->errors ) ) {
					$uncanny_automator->complete_action( $user_id, $action_data, $recipe_id );
					return;
				} else {
					foreach ( $body->errors as $error ) {
						$error_msg .= $error->code . ': ' . $error->message . PHP_EOL;
					}
					$action_data['do-nothing']           = true;
					$action_data['complete_with_errors'] = true;
					$uncanny_automator->complete_action( $user_id, $action_data, $recipe_id, $error_msg );
					return;
				}
			} else {
				throw new \Exception( __( 'WordPress was unable to communicate with the Automator API.', 'uncanny-automator' ) );
			}
		} catch ( \Exception $e ) {
			$error_msg                           = $e->getMessage();
			$action_data['do-nothing']           = true;
			$action_data['complete_with_errors'] = true;
			$uncanny_automator->complete_action( $user_id, $action_data, $recipe_id, $error_msg );

			return;
		}

	}

	/**
	 * Send data to Automator API.
	 *
	 * @param string $status
	 * @return mixed
	 */
	public function statuses_update( $status ) {

		global $uncanny_automator;

		// Get twitter credentials.
		$request_body = $uncanny_automator->helpers->recipe->twitter->get_client();

		$url = $uncanny_automator->helpers->recipe->twitter->automator_api;

		$request_body['action'] = 'twitter_statuses_update';
		$request_body['status'] = $status;
		$args                   = array();
		$args['body']           = $request_body;

		return wp_remote_post( $url, $args );
	}
}
