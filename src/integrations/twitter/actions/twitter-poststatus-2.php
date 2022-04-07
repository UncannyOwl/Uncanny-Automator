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
			'support_link'       => Automator()->get_author_support_link( $this->action_code, 'knowledge-base/twitter/' ),
			'integration'        => self::$integration,
			'code'               => $this->action_code,
			'sentence'           => sprintf( __( 'Post {{a tweet:%1$s}} to Twitter', 'uncanny-automator' ), $this->action_meta ),
			'select_option_name' => __( 'Post {{a tweet}} to Twitter', 'uncanny-automator' ),
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

			$response = $this->statuses_update( $status, $media_id, $action_data );

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
	public function statuses_update( $status, $media_id = '', $action_data = null ) {

		$body['action']    = 'statuses_update';
		$body['status']    = $status;
		$body['media_ids'] = $media_id;

		$response = Automator()->helpers->recipe->twitter->api_request( $body, $action_data );

		return $response;
	}

	/**
	 * Send image to Automator API.
	 *
	 * @param string $status
	 *
	 * @return mixed
	 */
	public function media_upload( $media ) {

		$body['action'] = 'media_upload';
		$body['media']  = $media;

		$timeout = 60;

		$response = Automator()->helpers->recipe->twitter->api_request( $body, null, $timeout );

		if ( empty( $response['data']['media_id'] ) ) {
			throw new \Exception( __( "Media couldn't be uploded", "uncanny-automator" ) );
		} 

		return $response['data']['media_id'];
	}
}
