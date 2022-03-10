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

		$course_tokens = array();

		$relevant_tokens = array(
			$this->trigger_meta                => esc_attr__( 'Lesson title', 'uncanny-automator' ),
			$this->trigger_meta . '_ID'        => esc_attr__( 'Lesson ID', 'uncanny-automator' ),
			$this->trigger_meta . '_URL'       => esc_attr__( 'Lesson URL', 'uncanny-automator' ),
			$this->trigger_meta . '_THUMB_ID'  => esc_attr__( 'Lesson featured image ID', 'uncanny-automator' ),
			$this->trigger_meta . '_THUMB_URL' => esc_attr__( 'Lesson featured image URL', 'uncanny-automator' ),
		);

		$args = array(
			'post_type'      => tutor()->course_post_type,
			'posts_per_page' => 999,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'post_status'    => 'publish',
		);

		$courses = Automator()->helpers->recipe->options->wp_query( $args, false, esc_attr__( 'Any course', 'uncanny-automator' ) );

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
			'options'             => array(
				Automator()->helpers->recipe->options->number_of_times(),
			),
			'options_group'       => array(
				$this->trigger_meta => array(
					Automator()->helpers->recipe->field->select_field_ajax(
						'TUTORLMSCOURSE',
						esc_attr__( 'Course', 'uncanny-automator' ),
						$courses,
						'',
						'',
						false,
						true,
						array(
							'target_field' => $this->trigger_meta,
							'endpoint'     => 'select_lesson_from_course_LESSONCOMPLETED',
						),
						$course_tokens
					),
					Automator()->helpers->recipe->field->select_field( $this->trigger_meta, esc_attr__( 'Lesson', 'uncanny-automator' ), array(), false, false, false, $relevant_tokens ),
				),
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

		// Is this the registered lesson post type
		if ( tutor()->lesson_post_type !== $post->post_type ) {
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
