<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName

namespace Uncanny_Automator;

/**
 * Class LP_COURSEENROLLED
 *
 * @package Uncanny_Automator
 */
class LP_COURSEENROLLED {

	/**
	 * Integration code
	 *
	 * @var string
	 */
	public static $integration = 'LP';

	private $trigger_code;
	private $trigger_meta;

	/**
	 * Set up Automator trigger constructor.
	 */
	public function __construct() {
		$this->trigger_code = 'LPCOURSEENROLLED';
		$this->trigger_meta = 'LPCOURSEEN';
		$this->define_trigger();
	}

	/**
	 * Define and register the trigger by pushing it into the Automator object
	 */
	public function define_trigger() {

		$trigger = array(
			'author'              => Automator()->get_author_name(),
			'support_link'        => Automator()->get_author_support_link( $this->trigger_code, 'integration/learnpress/' ),
			'integration'         => self::$integration,
			'code'                => $this->trigger_code,
			/* translators: Logged-in trigger - LearnPress */
			'sentence'            => sprintf( esc_attr__( 'A user is enrolled in {{a course:%1$s}} {{a number of:%2$s}} time(s)', 'uncanny-automator' ), $this->trigger_meta, 'NUMTIMES' ),
			/* translators: Logged-in trigger - LearnPress */
			'select_option_name'  => esc_attr__( 'A user is enrolled in {{a course}}', 'uncanny-automator' ),
			'action'              => 'learnpress/user/course-enrolled',
			'priority'            => 20,
			'accepted_args'       => 3,
			'validation_function' => array( $this, 'lp_course_enrolled' ),
			'options'             => array(
				Automator()->helpers->recipe->learnpress->options->all_lp_courses( esc_attr__( 'Course', 'uncanny-automator' ), $this->trigger_meta ),
				Automator()->helpers->recipe->options->number_of_times(),
			),
		);

		Automator()->register->trigger( $trigger );

	}

	/**
	 * Validation function when the trigger action is hit
	 *
	 * @param $order_id
	 * @param $course_id
	 * @param $user_id
	 */
	public function lp_course_enrolled( $order_id, $course_id, $user_id ) {

		if ( empty( $user_id ) ) {
			return;
		}

		$args = array(
			'code'    => $this->trigger_code,
			'meta'    => $this->trigger_meta,
			'post_id' => intval( $course_id ),
			'user_id' => $user_id,
		);

		Automator()->maybe_add_trigger_entry( $args );

	}
}
