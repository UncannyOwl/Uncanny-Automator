<?php

namespace Uncanny_Automator;

/**
 * Class WP_USER_UPDATES_POST
 *
 * @package Uncanny_Automator
 */
class WP_USER_UPDATES_POST {

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
		$this->set_trigger_code( 'WP_USER_POST_UPDATED' );
		$this->set_trigger_meta( 'WPPOSTTYPES' );
		$this->set_is_login_required( true );
		$this->set_support_link( Automator()->get_author_support_link( $this->get_trigger_code(), 'integration/wordpress-core/' ) );
		$this->set_sentence(
		/* Translators: Trigger sentence - WordPress */
			sprintf( esc_html_x( 'A user updates {{a type of post:%1$s}}', 'WordPress', 'uncanny-automator' ), $this->get_trigger_meta() )
		);
		$this->set_readable_sentence( esc_attr_x( 'A user updates {{a type of post}}', 'WordPress', 'uncanny-automator' ) );
		$this->set_action_hook( 'post_updated' );
		$this->set_action_args_count( 3 );
		$this->set_options_callback( array( $this, 'load_options' ) );
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
		$include_non_public_posts                         = apply_filters( 'automator_wp_post_updates_include_non_public_posts', false, $post_id );
		if ( false === $include_non_public_posts ) {
			$__object = get_post_type_object( $wp_post_after->post_type );
			if ( false === $__object->public ) {
				return false;
			}
		}

		return ! empty( $post_id );
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

}
