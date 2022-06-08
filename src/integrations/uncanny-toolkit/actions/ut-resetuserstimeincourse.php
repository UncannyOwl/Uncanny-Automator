<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName

namespace Uncanny_Automator;

use uncanny_pro_toolkit;

/**
 * Class UT_RESETUSERSTIMEINCOURSE
 *
 * @package Uncanny_Automator
 */
class UT_RESETUSERSTIMEINCOURSE {

	/**
	 * Integration code
	 *
	 * @var string
	 */
	public static $integration = 'UNCANNYTOOLKIT';

	/**
	 * Action code
	 *
	 * @var string
	 */
	private $action_code;
	/**
	 * Action meta
	 *
	 * @var string
	 */
	private $action_meta;

	/**
	 * Set up Automator action constructor.
	 */
	public function __construct() {
		if ( ! defined( 'UNCANNY_TOOLKIT_PRO_VERSION' ) ) {
			return;
		}

		$this->action_code = 'RESETUSERSTIMEINCOURSE';
		$this->action_meta = 'UTRESETUSERSTIMEINCOURSE';
		$this->define_action();
	}

	/**
	 * Define and register the action by pushing it into the Automator object
	 */
	public function define_action() {

		$action = array(
			'author'             => Automator()->get_author_name( $this->action_code ),
			'support_link'       => Automator()->get_author_support_link( $this->action_code, 'integration/uncanny-toolkit/' ),
			'integration'        => self::$integration,
			'code'               => $this->action_code,
			/* translators: Logged-in action - Uncanny Groups */
			'sentence'           => sprintf( esc_attr__( "Reset a user's time in {{a course:%1\$s}}", 'uncanny-automator' ), $this->action_meta ),
			/* translators: Logged-in action - Uncanny Groups */
			'select_option_name' => esc_attr__( "Reset a user's time in {{a course}}", 'uncanny-automator' ),
			'priority'           => 10,
			'accepted_args'      => 1,
			'execution_function' => array( $this, 'process_action' ),
			'options_callback'   => array( $this, 'load_options' ),
		);

		Automator()->register->action( $action );
	}

	/**
	 * Load options
	 *
	 * @return array
	 */
	public function load_options() {

		$all_courses = Automator()->helpers->recipe->learndash->options->get_all_ld_courses( 'Course', $this->action_meta, false );
		$options     = array(
			'options' => array(
				$all_courses,
			),
		);

		return Automator()->utilities->keep_order_of_options( $options );
	}

	/**
	 * Validation function when the trigger action is hit
	 *
	 * @param $user_id
	 * @param $action_data
	 * @param $recipe_id
	 */
	public function process_action( $user_id, $action_data, $recipe_id, $args ) {

		$active_modules = get_option( 'uncanny_toolkit_active_classes', true );
		if ( ! isset( $active_modules['uncanny_pro_toolkit\CourseTimer'] ) && empty( $active_modules['uncanny_pro_toolkit\CourseTimer'] ) ) {
			$error_message                       = esc_html__( 'Simple course timer module is not active.', 'uncanny-automator' );
			$action_data['do-nothing']           = true;
			$action_data['complete_with_errors'] = true;
			Automator()->complete_action( $user_id, $action_data, $recipe_id, $error_message );
			return;
		}

		$ut_course_id = Automator()->parse->text( $action_data['meta'][ $this->action_meta ], $recipe_id, $user_id, $args );
		if ( empty( $ut_course_id ) ) {
			$error_message                       = esc_html__( 'The selected course is not found.', 'uncanny-automator' );
			$action_data['do-nothing']           = true;
			$action_data['complete_with_errors'] = true;
			Automator()->complete_action( $user_id, $action_data, $recipe_id, $error_message );

			return;
		}

		if ( ! class_exists( '\uncanny_pro_toolkit\CourseTimer' ) ) {
			$error_message                       = esc_html__( 'Simple course timer module is not active.', 'uncanny-automator' );
			$action_data['do-nothing']           = true;
			$action_data['complete_with_errors'] = true;
			Automator()->complete_action( $user_id, $action_data, $recipe_id, $error_message );

			return;
		}
		if ( ! method_exists( '\uncanny_pro_toolkit\CourseTimer', 'delete_user_course_data' ) ) {
			$error_message                       = esc_html__( 'A required method is not available. Please update Uncanny Toolkit Pro to the latest version.', 'uncanny-automator' );
			$action_data['do-nothing']           = true;
			$action_data['complete_with_errors'] = true;
			Automator()->complete_action( $user_id, $action_data, $recipe_id, $error_message );

			return;
		}

		uncanny_pro_toolkit\CourseTimer::delete_user_course_data( $user_id, $ut_course_id );

		Automator()->complete_action( $user_id, $action_data, $recipe_id );
	}

}
