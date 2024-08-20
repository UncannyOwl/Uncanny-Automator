<?php

namespace Uncanny_Automator;

/**
 * Class WP_ANON_UPDATES_POST
 *
 * @package Uncanny_Automator
 */
class WP_ANON_UPDATES_POST {

	use Recipe\Triggers;

	/**
	 * Set up Automator trigger constructor.
	 *
	 * @throws Automator_Exception
	 */
	public function __construct() {

		$this->setup_trigger();

	}

	/**
	 * Setup basic trigger properties.
	 *
	 * @return void.
	 * @throws Automator_Exception
	 */
	public function setup_trigger() {
		$this->set_integration( 'WP' );
		$this->set_trigger_code( 'WP_ANON_POST_UPDATED' );
		$this->set_trigger_meta( 'WPPOSTTYPES' );
		$this->set_is_login_required( false );
		$this->set_trigger_type( 'anonymous' );
		$this->set_support_link( Automator()->get_author_support_link( $this->get_trigger_code(), 'integration/wordpress-core/' ) );
		$this->set_sentence(
		/* Translators: Trigger sentence - WordPress */
			sprintf( esc_html_x( '{{A type of post:%1$s}} is updated', 'WordPress', 'uncanny-automator' ), $this->get_trigger_meta() )
		);
		$this->set_readable_sentence( esc_attr_x( 'A post is updated', 'WordPress', 'uncanny-automator' ) );
		$this->set_action_hook( 'post_updated' );
		$this->set_action_args_count( 3 );
		$this->set_options_callback( array( $this, 'load_options' ) );
		$this->set_loopable_tokens( Wp_Helpers::common_trigger_loopable_tokens() );
		$this->register_trigger();

	}

	/**
	 * @return array
	 */
	public function load_options() {
		return Automator()->utilities->keep_order_of_options(
			array(
				'options' => array(
					Automator()->helpers->recipe->wp->options->all_post_types(
						esc_html_x( 'Post type', 'WordPress', 'uncanny-automator' ),
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

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return false;
		}

		list( $post_id, $wp_post_after, $wp_post_before ) = $args[0];

		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			if ( apply_filters( 'automator_wp_post_updates_prevent_trigger_on_rest_requests', true, $post_id ) ) {
				return;
			}
		}

		// Prevent if publishing a post.
		if ( 'publish' === $wp_post_after->post_status && 'publish' !== $wp_post_before->post_status ) {
			return false;
		}

		$ignore_statuses = apply_filters(
			'automator_wp_post_updates_ignore_statuses',
			array(
				'trash',
				'draft',
				'future',
			),
			$post_id,
			$wp_post_after,
			$wp_post_before
		);

		// Prevent if the status is excluded
		if ( in_array( $wp_post_after->post_status, $ignore_statuses, true ) ) {
			return false;
		}

		$include_non_public_posts = apply_filters( 'automator_wp_post_updates_include_non_public_posts', false, $post_id );
		if ( false === $include_non_public_posts ) {
			$__object = get_post_type_object( $wp_post_after->post_type );
			if ( false === $__object->public ) {
				return false;
			}
		}

		if ( empty( $post_id ) ) {
			return false;
		}

		return apply_filters( 'automator_wp_post_updates_post_updated', true, $post_id, $wp_post_after, $wp_post_before );
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

	/**
	 * Validate if the selected field post type equals the one in the action hook.
	 *
	 * @param array $args The action hook args.
	 *
	 * @return array The matching recipes and triggers.
	 */
	public function validate_conditions( ...$args ) {

		list( $post_id, $wp_post_after, $wp_post_before ) = $args[0];

		return $this->find_all( $this->trigger_recipes() )
					->where( array( $this->get_trigger_meta() ) )
					->match( array( $wp_post_after->post_type ) )
					->format( array( 'trim' ) )
					->get();
	}

	/**
	 * @param ...$args
	 *
	 * @return bool
	 */
	public function do_continue_anon_trigger( ...$args ) {
		return true;
	}
}
