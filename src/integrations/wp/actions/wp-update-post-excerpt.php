<?php

namespace Uncanny_Automator;

use Uncanny_Automator\Recipe\Action;

/**
 * Class WP_UPDATE_POST_EXCERPT
 *
 * @pacakge Uncanny_Automator
 */
class WP_UPDATE_POST_EXCERPT extends Action {

	/**
	 * @return mixed
	 */
	protected function setup_action() {
		$this->set_integration( 'WP' );
		$this->set_action_code( 'WP_UPDATE_EXCERPT' );
		$this->set_action_meta( 'WP_POSTS' );
		$this->set_requires_user( false );
		// translators: 1: Post title
		$this->set_sentence( sprintf( esc_attr_x( 'Update the excerpt of {{a post:%1$s}}', 'WordPress', 'uncanny-automator' ), $this->get_action_meta() ) );
		$this->set_readable_sentence( esc_attr_x( 'Update the excerpt of {{a post}}', 'WordPress', 'uncanny-automator' ) );
	}

	/**
	 * @return array
	 */
	public function options() {

		return array(
			array(
				'option_code' => $this->get_action_meta(),
				'input_type'  => 'text',
				'label'       => esc_attr_x( 'Post', 'WordPress', 'uncanny-automator' ),
				'required'    => true,
			),
			array(
				'option_code' => 'WP_POST_EXCERPT',
				'input_type'  => 'textarea',
				'label'       => esc_attr_x( 'Excerpt', 'WordPress', 'uncanny-automator' ),
				'required'    => true,
			),
		);
	}

	/**
	 * @param int   $user_id
	 * @param array $action_data
	 * @param int   $recipe_id
	 * @param array $args
	 * @param       $parsed
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {
		$post_id      = absint( $parsed[ $this->get_action_meta() ] );
		$post_excerpt = $parsed['WP_POST_EXCERPT'];

		if ( is_null( get_post( $post_id ) ) ) {
			$this->add_log_error( esc_attr_x( 'Invalid post ID.', 'WordPress', 'uncanny-automator' ) );

			return false;
		}

		$post_data    = array(
			'ID'           => $post_id,
			'post_excerpt' => $post_excerpt,
		);
		$post_updated = wp_update_post( $post_data, true );

		if ( is_wp_error( $post_updated ) ) {
			$message = $post_updated->get_error_message();
			// translators: 1: Error message
			$this->add_log_error( sprintf( esc_attr_x( '(%s)', 'WordPress', 'uncanny-automator' ), $message ) );

			return false;
		}

		$this->hydrate_tokens(
			array(
				$this->get_action_meta() => get_the_title( $post_id ),
				'WP_POST_EXCERPT'        => $post_excerpt,
			)
		);

		return true;
	}

}
