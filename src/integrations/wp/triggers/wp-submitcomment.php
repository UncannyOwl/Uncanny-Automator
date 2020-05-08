<?php

namespace Uncanny_Automator;

/**
 * Class WP_SUBMITCOMMENT
 * @package uncanny_automator
 */
class WP_SUBMITCOMMENT {

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
		$this->trigger_code = 'WPSUBMITCOMMENT';
		$this->trigger_meta = 'WPPOSTCOMMENTS';
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
			'sentence'            => sprintf( __( 'A user submits a comment on {{a post:%1$s}} {{a number of:%2$s}} times', 'uncanny-automator' ), $this->trigger_meta, 'NUMTIMES' ),
			/* translators: Logged-in trigger - WordPress */
			'select_option_name'  => __( 'A user submits a comment on {{a post}}', 'uncanny-automator' ),
			'action'              => 'comment_post',
			'priority'            => 90,
			'accepted_args'       => 3,
			'validation_function' => array( $this, 'submitted_comment' ),
			'options'             => [
				$uncanny_automator->helpers->recipe->wp->options->all_posts( 'Post', 'WPPOSTCOMMENTS' ),
				$uncanny_automator->helpers->recipe->options->number_of_times(),
			],
		);

		$uncanny_automator->register->trigger( $trigger );

		return;
	}

	/**
	 * Validation function when the trigger action is hit
	 *
	 * @param int        $comment_id       The comment ID.
	 * @param int|string $comment_approved 1 if the comment is approved, 0 if not, 'spam' if spam.
	 * @param array      $commentdata      Comment data.
	 */
	public function submitted_comment( $comment_id, $comment_approved, $commentdata ) {

		global $uncanny_automator;

		$user_id = get_current_user_id();

		$args = [
			'code'    => $this->trigger_code,
			'meta'    => $this->trigger_meta,
			'post_id' => $commentdata['comment_post_ID'],
			'user_id' => $user_id,
		];

		$uncanny_automator->maybe_add_trigger_entry( $args );
	}
}
