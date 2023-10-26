<?php

namespace Uncanny_Automator;

/**
 * Class TWITTER_POSTSTATUS_2
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

	public $functions;

	/**
	 * Set up Automator action constructor.
	 */
	public function __construct() {
		$this->action_code = 'TWITTERPOSTSTATUS2';
		$this->action_meta = 'TWITTERSTATUS';
		$this->functions   = new Twitter_Functions();
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
			/* translators: Tweet text */
			'sentence'              => sprintf( __( 'Post {{a tweet:%1$s}} to X/Twitter', 'uncanny-automator' ), $this->action_meta ),
			'select_option_name'    => __( 'Post {{a tweet}} to X/Twitter', 'uncanny-automator' ),
			'priority'              => 10,
			'accepted_args'         => 1,
			'requires_user'         => false,
			'execution_function'    => array( $this, 'post_status' ),
			'options_group'         => array(
				$this->action_meta => array(
					$this->functions->textarea_field(
						'TWITTERSTATUSCONTENT',
						esc_attr__( 'Status', 'uncanny-automator' ),
						true,
						'textarea',
						'',
						true,
						esc_attr__( 'Messages posted to X/Twitter have a 280 character limit.', 'uncanny-automator' ),
						__( 'Enter the message', 'uncanny-automator' ),
						278
					),
					// Image field.
					Automator()->helpers->recipe->field->text(
						array(
							'option_code' => 'TWITTERSTATUSIMAGE',
							/* translators: Image field */
							'label'       => esc_attr__( 'Image URL or Media library ID', 'uncanny-automator' ),
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

		if ( is_numeric( $media ) ) {
			$media = wp_get_attachment_url( intval( $media ) );
		}

		try {

			$response = $this->functions->statuses_update( $status, $media, $action_data );

			$post_id = isset( $response['data']['id'] ) ? $response['data']['id'] : 0;

			$username = $this->functions->get_username();

			if ( 0 !== $post_id && ! empty( $username ) ) {

				// The Tweet link.
				$post_link = strtr(
					'https://twitter.com/{{screen_name}}/status/{{post_id}}',
					array(
						'{{screen_name}}' => $username,
						'{{post_id}}'     => $post_id,
					)
				);

				$this->hydrate_tokens( array( 'POST_LINK' => $post_link ) );

			}

			Automator()->complete_action( $user_id, $action_data, $recipe_id );

			return;

		} catch ( \Exception $e ) {

			$error_msg                           = $e->getMessage();
			$action_data['complete_with_errors'] = true;
			Automator()->complete_action( $user_id, $action_data, $recipe_id, $error_msg );

			return;
		}
	}
}
