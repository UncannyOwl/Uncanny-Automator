<?php

namespace Uncanny_Automator;

/**
 * Class WP_VIEWPAGE
 *
 * @package Uncanny_Automator
 */
class WP_VIEWPAGE {

	/**
	 * @var string
	 */
	public static $integration = 'WP';

	/**
	 * @var string
	 */
	private $trigger_code;

	/**
	 * @var string
	 */
	private $trigger_meta;

	/**
	 * Set up Automator trigger constructor.
	 */
	public function __construct() {
		$this->trigger_code = 'VIEWPAGE';
		$this->trigger_meta = 'WPPAGE';
		$this->define_trigger();
	}

	/**
	 * Define and register the trigger by pushing it into the Automator object
	 */
	public function define_trigger() {

		$trigger = array(
			'author'              => Automator()->get_author_name( $this->trigger_code ),
			'support_link'        => Automator()->get_author_support_link( $this->trigger_code, 'integration/wordpress-core/' ),
			'integration'         => self::$integration,
			'code'                => $this->trigger_code,
			/* translators: Logged-in trigger - WordPress */
			'sentence'            => sprintf( esc_attr__( 'A user views {{a page:%1$s}} {{a number of:%2$s}} time(s)', 'uncanny-automator' ), $this->trigger_meta, 'NUMTIMES' ),
			/* translators: Logged-in trigger - WordPress */
			'select_option_name'  => esc_attr__( 'A user views {{a page}}', 'uncanny-automator' ),
			'action'              => 'template_redirect',
			'priority'            => 90,
			'accepted_args'       => 1,
			'validation_function' => array( $this, 'view_page' ),
			'options_callback'    => array( $this, 'load_options' ),
		);

		Automator()->register->trigger( $trigger );
	}

	/**
	 * load_options
	 *
	 * @return array
	 */
	public function load_options() {
		
		Automator()->helpers->recipe->wp->options->load_options = true;
		
		$options = Automator()->utilities->keep_order_of_options(
			array(
				'options' => array(
					Automator()->helpers->recipe->wp->options->all_pages(),
					Automator()->helpers->recipe->options->number_of_times(),
				),
			)
		);

		return $options;
	}

	/**
	 * Validation function when the trigger action is hit
	 */
	public function view_page() {

		global $post;

		// Bail out if the page is not of a post type 'page'.
		if ( ! is_singular( 'page' ) ) {
			return;
		}

		// Bail out if post id is null.
		if ( ! isset( $post->ID ) ) {
			return;
		}

		// Return if post id is zero or empty. Some plugins like BuddyPress overwrites post id.
		if ( empty( $post->ID ) ) {
			return;
		}

		if ( ! is_user_logged_in() ) {
			return;
		}
		$user_id = get_current_user_id();
		$args    = array(
			'code'    => $this->trigger_code,
			'meta'    => $this->trigger_meta,
			'post_id' => $post->ID,
			'user_id' => $user_id,
		);

		$arr = Automator()->process->user->maybe_add_trigger_entry( $args, false );

		if ( $arr ) {
			foreach ( $arr as $result ) {
				if ( true === $result['result'] ) {
					Automator()->process->user->maybe_trigger_complete( $result['args'] );
				}
			}
		}
	}
}
