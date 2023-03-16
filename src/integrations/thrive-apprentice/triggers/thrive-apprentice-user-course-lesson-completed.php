<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
namespace Uncanny_Automator;

use Uncanny_Automator\Recipe;

/**
 * Class THRIVE_APPRENTICE_USER_COURSE_LESSON_COMPLETED
 *
 * @package Uncanny_Automator
 */
class THRIVE_APPRENTICE_USER_COURSE_LESSON_COMPLETED {

	use Recipe\Triggers;

	/**
	 * Constant TRIGGER_CODE.
	 *
	 * @var string
	 */
	const TRIGGER_CODE = 'THRIVE_APPRENTICE_USER_COURSE_LESSON_COMPLETED';

	/**
	 * Constant TRIGGER_META.
	 *
	 * @var string
	 */
	const TRIGGER_META = 'THRIVE_APPRENTICE_USER_COURSE_LESSON_COMPLETED_META';

	public function __construct() {

		$this->set_helper( new Thrive_Apprentice_Helpers( false ) );

		$this->setup_trigger();

	}

	/**
	 * Define and register the trigger by pushing it into the Automator object.
	 *
	 * @return void.
	 */
	public function setup_trigger() {

		$this->set_integration( 'THRIVE_APPRENTICE' );

		$this->set_trigger_code( self::TRIGGER_CODE );

		$this->set_trigger_meta( self::TRIGGER_META );

		$this->set_is_pro( false );

		$this->set_is_login_required( true );

		// The action hook to attach this trigger into.
		$this->add_action( 'thrive_apprentice_lesson_complete' );

		// The number of arguments that the action hook accepts.
		$this->set_action_args_count( 2 );

		$this->set_sentence(
			sprintf(
				/* Translators: Trigger sentence */
				esc_html__( 'A user completes {{a lesson:%1$s}} in {{a course:%2$s}}', 'uncanny-automator' ),
				$this->get_trigger_meta(),
				'COURSE:' . $this->get_trigger_meta()
			)
		);

		$this->set_readable_sentence(
			/* Translators: Trigger sentence */
			esc_html__( 'A user completes {{a lesson}} in {{a course}}', 'uncanny-automator' )
		);

		$this->set_options_callback( array( $this, 'load_options' ) );

		// Register the trigger.
		$this->register_trigger();

	}

	/**
	 * Loads available options for the Trigger.
	 *
	 * @return array The available trigger options.
	 */
	public function load_options() {
		return Automator()->utilities->keep_order_of_options(
			array(
				'options_group' => array(
					$this->get_trigger_meta() => array(
						array(
							'option_code'     => 'COURSE',
							'required'        => true,
							'label'           => esc_html__( 'Course', 'uncanny-automator' ),
							'input_type'      => 'select',
							'is_ajax'         => true,
							'endpoint'        => 'automator_thrive_apprentice_lessons_handler',
							'fill_values_in'  => $this->get_trigger_meta(),
							'options'         => $this->get_helper()->get_dropdown_options_courses(),
							'relevant_tokens' => $this->get_helper()->get_relevant_tokens_courses(),
						),
						array(
							'option_code'              => $this->get_trigger_meta(),
							'required'                 => true,
							'label'                    => esc_html__( 'Lesson', 'uncanny-automator' ),
							'input_type'               => 'select',
							'supports_custom_value'    => true,
							'custom_value_description' => esc_html__( 'Lesson ID', 'uncanny-automator' ),
							'options'                  => array(),
							'relevant_tokens'          => $this->get_helper()->get_relevant_tokens_courses_lessons(),
						),
					),
				),
			)
		);
	}

	/**
	 * Validates the trigger.
	 *
	 * @return boolean True.
	 */
	public function validate_trigger( ...$args ) {

		list( $lesson, $user ) = $args[0];

		return ! empty( $lesson ) && ! empty( $user );

	}

	/**
	 * Sets some properties before the trigger is run.
	 *
	 * @param array $data The trigger data.
	 *
	 * @return void.
	 */
	public function prepare_to_run( $data ) {

		$this->set_conditional_trigger( true );

	}

	/**
	 * Validates the conditions.
	 *
	 * @param array $args The trigger args.
	 *
	 * @return array The matching recipe and trigger IDs.
	 */
	public function validate_conditions( ...$args ) {

		list( $lesson, $user ) = $args[0];

		$matching_recipes_triggers = $this->find_all( $this->trigger_recipes() )
			->where( array( $this->get_trigger_meta(), 'COURSE' ) )
			->match( array( absint( $lesson['lesson_id'] ), absint( $lesson['course_id'] ) ) )
			->format( array( 'intval', 'absint' ) )
			->get();

		return $matching_recipes_triggers;

	}

	/**
	 * Parses the tokens.
	 *
	 * @return The parsed tokens.
	 */
	public function parse_additional_tokens( $parsed, $args, $trigger ) {

		$params = array_shift( $args['trigger_args'] );

		$tva_author = get_term_meta( $params['course_id'], 'tva_author', true );

		$user_data = get_userdata( $tva_author['ID'] );

		$hydrated_tokens = array(
			'COURSE_ID'      => $params['course_id'],
			'COURSE_URL'     => get_term_link( $params['course_id'] ),
			'COURSE_TITLE'   => $params['course_title'],
			'COURSE_AUTHOR'  => is_object( $user_data ) && ! empty( $user_data ) ? $user_data->user_email : '',
			'COURSE_SUMMARY' => get_term_meta( $params['course_id'], 'tva_excerpt', true ),
			'LESSON_ID'      => $params['lesson_id'],
			'LESSON_URL'     => $params['lesson_url'],
			'LESSON_TITLE'   => $params['lesson_title'],
		);

		return $parsed + $hydrated_tokens;

	}

}
