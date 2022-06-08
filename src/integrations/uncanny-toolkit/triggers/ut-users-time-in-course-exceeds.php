<?php

namespace Uncanny_Automator;

/**
 * Uncanny Toolkit: Trigger - A Group Leader is imported to {{a LearnDash
 * Group}}
 */
class UT_USERS_TIME_IN_COURSE_EXCEEDS {

	/**
	 * Integration code
	 *
	 * @var string
	 */
	public static $integration = 'UNCANNYTOOLKIT';

	/**
	 * Trigger Code
	 *
	 * @var string
	 */
	private $trigger_code;
	/**
	 * Trigger Meta
	 *
	 * @var string
	 */
	private $trigger_meta;

	/**
	 * Set up Automator trigger constructor.
	 */
	public function __construct() {
		if ( ! defined( 'UNCANNY_TOOLKIT_PRO_VERSION' ) ) {
			return;
		}
		$this->trigger_code = 'UTUSERSTIMEINCOURSEEXCEEDS';
		$this->trigger_meta = 'UOUSERSTIMEINCOURSEEXCEEDS';
		$this->define_trigger();
	}

	/**
	 * Define and register the trigger by pushing it into the Automator object
	 */
	public function define_trigger() {

		$trigger = array(
			'author'              => Automator()->get_author_name( $this->trigger_code ),
			'support_link'        => Automator()->get_author_support_link( $this->trigger_code, 'integration/uncanny-toolkit/' ),
			'integration'         => self::$integration,
			'code'                => $this->trigger_code,
			'meta'                => $this->trigger_meta,
			/* translators: Logged-in trigger - Uncanny Toolkit */
			'sentence'            => sprintf( esc_attr__( "A user's time in {{a course:%1\$s}} exceeds {{a specific number of:%2\$s}} minutes", 'uncanny-automator' ), $this->trigger_meta, $this->trigger_meta . '_COURSEMINUTES' ),
			/* translators: Logged-in trigger - Uncanny Toolkit */
			'select_option_name'  => esc_attr__( "A user's time in {{a course}} exceeds {{a specific number of}} minutes", 'uncanny-automator' ),
			'action'              => 'uo_course_timer_add_timer',
			'priority'            => 20,
			'accepted_args'       => 3,
			'validation_function' => array( $this, 'users_limit_exceeds' ),
			'options_callback'    => array( $this, 'load_options' ),
		);

		Automator()->register->trigger( $trigger );
	}

	/**
	 * load_options
	 */
	public function load_options() {

		$all_courses = Automator()->helpers->recipe->learndash->options->get_all_ld_courses( 'Course', $this->trigger_meta );

		$minutes_args = array(
			'option_code' => $this->trigger_meta . '_COURSEMINUTES',
			'input_type'  => 'text',
			'label'       => esc_attr__( 'Minutes', 'uncanny-automator' ),
			'required'    => true,
			'token_name'  => __( 'Time in minutes', 'uncanny-automator' ),
		);

		$options = array(
			'options' => array(
				$all_courses,
				Automator()->helpers->recipe->field->text( $minutes_args ),
			),
		);

		return Automator()->utilities->keep_order_of_options( $options );
	}

	/**
	 * Running an actual function on the trigger
	 *
	 * @param $user_id
	 * @param $csv_data
	 * @param $csv_header
	 * @param $key_location
	 */
	public function users_limit_exceeds( $course_ID, $post_ID, $timer_interval ) {
		if ( ! is_numeric( $course_ID ) ) {
			return;
		}

		$recipes = Automator()->get->recipes_from_trigger_code( $this->trigger_code );

		if ( empty( $recipes ) ) {
			return;
		}

		$required_course = Automator()->get->meta_from_recipes( $recipes, $this->trigger_meta );
		$course_minutes  = Automator()->get->meta_from_recipes( $recipes, $this->trigger_meta . '_COURSEMINUTES' );
		if ( empty( $required_course ) ) {
			return;
		}

		$matched_recipe_ids = array();
		$user_id            = get_current_user_id();

		foreach ( $recipes as $recipe_id => $recipe ) {
			foreach ( $recipe['triggers'] as $trigger ) {
				$trigger_id = $trigger['ID'];

				if ( ! method_exists( '\uncanny_pro_toolkit\CourseTimer', 'get_course_time_in_seconds' ) && ! class_exists( '\uncanny_pro_toolkit\CourseTimer' ) ) {
					$error_message                       = esc_html__( 'A required method is not available. Please update Uncanny Toolkit Pro to the latest version.', 'uncanny-automator' );
					$action_data['do-nothing']           = true;
					$action_data['complete_with_errors'] = true;
					Automator()->complete_action( $user_id, $action_data, $recipe_id, $error_message );

					return;
				}
				$timer = \uncanny_pro_toolkit\CourseTimer::get_course_time_in_seconds( $course_ID, $user_id );

				if ( intval( $timer ) > ( intval( $course_minutes[ $recipe_id ][ $trigger_id ] ) * 60 ) ) {
					$matched_recipe_ids[] = array(
						'recipe_id'  => $recipe_id,
						'trigger_id' => $trigger_id,
					);
				}
			}
		}

		if ( empty( $matched_recipe_ids ) ) {
			return;
		}

		foreach ( $matched_recipe_ids as $matched_recipe_id ) {
			$recipe_id  = $matched_recipe_id['recipe_id'];
			$trigger_id = $matched_recipe_id['trigger_id'];//return early for all products
			if ( ! isset( $required_course[ $recipe_id ] ) ) {
				continue;
			}
			if ( ! isset( $required_course[ $recipe_id ][ $trigger_id ] ) ) {
				continue;
			}
			if ( intval( '-1' ) === intval( $required_course[ $recipe_id ][ $trigger_id ] ) || (int) $required_course[ $recipe_id ][ $trigger_id ] === (int) $course_ID ) {

				$pass_args = array(
					'code'             => $this->trigger_code,
					'meta'             => $this->trigger_meta,
					'ignore_post_id'   => true,
					'user_id'          => $user_id,
					'is_signed_in'     => true,
					'recipe_to_match'  => $recipe_id,
					'trigger_to_match' => $trigger_id,
				);

				$args = Automator()->maybe_add_trigger_entry( $pass_args, false );

				if ( $args ) {
					foreach ( $args as $result ) {
						if ( true === $result['result'] ) {

							// Add token for course title
							Automator()->insert_trigger_meta(
								array(
									'user_id'        => $user_id,
									'trigger_id'     => $result['args']['trigger_id'],
									'meta_key'       => $this->trigger_meta,
									'meta_value'     => get_the_title( $course_ID ),
									'trigger_log_id' => $result['args']['get_trigger_id'],
									'run_number'     => $result['args']['run_number'],
								)
							);

							// Add token for course id
							Automator()->insert_trigger_meta(
								array(
									'user_id'        => $user_id,
									'trigger_id'     => $result['args']['trigger_id'],
									'meta_key'       => $this->trigger_meta . '_ID',
									'meta_value'     => $course_ID,
									'trigger_log_id' => $result['args']['get_trigger_id'],
									'run_number'     => $result['args']['run_number'],
								)
							);

							// Add token for course spent minutes
							Automator()->insert_trigger_meta(
								array(
									'user_id'        => $user_id,
									'trigger_id'     => $result['args']['trigger_id'],
									'meta_key'       => $this->trigger_meta . '_COURSEMINUTES',
									'meta_value'     => ( isset( $course_minutes[ $recipe_id ][ $trigger_id ] ) ) ? $course_minutes[ $recipe_id ][ $trigger_id ] : 0,
									'trigger_log_id' => $result['args']['get_trigger_id'],
									'run_number'     => $result['args']['run_number'],
								)
							);

							// Add token for course url
							Automator()->insert_trigger_meta(
								array(
									'user_id'        => $user_id,
									'trigger_id'     => $result['args']['trigger_id'],
									'meta_key'       => $this->trigger_meta . '_URL',
									'meta_value'     => get_the_permalink( $course_ID ),
									'trigger_log_id' => $result['args']['get_trigger_id'],
									'run_number'     => $result['args']['run_number'],
								)
							);

							// Add token for course url
							Automator()->insert_trigger_meta(
								array(
									'user_id'        => $user_id,
									'trigger_id'     => $result['args']['trigger_id'],
									'meta_key'       => $this->trigger_meta . '_THUMB_ID',
									'meta_value'     => get_post_thumbnail_id( $course_ID ),
									'trigger_log_id' => $result['args']['get_trigger_id'],
									'run_number'     => $result['args']['run_number'],
								)
							);

							// Add token for course url
							Automator()->insert_trigger_meta(
								array(
									'user_id'        => $user_id,
									'trigger_id'     => $result['args']['trigger_id'],
									'meta_key'       => $this->trigger_meta . '_THUMB_URL',
									'meta_value'     => get_the_post_thumbnail_url( $course_ID ),
									'trigger_log_id' => $result['args']['get_trigger_id'],
									'run_number'     => $result['args']['run_number'],
								)
							);

							Automator()->maybe_trigger_complete( $result['args'] );
						}
					}
				}
			}
		}
	}

}
