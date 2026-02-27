<?php

namespace Uncanny_Automator\Integrations\Litespeed_Cache;

/**
 * Class Litespeed_Cache_Purge_Post
 *
 * @package Uncanny_Automator
 * @method \Uncanny_Automator\Integrations\Litespeed_Cache\Litespeed_Cache_Helpers get_item_helpers()
 */
class Litespeed_Cache_Purge_Post extends \Uncanny_Automator\Recipe\Action {

	/**
	 * Setup action.
	 *
	 * @return void
	 */
	protected function setup_action() {
		$this->set_integration( 'LITESPEED_CACHE' );
		$this->set_action_code( 'LITESPEED_CACHE_PURGE_POST' );
		$this->set_action_meta( 'LITESPEED_CACHE_POST_ID' );
		$this->set_is_pro( false );
		$this->set_requires_user( false );
		$this->set_background_processing( true );
		// translators: %1$s is the post ID.
		$this->set_sentence( sprintf( esc_html_x( 'Purge LiteSpeed cache for {{a specific post:%1$s}}', 'LiteSpeed Cache', 'uncanny-automator' ), $this->get_action_meta() ) );
		$this->set_readable_sentence( esc_html_x( 'Purge LiteSpeed cache for {{a specific post}}', 'LiteSpeed Cache', 'uncanny-automator' ) );
		$this->set_action_tokens(
			array(
				'POST_ID'    => array(
					'name' => esc_html_x( 'Post ID', 'LiteSpeed Cache', 'uncanny-automator' ),
					'type' => 'int',
				),
				'POST_TITLE' => array(
					'name' => esc_html_x( 'Post title', 'LiteSpeed Cache', 'uncanny-automator' ),
					'type' => 'text',
				),
				'POST_URL'   => array(
					'name' => esc_html_x( 'Post URL', 'LiteSpeed Cache', 'uncanny-automator' ),
					'type' => 'url',
				),
			),
			$this->get_action_code()
		);
	}

	/**
	 * Define action options.
	 *
	 * @return array[]
	 */
	public function options() {
		return array(
			array(
				'option_code'     => $this->get_action_meta(),
				'label'           => esc_html_x( 'Post ID', 'LiteSpeed Cache', 'uncanny-automator' ),
				'input_type'      => 'text',
				'required'        => true,
				'relevant_tokens' => array(),
				'description'     => esc_html_x( 'Enter a post ID or use a token from a trigger', 'LiteSpeed Cache', 'uncanny-automator' ),
			),
		);
	}

	/**
	 * Process the action.
	 *
	 * @param int   $user_id     The user ID.
	 * @param array $action_data The action configuration.
	 * @param int   $recipe_id   The recipe ID.
	 * @param array $args        Additional arguments.
	 * @param array $parsed      Parsed token values.
	 *
	 * @return bool
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		$post_id = absint( $parsed[ $this->get_action_meta() ] ?? 0 );

		if ( 0 === $post_id ) {
			$this->add_log_error( 'A valid post ID is required.' );
			return false;
		}

		$post = get_post( $post_id );

		if ( null === $post ) {
			$this->add_log_error( sprintf( 'Post with ID %d does not exist.', $post_id ) );
			return false;
		}

		$this->get_item_helpers()->purge_post_cache( $post_id );

		$this->hydrate_tokens(
			array(
				'POST_ID'    => $post_id,
				'POST_TITLE' => $post->post_title,
				'POST_URL'   => get_permalink( $post_id ),
			)
		);

		return true;
	}
}
