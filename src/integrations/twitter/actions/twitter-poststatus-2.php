<?php

namespace Uncanny_Automator\Integrations\Twitter;

/**
 * Class TWITTER_POSTSTATUS_2
 *
 * @package Uncanny_Automator
 *
 * @property Twitter_App_Helpers $helpers
 * @property Twitter_Api_Caller $api
 */
class TWITTER_POSTSTATUS_2 extends \Uncanny_Automator\Recipe\App_Action {

	/**
	 * Setup Action.
	 *
	 * @return void.
	 */
	protected function setup_action() {
		$this->set_integration( 'TWITTER' );
		$this->set_action_code( 'TWITTERPOSTSTATUS2' );
		$this->set_action_meta( 'TWITTERSTATUS' );
		$this->set_is_pro( false );
		$this->set_support_link( Automator()->get_author_support_link( $this->get_action_code(), 'knowledge-base/twitter/' ) );
		$this->set_requires_user( false );
		$this->set_wpautop( false );
		$this->set_sentence(
			sprintf(
				// translators: %1$s is the tweet content
				esc_attr_x( 'Post {{a tweet:%1$s}} to X/Twitter', 'Twitter', 'uncanny-automator' ),
				$this->get_action_meta()
			)
		);
		$this->set_readable_sentence( esc_attr_x( 'Post {{a tweet}} to X/Twitter', 'Twitter', 'uncanny-automator' ) );
		$this->set_background_processing( true );

		// Set up action tokens
		$this->set_action_tokens(
			array(
				'POST_LINK' => array(
					'name' => esc_html_x( 'Link to Tweet', 'Twitter', 'uncanny-automator' ),
					'type' => 'url',
				),
			),
			$this->get_action_code()
		);
	}

	/**
	 * Define the action options.
	 *
	 * @return array
	 */
	public function options() {
		return array(
			$this->helpers->get_recipe_status_config( 'TWITTERSTATUSCONTENT' ),
			array(
				'option_code' => 'TWITTERSTATUSIMAGE',
				'label'       => esc_attr_x( 'Image URL or Media library ID', 'Twitter', 'uncanny-automator' ),
				'input_type'  => 'text',
				'required'    => false,
				'description' => esc_attr_x( 'Supported image formats include JPG, PNG, GIF, WEBP. Images posted to Twitter have a 5MB limit.', 'Twitter', 'uncanny-automator' ),
			),
		);
	}

	/**
	 * Process the Twitter action.
	 *
	 * @param int    $user_id
	 * @param array  $action_data
	 * @param int    $recipe_id
	 * @param array  $args
	 * @param array  $parsed
	 *
	 * @return bool
	 * @throws \Exception When the action fails.
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {
		$status = $this->get_parsed_meta_value( 'TWITTERSTATUSCONTENT' );
		$media  = trim( sanitize_text_field( $parsed['TWITTERSTATUSIMAGE'] ) );

		// Handle media library ID
		if ( is_numeric( $media ) ) {
			$media = wp_get_attachment_url( intval( $media ) );
			if ( empty( $media ) ) {
				throw new \Exception( esc_html_x( 'Media library image not found.', 'Twitter', 'uncanny-automator' ) );
			}
		}

		// Make the API call
		$response = $this->api->statuses_update( $status, $media, $action_data );

		// Handle the response and set tokens
		$post_id  = isset( $response['data']['id'] ) ? $response['data']['id'] : 0;
		$username = $this->helpers->get_username();

		if ( 0 !== $post_id && ! empty( $username ) ) {
			// Generate the Tweet link
			$post_link = strtr(
				'https://twitter.com/{{screen_name}}/status/{{post_id}}',
				array(
					'{{screen_name}}' => $username,
					'{{post_id}}'     => $post_id,
				)
			);

			$this->hydrate_tokens( array( 'POST_LINK' => $post_link ) );
		}

		return true;
	}
}
