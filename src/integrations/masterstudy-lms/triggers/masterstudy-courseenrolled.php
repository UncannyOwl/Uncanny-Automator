<?php

namespace Uncanny_Automator;

/**
 * Class MASTERSTUDY_COURSEENROLLED
 *
 * @package Uncanny_Automator
 */
class MASTERSTUDY_COURSEENROLLED {

	/**
	 * Integration code
	 *
	 * @var string
	 */
	public static $integration = 'MSLMS';

	private $trigger_code;
	private $trigger_meta;

	/**
	 * Set up Automator trigger constructor.
	 */
	public function __construct() {
		$this->trigger_code = 'MSLMSCOURSEENROLLED';
		$this->trigger_meta = 'MSLMSCOURSE';
		$this->define_trigger();
	}

	/**
	 * Define and register the trigger by pushing it into the Automator object
	 */
	public function define_trigger() {

		$args = array(
			'post_type'      => 'stm-courses',
			'posts_per_page' => 999,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'post_status'    => 'publish',
		);

		$options = Automator()->helpers->recipe->options->wp_query( $args, true, esc_attr__( 'Any course', 'uncanny-automator' ) );

		$trigger = array(
			'author'              => Automator()->get_author_name(),
			'support_link'        => Automator()->get_author_support_link( $this->trigger_code, 'integration/masterstudy-lms/' ),
			'integration'         => self::$integration,
			'code'                => $this->trigger_code,
			/* translators: Logged-in trigger - MasterStudy LMS */
			'sentence'            => sprintf( esc_attr__( 'A user is enrolled in {{a course:%1$s}}', 'uncanny-automator' ), $this->trigger_meta ),
			/* translators: Logged-in trigger - MasterStudy LMS */
			'select_option_name'  => esc_attr__( 'A user is enrolled in {{a course}}', 'uncanny-automator' ),
			'action'              => 'add_user_course',
			'priority'            => 20,
			'accepted_args'       => 2,
			'validation_function' => array( $this, 'course_enrolled' ),
			'options'             => array(
				array(
					'option_code'              => $this->trigger_meta,
					'label'                    => esc_attr_x( 'Course', 'MasterStudy LMS', 'uncanny-automator' ),
					'input_type'               => 'select',
					'required'                 => true,
					'options'                  => $options,
					'relevant_tokens'          => array(
						'MSLMSCOURSE'           => esc_attr__( 'Course title', 'uncanny-automator' ),
						'MSLMSCOURSE_ID'        => esc_attr__( 'Course ID', 'uncanny-automator' ),
						'MSLMSCOURSE_URL'       => esc_attr__( 'Course URL', 'uncanny-automator' ),
						'MSLMSCOURSE_THUMB_ID'  => esc_attr__( 'Course featured image ID', 'uncanny-automator' ),
						'MSLMSCOURSE_THUMB_URL' => esc_attr__( 'Course featured image URL', 'uncanny-automator' ),
					),
					'custom_value_description' => _x( 'Course ID', 'MasterStudy', 'uncanny-automator' ),
				),
			),
		);

		Automator()->register->trigger( $trigger );
	}

	/**
	 * Validation function when the trigger action is hit
	 *
	 * @param $data
	 */
	public function course_enrolled( $user_id, $course_id ) {

		$args = array(
			'code'    => $this->trigger_code,
			'meta'    => $this->trigger_meta,
			'post_id' => $course_id,
			'user_id' => $user_id,
		);

		Automator()->maybe_add_trigger_entry( $args );

	}
}
