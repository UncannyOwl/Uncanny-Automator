<?php

namespace Uncanny_Automator;

/**
 *
 */
class WP_POST_PUBLISHED {

	use Recipe\Triggers;

	/**
	 *
	 */
	const INTEGRATION = 'WP';

	/**
	 *
	 */
	const TRIGGER_CODE = 'WP_POST_PUBLISHED';

	/**
	 *
	 */
	const TRIGGER_META = 'WPPOSTTYPES';

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

		$this->set_integration( self::INTEGRATION );

		$this->set_trigger_code( self::TRIGGER_CODE );

		$this->set_trigger_meta( self::TRIGGER_META );

		$this->set_trigger_type( 'anonymous' );

		$this->set_is_login_required( false );

		$this->set_support_link( Automator()->get_author_support_link( self::TRIGGER_CODE, 'integration/wordpress-core/' ) );

		$this->set_sentence(
		/* Translators: Trigger sentence */
			sprintf( esc_html__( 'A {{type of post:%1$s}} is published', 'uncanny-automator' ), $this->get_trigger_meta() )
		);

		$this->set_readable_sentence( esc_attr__( 'A post is published', 'uncanny-automator' ) );

		$this->set_action_hook( 'wp_after_insert_post' );

		$this->set_action_args_count( 4 );

		$this->set_options_callback( array( $this, 'load_options' ) );

		//$this->set_trigger_autocomplete( false );

		$this->register_trigger();

	}

	/**
	 * @return array
	 */
	public function load_options() {

		$post_types = Automator()->helpers->recipe->wp->options->all_post_types(
			esc_html__( 'Post type', 'uncanny-automator' ),
			$this->get_trigger_meta(),
			array(
				'token'               => false,
				'use_zero_as_default' => true,
			//              'default_value'       => 'post',
			)
		);

		return Automator()->utilities->keep_order_of_options(
			array(
				'options_group' => array(
					$this->get_trigger_meta() => array(
						$post_types,
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

		$matching_recipes_triggers = $this->find_all( $this->trigger_recipes() )
										  ->where( array( $this->get_trigger_meta() ) )
										  ->match( array( $wp_post->post_type ) )
										  ->format( array( 'trim' ) )
										  ->get();

		return $matching_recipes_triggers;

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
	 * Also fire the trigger for users that are logged-in.
	 *
	 * @param array $args The trigger args.
	 *
	 * @return bool
	 */
	public function do_continue_anon_trigger( ...$args ) {

		return true;

	}
}
