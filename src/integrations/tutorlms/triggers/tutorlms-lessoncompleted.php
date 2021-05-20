<?php
/**
 * Contains Lesson Completion Trigger.
 *
 * @version 2.4.0
 * @since   2.4.0
 */

namespace Uncanny_Automator;

use function tutor;

defined( '\ABSPATH' ) || exit;

/**
 * Adds Lesson Completion as Trigger.
 *
 * @since 2.4.0
 */
class TUTORLMS_LESSONCOMPLETED {

	public static $integration = 'TUTORLMS';

	private $trigger_code;
	private $trigger_meta;

	/**
	 * Constructor.
	 *
	 * @since 2.4.0
	 */
	public function __construct() {
		$this->trigger_code = 'TUTORLMSLESSONCOMPLETED';
		$this->trigger_meta = 'TUTORLMSLESSON';

		// hook into automator.
		$this->define_trigger();
	}

	/**
	 * Registers Lesson Completion trigger.
	 *
	 * @since 2.4.0
	 */
	public function define_trigger() {

		// global automator object.


		// setup trigger configuration.
		$trigger = array(
			'author'              => Automator()->get_author_name( $this->trigger_code ),
			'support_link'        => Automator()->get_author_support_link( $this->trigger_code, 'integration/tutor-lms/' ),
			'integration'         => self::$integration,
			'code'                => $this->trigger_code,
			/* translators: Logged-in trigger - TutorLMS */
			'sentence'            => sprintf( esc_attr__( 'A user completes {{a lesson:%1$s}} {{a number of:%2$s}} time(s)', 'uncanny-automator' ), $this->trigger_meta, 'NUMTIMES' ),
			/* translators: Logged-in trigger - TutorLMS */
			'select_option_name'  => esc_attr__( 'A user completes {{a lesson}}', 'uncanny-automator' ),
			'action'              => 'tutor_lesson_completed_after',
			'priority'            => 10,
			'accepted_args'       => 1,
			'validation_function' => array( $this, 'complete' ),
			// very last call in WP, we need to make sure they viewed the page and didn't skip before is was fully viewable
			'options'             => [
				Automator()->helpers->recipe->tutorlms->options->all_tutorlms_lessons( null, $this->trigger_meta, true ),
				Automator()->helpers->recipe->options->number_of_times(),
			],
		);

		Automator()->register->trigger( $trigger );
	}

	/**
	 * Validates Trigger.
	 *
	 * @since 2.4.0
	 */
	public function complete() {

		// global post object.
		global $post;

		// Is this the registered lesson post type
		if ( tutor()->lesson_post_type !== $post->post_type ) {
			return;
		}

		// current user.
		$user_id = get_current_user_id();

		// trigger entry args.
		$args = [
			'code'    => $this->trigger_code,
			'meta'    => $this->trigger_meta,
			'post_id' => $post->ID,
			'user_id' => $user_id,
		];



		// run trigger.
		Automator()->maybe_add_trigger_entry( $args );
	}
}
