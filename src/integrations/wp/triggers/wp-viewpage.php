<?php

namespace Uncanny_Automator;

/**
 * Class WP_VIEWPAGE
 * @package Uncanny_Automator
 */
class WP_VIEWPAGE {

	public static $integration = 'WP';

	private $trigger_code;
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

		global $uncanny_automator;

		$trigger = array(
			'author'              => $uncanny_automator->get_author_name( $this->trigger_code ),
			'support_link'        => $uncanny_automator->get_author_support_link( $this->trigger_code ),
			'integration'         => self::$integration,
			'code'                => $this->trigger_code,
			/* translators: Logged-in trigger - WordPress */
			'sentence'            => sprintf(  esc_attr__( 'A user views {{a page:%1$s}} {{a number of:%2$s}} time(s)', 'uncanny-automator' ), $this->trigger_meta, 'NUMTIMES' ),
			/* translators: Logged-in trigger - WordPress */
			'select_option_name'  =>  esc_attr__( 'A user views {{a page}}', 'uncanny-automator' ),
			'action'              => 'template_redirect',
			'priority'            => 90,
			'accepted_args'       => 1,
			'validation_function' => array( $this, 'view_page' ),
			'options'             => [
				$uncanny_automator->helpers->recipe->wp->options->all_pages(),
				$uncanny_automator->helpers->recipe->options->number_of_times(),
			],
		);

		$uncanny_automator->register->trigger( $trigger );

		return;
	}

	/**
	 * Validation function when the trigger action is hit
	 */
	public function view_page() {

		global $uncanny_automator, $post;

		if ( ! is_page() && ! is_archive() ) {
			return;
		}

		if ( is_user_logged_in() ) {
			$user_id = get_current_user_id();
			$args    = [
				'code'    => $this->trigger_code,
				'meta'    => $this->trigger_meta,
				'post_id' => $post->ID,
				'user_id' => $user_id,
			];

			if ( isset( $uncanny_automator->process ) && isset( $uncanny_automator->process->user ) && $uncanny_automator->process->user instanceof Automator_Recipe_Process_User ) {
				$arr = $uncanny_automator->process->user->maybe_add_trigger_entry( $args, false );
			} else {
				$arr = $uncanny_automator->maybe_add_trigger_entry( $args, false );
			}

			if ( $arr ) {
				foreach ( $arr as $result ) {
					if ( true === $result['result'] ) {
						if ( isset( $uncanny_automator->process ) && isset( $uncanny_automator->process->user ) && $uncanny_automator->process->user instanceof Automator_Recipe_Process_User ) {
							$uncanny_automator->process->user->maybe_trigger_complete( $result['args'] );
						} else {
							$uncanny_automator->maybe_trigger_complete( $result['args'] );

						}
					}
				}
			}
		}
	}
}
