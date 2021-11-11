<?php

namespace Uncanny_Automator;

/**
 * Class TWITTER_POSTSTATUS
 *
 * @package Uncanny_Automator
 */
class TWITTER_POSTSTATUS_2 {
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
		$this->action_code = 'TWITTERPOSTSTATUS2';
		$this->action_meta = 'TWITTERSTATUS';
		$this->define_action();

	}

	/**
	 * Define and register the action by pushing it into the Automator object.
	 */
	public function define_action() {

		$action = array(
			'author'             => Automator()->get_author_name( $this->action_code ),
			'support_link'       => Automator()->get_author_support_link( $this->action_code, 'integration/twitter/' ),
			'integration'        => self::$integration,
			'code'               => $this->action_code,
			'sentence'           => sprintf( __( 'Post {{a status:%1$s}} to Twitter', 'uncanny-automator' ), $this->action_meta ),
			'select_option_name' => __( 'Post {{a status}} to Twitter', 'uncanny-automator' ),
			'priority'           => 10,
			'accepted_args'      => 1,
			'requires_user'      => false,
			'execution_function' => array( $this, 'post_status' ),
			'options_group'      => array(
				$this->action_meta => array(
					Automator()->helpers->recipe->twitter->textarea_field(
						'TWITTERSTATUSCONTENT',
						esc_attr__( 'Status', 'uncanny-automator' ),
						true,
						'textarea',
						'',
						true,
						esc_attr__( 'Messages posted to Twitter have a 280 character limit.', 'uncanny-automator' ),
						__( 'Enter the message', 'uncanny-automator' ),
						278
					),
					// Image field.
					Automator()->helpers->recipe->field->text(
						array(
							'option_code' => 'TWITTERSTATUSIMAGE',
							/* translators: Image field */
							'label'       => esc_attr__( 'Image URL', 'uncanny-automator' ),
							'input_type'  => 'text',
							'default'     => '',
							'description' => 'Images posted to Twitter have a 5 Mb limit.',
							'required'    => false,
						)
					),
				),
			),
		);

		Automator()->register->action( $action );
	}

	/**
	 * Action validation function.
	 *
	 * @return mixed
	 */
	public function post_status( $user_id, $action_data, $recipe_id, $args ) {

		$status = Automator()->parse->text( $action_data['meta']['TWITTERSTATUSCONTENT'], $recipe_id, $user_id, $args );
		$media  = trim( Automator()->parse->text( $action_data['meta']['TWITTERSTATUSIMAGE'], $recipe_id, $user_id, $args ) );

		try {

			$media_id = '';

			if ( ! empty( $media ) ) {
				$media_id = $this->media_upload( $media );
			}

			$response = $this->statuses_update( $status, $media_id );

			Automator()->complete_action( $user_id, $action_data, $recipe_id );
			return;

		} catch ( \Exception $e ) {
			$error_msg                           = $e->getMessage();
			$action_data['do-nothing']           = true;
			$action_data['complete_with_errors'] = true;
			Automator()->complete_action( $user_id, $action_data, $recipe_id, $error_msg );

			return;
		}

	}

	/**
	 * Send data to Automator API.
	 *
	 * @param string $status
	 *
	 * @return mixed
	 */
	public function statuses_update( $status, $media_id = '' ) {

		// Get twitter credentials.
		$request_body = Automator()->helpers->recipe->twitter->get_client();

		$url = Automator()->helpers->recipe->twitter->automator_api;

		$request_body['action']    = 'statuses_update';
		$request_body['status']    = $status;
		$request_body['media_ids'] = $media_id;

		$args         = array();
		$args['body'] = $request_body;

		$response = wp_remote_post( $url, $args );

		if ( is_array( $response ) && ! is_wp_error( $response ) ) {

			$body = json_decode( wp_remote_retrieve_body( $response ) );

			if ( ! isset( $body->error ) ) {

				return $body->data;

			} else {

				throw new \Exception( $body->error->description );

			}
		} else {

			$error_msg = $response->get_error_message();

			throw new \Exception( $error_msg );

		}

	}

	/**
	 * Send image to Automator API.
	 *
	 * @param string $status
	 *
	 * @return mixed
	 */
	public function media_upload( $media ) {

		// Get twitter credentials.
		$request_body = Automator()->helpers->recipe->twitter->get_client();

		$url = Automator()->helpers->recipe->twitter->automator_api;

		$request_body['action'] = 'media_upload';
		$request_body['media']  = $media;
		$args                   = array();
		$args['body']           = $request_body;
		$args['timeout']        = 60;

		$response = wp_remote_post( $url, $args );

		if ( is_array( $response ) && ! is_wp_error( $response ) ) {
			$body = json_decode( wp_remote_retrieve_body( $response ) );
			if ( ! isset( $body->error ) && isset( $body->data->media_id ) ) {
				return $body->data->media_id;
			} else {
				throw new \Exception( $body->error->description );
			}
		} else {
			$error_msg = $response->get_error_message();
			throw new \Exception( $error_msg );
		}

	}
}
