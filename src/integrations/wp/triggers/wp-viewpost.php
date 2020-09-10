<?php

namespace Uncanny_Automator;


/**
 * Class WP_VIEWPOST
 * @package Uncanny_Automator
 */
class WP_VIEWPOST {

	/**
	 * Integration code
	 * @var string
	 */
	public static $integration = 'WP';

	private $trigger_code;
	private $trigger_meta;

	/**
	 * Set up Automator trigger constructor.
	 */
	public function __construct() {
		$this->trigger_code = 'VIEWPOST';
		$this->trigger_meta = 'WPPOST';
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
			'sentence'            => sprintf(  esc_attr__( 'A user views {{a post:%1$s}} {{a number of:%2$s}} time(s)', 'uncanny-automator' ), $this->trigger_meta, 'NUMTIMES' ),
			/* translators: Logged-in trigger - WordPress */
			'select_option_name'  =>  esc_attr__( 'A user views {{a post}}', 'uncanny-automator' ),
			'action'              => 'template_redirect',
			'priority'            => 90,
			'accepted_args'       => 1,
			'validation_function' => array( $this, 'view_post' ),
			// very last call in WP, we need to make sure they viewed the post and didn't skip before is was fully viewable
			'options'             => [
				$uncanny_automator->helpers->recipe->wp->options->all_posts(),
				$uncanny_automator->helpers->recipe->options->number_of_times(),
			],
		);

		$uncanny_automator->register->trigger( $trigger );

		return;
	}

	/**
	 * Validation function when the trigger action is hit
	 */
	public function view_post() {

		global $uncanny_automator;

		global $post;
		$user_id = get_current_user_id();

		if ( ! is_single() ) {
			return;
		}

		$args = [
			'code'    => $this->trigger_code,
			'meta'    => $this->trigger_meta,
			'post_id' => $post->ID,
			'user_id' => $user_id,
		];

		$uncanny_automator->maybe_add_trigger_entry( $args );
	}
}
