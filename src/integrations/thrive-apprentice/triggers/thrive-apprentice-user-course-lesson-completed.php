<?php
namespace Uncanny_Automator;

use Uncanny_Automator\Recipe\Trigger;

/**
 * Class THRIVE_APPRENTICE_USER_COURSE_LESSON_COMPLETED
 *
 * @package Uncanny_Automator
 */
class THRIVE_APPRENTICE_USER_COURSE_LESSON_COMPLETED extends Trigger {

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

	/**
	 * Helper instance
	 *
	 * @var Thrive_Apprentice_Helpers
	 */
	protected $helper;

	/**
	 * Setup trigger
	 *
	 * @return void
	 */
	protected function setup_trigger() {
		$this->helper = new Thrive_Apprentice_Helpers( false );

		$this->set_integration( 'THRIVE_APPRENTICE' );
		$this->set_trigger_code( self::TRIGGER_CODE );
		$this->set_trigger_meta( self::TRIGGER_META );
		$this->set_is_pro( false );

		$this->add_action( 'thrive_apprentice_lesson_complete' );

		$this->set_action_args_count( 2 );

		$this->set_sentence(
			sprintf(
				// translators:  %1$s: Lesson,  %2$s: Course
				esc_html_x( 'A user completes {{a lesson:%1$s}} in {{a course:%2$s}}', 'Thrive Apprentice', 'uncanny-automator' ),
				$this->get_trigger_meta() . ':' . $this->get_trigger_meta(),
				'COURSE:' . $this->get_trigger_meta()
			)
		);

		$this->set_readable_sentence(
			esc_html_x( 'A user completes {{a lesson}} in {{a course}}', 'Thrive Apprentice', 'uncanny-automator' )
		);
	}

	/**
	 * Loads available options for the Trigger.
	 *
	 * @return array The available trigger options.
	 */
	public function options() {
		return array(
			array(
				'option_code'     => 'COURSE',
				'required'        => true,
				'label'           => esc_html_x( 'Course', 'Thrive Apprentice', 'uncanny-automator' ),
				'input_type'      => 'select',
				'options'         => $this->helper->get_dropdown_options_courses( true, true ),
				'relevant_tokens' => array(),
			),
			array(
				'option_code'           => $this->get_trigger_meta(),
				'required'              => true,
				'label'                 => esc_html_x( 'Lesson', 'Thrive Apprentice', 'uncanny-automator' ),
				'input_type'            => 'select',
				'supports_custom_value' => false,
				'options'               => array(),
				'relevant_tokens'       => array(),
				'ajax'                  => array(
					'event'         => 'parent_fields_change',
					'endpoint'      => 'automator_thrive_apprentice_updated_lessons_handler',
					'listen_fields' => array( 'COURSE' ),
				),
			),
		);
	}

	/**
	 * Validate the trigger.
	 *
	 * @param array $trigger The trigger data.
	 * @param array $hook_args The hook arguments.
	 *
	 * @return bool True if validation was successful.
	 */
	public function validate( $trigger, $hook_args ) {
		list( $lesson, $user ) = $hook_args;

		if ( empty( $lesson ) || empty( $user ) ) {
			return false;
		}

		$lesson_id = absint( $lesson['lesson_id'] );
		$course_id = absint( $lesson['course_id'] );

		$selected_lesson_id = $trigger['meta'][ $this->get_trigger_meta() ];
		$selected_course_id = $trigger['meta']['COURSE'];

		$this->set_user_id( absint( $user['user_id'] ) );

		// If "Any course" is selected, return true
		if ( intval( '-1' ) === intval( $selected_course_id ) ) {
			return true;
		}

		// If "Any lesson" is selected, but course matches, return true
		if ( absint( $selected_course_id ) === $course_id && intval( '-1' ) === intval( $selected_lesson_id ) ) {
			return true;
		}

		// If "Specific lesson" is selected, but course and lesson matches, return true
		if ( absint( $selected_course_id ) === $course_id && absint( $selected_lesson_id ) === $lesson_id ) {
			return true;
		}

		return false;
	}

	/**
	 * Hydrate tokens with values.
	 *
	 * @param array $trigger The trigger data.
	 * @param array $hook_args The hook arguments.
	 *
	 * @return array The token values.
	 */
	public function hydrate_tokens( $trigger, $hook_args ) {
		list( $lesson, $user ) = $hook_args;

		return array(
			'LESSON_ID'             => $lesson['lesson_id'],
			'LESSON_URL'            => $lesson['lesson_url'],
			'LESSON_TITLE'          => $lesson['lesson_title'],
			'LESSON_TYPE'           => $lesson['lesson_type'],
			'MODULE_ID'             => $lesson['module_id'],
			'MODULE_TITLE'          => $lesson['module_title'],
			'COURSE_ID'             => $lesson['course_id'],
			'COURSE_TITLE'          => $lesson['course_title'],
			'USER_MEMBERSHIP_LEVEL' => $user['membership_level'],
			'USER_LAST_LOGIN'       => $user['last_logged_in'],
		);
	}

	/**
	 * Define tokens.
	 *
	 * @param array $trigger The trigger configuration.
	 * @param array $tokens The existing tokens.
	 *
	 * @return array
	 */
	public function define_tokens( $trigger, $tokens ) {
		return array(
			'LESSON_ID'             => array(
				'name'      => esc_html_x( 'Lesson ID', 'Thrive Apprentice', 'uncanny-automator' ),
				'type'      => 'int',
				'tokenId'   => 'LESSON_ID',
				'tokenName' => esc_html_x( 'Lesson ID', 'Thrive Apprentice', 'uncanny-automator' ),
			),
			'LESSON_URL'            => array(
				'name'      => esc_html_x( 'Lesson URL', 'Thrive Apprentice', 'uncanny-automator' ),
				'type'      => 'url',
				'tokenId'   => 'LESSON_URL',
				'tokenName' => esc_html_x( 'Lesson URL', 'Thrive Apprentice', 'uncanny-automator' ),
			),
			'LESSON_TITLE'          => array(
				'name'      => esc_html_x( 'Lesson title', 'Thrive Apprentice', 'uncanny-automator' ),
				'type'      => 'text',
				'tokenId'   => 'LESSON_TITLE',
				'tokenName' => esc_html_x( 'Lesson title', 'Thrive Apprentice', 'uncanny-automator' ),
			),
			'LESSON_TYPE'           => array(
				'name'      => esc_html_x( 'Lesson type', 'Thrive Apprentice', 'uncanny-automator' ),
				'type'      => 'text',
				'tokenId'   => 'LESSON_TYPE',
				'tokenName' => esc_html_x( 'Lesson type', 'Thrive Apprentice', 'uncanny-automator' ),
			),
			'MODULE_ID'             => array(
				'name'      => esc_html_x( 'Module ID', 'Thrive Apprentice', 'uncanny-automator' ),
				'type'      => 'int',
				'tokenId'   => 'MODULE_ID',
				'tokenName' => esc_html_x( 'Module ID', 'Thrive Apprentice', 'uncanny-automator' ),
			),
			'MODULE_TITLE'          => array(
				'name'      => esc_html_x( 'Module title', 'Thrive Apprentice', 'uncanny-automator' ),
				'type'      => 'text',
				'tokenId'   => 'MODULE_TITLE',
				'tokenName' => esc_html_x( 'Module title', 'Thrive Apprentice', 'uncanny-automator' ),
			),
			'COURSE_ID'             => array(
				'name'      => esc_html_x( 'Course ID', 'Thrive Apprentice', 'uncanny-automator' ),
				'type'      => 'int',
				'tokenId'   => 'COURSE_ID',
				'tokenName' => esc_html_x( 'Course ID', 'Thrive Apprentice', 'uncanny-automator' ),
			),
			'COURSE_TITLE'          => array(
				'name'      => esc_html_x( 'Course title', 'Thrive Apprentice', 'uncanny-automator' ),
				'type'      => 'text',
				'tokenId'   => 'COURSE_TITLE',
				'tokenName' => esc_html_x( 'Course title', 'Thrive Apprentice', 'uncanny-automator' ),
			),
			'USER_MEMBERSHIP_LEVEL' => array(
				'name'      => esc_html_x( 'User membership level', 'Thrive Apprentice', 'uncanny-automator' ),
				'type'      => 'text',
				'tokenId'   => 'USER_MEMBERSHIP_LEVEL',
				'tokenName' => esc_html_x( 'User membership level', 'Thrive Apprentice', 'uncanny-automator' ),
			),
			'USER_LAST_LOGIN'       => array(
				'name'      => esc_html_x( 'User last login', 'Thrive Apprentice', 'uncanny-automator' ),
				'type'      => 'date',
				'tokenId'   => 'USER_LAST_LOGIN',
				'tokenName' => esc_html_x( 'User last login', 'Thrive Apprentice', 'uncanny-automator' ),
			),

		);
	}
}
