<?php

namespace Uncanny_Automator;

/**
 * Class TWITTER_POSTSTATUS
 *
 * @package Uncanny_Automator
 */
class TWITTER_POSTSTATUS_2 {

	use Recipe\Action_Tokens;
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
			'author'                => Automator()->get_author_name( $this->action_code ),
			'support_link'          => Automator()->get_author_support_link( $this->action_code, 'knowledge-base/twitter/' ),
			'integration'           => self::$integration,
			'code'                  => $this->action_code,
			'sentence'              => sprintf( __( 'Post {{a tweet:%1$s}} to Twitter', 'uncanny-automator' ), $this->action_meta ),
			'select_option_name'    => __( 'Post {{a tweet}} to Twitter', 'uncanny-automator' ),
			'priority'              => 10,
			'accepted_args'         => 1,
			'requires_user'         => false,
			'execution_function'    => array( $this, 'post_status' ),
			'options_group'         => array(
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
							'description' => 'Supported image formats include JPG, PNG, GIF, WEBP. Images posted to Twitter have a 5MB limit.',
							'required'    => false,
						)
					),
				),
			),
			'background_processing' => true,
		);

		$this->set_action_tokens(
			array(
				'POST_LINK' => array(
					'name' => __( 'Link to Tweet', 'uncanny-automator' ),
					'type' => 'url',
				),
			),
			$this->action_code
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

			$response = $this->statuses_update( $status, $media, $action_data );

			$post_id = isset( $response['data']['id'] ) ? $response['data']['id'] : 0;

			$has_screen_name = ! empty( $response['data']['user']['screen_name'] );

			if ( 0 !== $post_id && $has_screen_name ) {

				// The Tweet link.
				$post_link = strtr(
					'https://twitter.com/{{screen_name}}/status/{{post_id}}',
					array(
						'{{screen_name}}' => $response['data']['user']['screen_name'],
						'{{post_id}}'     => $post_id,
					)
				);

				$this->hydrate_tokens( array( 'POST_LINK' => $post_link ) );

			}

			Automator()->complete_action( $user_id, $action_data, $recipe_id );

			return;

		} catch ( \Exception $e ) {

			$error_msg                           = $this->parse_errors( $e->getMessage() );
			$action_data['complete_with_errors'] = true;
			Automator()->complete_action( $user_id, $action_data, $recipe_id, $error_msg );

			return;
		}

	}

	/**
	 * parse_errors
	 *
	 * @param  string $error_msg
	 * @return string
	 */
	public function parse_errors( $error_msg ) {

		$output = array();

		// The message has several lines, parse them into array.
		$lines = explode( "\n", $error_msg );

		// The second line usually has a json string, but let's loop through all of them just in case.
		foreach ( $lines as $line ) {

			$error_array = json_decode( $line, true );

			if ( ! empty( $error_array['errors'] ) ) {

				foreach ( $error_array['errors'] as $error ) {

					if ( empty( $error['code'] ) || empty( $error['message'] ) ) {
						continue;
					}

					$output[] = 'Error code ' . $error['code'] . ': ' . $error['message'];
				}
			}
		}

		// Return the original string if no errors were parsed.
		if ( empty( $output ) ) {
			return $error_msg;
		}

		return implode( '<br>', $output );
	}

	/**
	 * Send data to Automator API.
	 *
	 * @param string $status
	 *
	 * @return mixed
	 */
	public function statuses_update( $status, $media = '', $action_data = null ) {

		$body['action'] = 'statuses_update';
		$body['status'] = $status;
		$body['media']  = $media;

		$response = Automator()->helpers->recipe->twitter->api_request( $body, $action_data, 60 );

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
			throw new \Exception( __( "Media couldn't be uploded", 'uncanny-automator' ) );
		}

		return $response['data']['media_id'];
	}
}
