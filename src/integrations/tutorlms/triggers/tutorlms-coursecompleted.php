<?php
/**
 * Contains Course Completion Trigger.
 *
 * @since 2.4.0
 * @version 2.4.0
 */

namespace Uncanny_Automator;

use function tutor;

defined( '\ABSPATH' ) || exit;

/**
 * Adds Course Completion as Trigger.
 *
 * @since 2.4.0
 */
class TUTORLMS_COURSECOMPLETED {

	public static $integration = 'TUTORLMS';

	private $trigger_code;
	private $trigger_meta;

	/**
	 * Constructor.
	 *
	 * @since 2.4.0
	 */
	public function __construct() {
		$this->trigger_code = 'TUTORLMSCOURSECOMPLETED';
		$this->trigger_meta = 'TUTORLMSCOURSE';

		// hook into automator.
		$this->define_trigger();
	}

	/**
	 * Registers Course Completion trigger.
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
			'sentence'            => sprintf( esc_attr__( 'A user completes {{a course:%1$s}} {{a number of:%2$s}} time(s)', 'uncanny-automator' ), $this->trigger_meta, 'NUMTIMES' ),
			/* translators: Logged-in trigger - TutorLMS */
			'select_option_name'  => esc_attr__( 'A user completes {{a course}}', 'uncanny-automator' ),
			'action'              => 'tutor_course_complete_after',
			'priority'            => 10,
			'accepted_args'       => 1,
			'validation_function' => array( $this, 'complete' ),
			// very last call in WP, we need to make sure they viewed the page and didn't skip before is was fully viewable
			'options'             => array(
				Automator()->helpers->recipe->tutorlms->options->all_tutorlms_courses( null, $this->trigger_meta, true, true ),
				Automator()->helpers->recipe->options->number_of_times(),
			),
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
		if ( ! $post instanceof \WP_Post ) {
			return;
		}

		// Is valid post?
		if ( ! is_object( $post ) ) {
			if ( isset( $_POST['course_id'] ) ) { //phpcs:ignore WordPress.Security.NonceVerification.Missing
				$course_id = (int) $_POST['course_id']; //phpcs:ignore WordPress.Security.NonceVerification.Missing
				$post      = get_post( $course_id ); //phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			}
		}

		// Is valid post?
		if ( ! is_object( $post ) ) {
			return;
		}

		// Is this the registered lesson post type
		if ( tutor()->course_post_type !== $post->post_type ) {
			return;
		}

		// current user.
		$user_id = get_current_user_id();

		// trigger entry args.
		$args = array(
			'code'    => $this->trigger_code,
			'meta'    => $this->trigger_meta,
			'post_id' => $post->ID,
			'user_id' => $user_id,
		);

		// run trigger.
		Automator()->maybe_add_trigger_entry( $args );
	}
}
