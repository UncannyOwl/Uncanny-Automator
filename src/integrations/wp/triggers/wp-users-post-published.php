<?php

namespace Uncanny_Automator;

/**
 * Class WP_USERS_POST_PUBLISHED
 *
 * @package Uncanny_Automator
 */
class WP_USERS_POST_PUBLISHED {

	use Recipe\Triggers;

	/**
	 * Set up Automator trigger constructor
	 */
	public function __construct() {
		$this->setup_trigger();
	}

	/**
	 * Setup basic trigger properties.
	 *
	 * @return void
	 * @throws Automator_Exception
	 */
	public function setup_trigger() {
		$this->set_integration( 'WP' );
		$this->set_trigger_code( 'WP_USER_POST_PUBLISHED' );
		$this->set_trigger_meta( 'WPPOSTTYPES' );
		$this->set_is_login_required( true );
		$this->set_support_link( Automator()->get_author_support_link( $this->get_trigger_code(), 'integration/wordpress-core/' ) );
		/* Translators: Trigger sentence - WordPress */
		$this->set_sentence( sprintf( esc_html_x( 'A user publishes a {{type of post:%1$s}}', 'WordPress', 'uncanny-automator' ), $this->get_trigger_meta() ) );
		$this->set_readable_sentence( esc_attr_x( 'A user publishes a post', 'WordPress', 'uncanny-automator' ) );
		$this->set_action_hook( 'wp_after_insert_post' );
		$this->set_action_args_count( 4 );
		$this->set_options_callback( array( $this, 'load_options' ) );
		$this->register_trigger();

	}

	/**
	 * Add Check for Scheduled Post
	 * Scheduled posts are run w/o a logged in user.
	 *
	 * @param array $args
	 *
	 * @return bool
	 */
	public function is_user_logged_in_required( $args ) {

		// Bail already logged in.
		if ( is_user_logged_in() ) {
			return true;
		}

		list( $post_id, $wp_post, $update, $wp_post_before ) = $args;

		// Ensure we have a post before object.
		if ( ! is_object( $wp_post_before ) ) {
			return true;
		}
		// This is a Scheduled Post.
		if ( 'future' === $wp_post_before->post_status && 'publish' === $wp_post->post_status ) {
			$this->set_user_id( $wp_post->post_author );
			return false;
		}

		return true;
	}

	/**
	 * @return array
	 */
	public function load_options() {

		return Automator()->utilities->keep_order_of_options(
			array(
				'options' => array(
					Automator()->helpers->recipe->wp->options->all_post_types(
						esc_html_x( 'Post type', 'wordpress', 'uncanny-automator' ),
						$this->get_trigger_meta(),
						array(
							'token'               => false,
							'use_zero_as_default' => intval( '-1' ),
						)
					),
				),
			)
		);
	}

	/**
	 * Validate the trigger.
	 *
	 * @param array $args The action hook args.
	 *
	 * @return bool
	 */
	protected function validate_trigger( ...$args ) {
		list( $post_id, $wp_post, $update, $wp_post_before ) = $args[0];
		// only run when posts
		// are published first time

		return Automator()->utilities->is_wp_post_being_published( $wp_post, $wp_post_before );
	}

	/**
	 * Validate if the selected field post type equals the one in the action hook.
	 *
	 * @param array $args The action hook args.
	 *
	 * @return array The matching recipes and triggers.
	 */
	public function validate_conditions( ...$args ) {
		list( $post_id, $wp_post, $update, $wp_post_before ) = $args[0];

		return $this->find_all( $this->trigger_recipes() )
					->where( array( $this->get_trigger_meta() ) )
					->match( array( $wp_post->post_type ) )
					->format( array( 'trim' ) )
					->get();
	}

	/**
	 * Prepare to run the trigger.
	 *
	 * @param array $data
	 *
	 * @return void
	 */
	public function prepare_to_run( $data ) {
		$this->set_conditional_trigger( true );
	}

}
