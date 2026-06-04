<?php
namespace Uncanny_Automator\Integrations\Fluent_Community;

use Uncanny_Automator\Recipe\Trigger;

/**
 * Class FLUENTCOMMUNITY_USER_QUIZ_PERCENTAGE
 *
 * @package Uncanny_Automator
 */
class FLUENTCOMMUNITY_USER_QUIZ_PERCENTAGE extends Trigger {

	protected $prefix = 'FLUENTCOMMUNITY_USER_QUIZ_PERCENTAGE';

	protected $helpers;

	/**
	 * Setup trigger.
	 */
	protected function setup_trigger() {
		$this->helpers = array_shift( $this->dependencies );

		$this->set_integration( 'FLUENT_COMMUNITY' );
		$this->set_trigger_code( $this->prefix . '_CODE' );
		$this->set_trigger_meta( $this->prefix . '_META' );
		$this->set_is_pro( false );

		$this->add_action( 'fluent_community/quiz/submitted' );
		$this->set_action_args_count( 3 );

		$this->set_sentence(
			sprintf(
				/* translators: %1$s - Course, %2$s - Lesson, %3$s - Quiz, %4$s - Condition, %5$s - Percentage */
				esc_html_x( 'A user achieves a percentage {{greater than, less than or equal to:%4$s}} {{a value:%5$s}} on {{a quiz:%3$s}} in {{a lesson:%2$s}} of {{a course:%1$s}}', 'FluentCommunity', 'uncanny-automator' ),
				'COURSE:' . $this->get_trigger_meta(),
				'LESSON:' . $this->get_trigger_meta(),
				'QUIZ:' . $this->get_trigger_meta(),
				'NUMBERCOND:' . $this->get_trigger_meta(),
				'QUIZPERCENT:' . $this->get_trigger_meta()
			)
		);

		$this->set_readable_sentence(
			esc_html_x( 'A user achieves a percentage {{greater than, less than or equal to}} {{a value}} on {{a quiz}}', 'FluentCommunity', 'uncanny-automator' )
		);
	}

	/**
	 * Options.
	 *
	 * @return array
	 */
	public function options() {
		return array(
			array(
				'option_code'           => 'COURSE',
				'label'                 => esc_html_x( 'Course', 'FluentCommunity', 'uncanny-automator' ),
				'input_type'            => 'select',
				'required'              => true,
				'options'               => $this->helpers->all_courses( true ),
				'supports_custom_value' => false,
			),
			array(
				'option_code'           => 'LESSON',
				'label'                 => esc_html_x( 'Lesson', 'FluentCommunity', 'uncanny-automator' ),
				'input_type'            => 'select',
				'required'              => true,
				'options'               => array(),
				'supports_custom_value' => false,
				'ajax'                  => array(
					'endpoint'      => 'automator_fluentcommunity_lessons_fetch',
					'event'         => 'parent_fields_change',
					'listen_fields' => array( 'COURSE' ),
				),
			),
			array(
				'option_code'           => 'QUIZ',
				'label'                 => esc_html_x( 'Quiz', 'FluentCommunity', 'uncanny-automator' ),
				'input_type'            => 'select',
				'required'              => true,
				'options'               => array(),
				'supports_custom_value' => false,
				'ajax'                  => array(
					'endpoint'      => 'automator_fluentcommunity_quizzes_by_lesson_fetch',
					'event'         => 'parent_fields_change',
					'listen_fields' => array( 'LESSON' ),
				),
			),
			array(
				'option_code'           => 'NUMBERCOND',
				'label'                 => esc_html_x( 'Condition', 'FluentCommunity', 'uncanny-automator' ),
				'input_type'            => 'select',
				'required'              => true,
				'supports_custom_value' => false,
				'options'               => array(
					array(
						'value' => '<',
						'text'  => esc_html_x( 'less than', 'FluentCommunity', 'uncanny-automator' ),
					),
					array(
						'value' => '>',
						'text'  => esc_html_x( 'greater than', 'FluentCommunity', 'uncanny-automator' ),
					),
					array(
						'value' => '=',
						'text'  => esc_html_x( 'equal to', 'FluentCommunity', 'uncanny-automator' ),
					),
					array(
						'value' => '!=',
						'text'  => esc_html_x( 'not equal to', 'FluentCommunity', 'uncanny-automator' ),
					),
					array(
						'value' => '>=',
						'text'  => esc_html_x( 'greater than or equal to', 'FluentCommunity', 'uncanny-automator' ),
					),
					array(
						'value' => '<=',
						'text'  => esc_html_x( 'less than or equal to', 'FluentCommunity', 'uncanny-automator' ),
					),
				),
			),
			array(
				'option_code'           => 'QUIZPERCENT',
				'label'                 => esc_html_x( 'Value', 'FluentCommunity', 'uncanny-automator' ),
				'input_type'            => 'int',
				'required'              => true,
				'supports_custom_value' => false,
				'placeholder'           => esc_html_x( 'Example: 80', 'FluentCommunity', 'uncanny-automator' ),
			),
		);
	}

	/**
	 * Validate.
	 *
	 * @param mixed $trigger The trigger.
	 * @param mixed $hook_args The arguments.
	 * @return bool
	 */
	public function validate( $trigger, $hook_args ) {
		$quiz_result = isset( $hook_args[0] ) ? $hook_args[0] : null;
		$user        = isset( $hook_args[1] ) ? $hook_args[1] : null;
		$quiz        = isset( $hook_args[2] ) ? $hook_args[2] : null;

		if ( empty( $quiz_result ) || empty( $user ) || empty( $quiz ) ) {
			return false;
		}

		$this->set_user_id( $user->ID );

		$selected_condition = isset( $trigger['meta']['NUMBERCOND'] ) ? $trigger['meta']['NUMBERCOND'] : '';
		$required_percent   = isset( $trigger['meta']['QUIZPERCENT'] ) ? floatval( $trigger['meta']['QUIZPERCENT'] ) : 0;
		$selected_quiz      = isset( $trigger['meta']['QUIZ'] ) ? intval( $trigger['meta']['QUIZ'] ) : -1;
		$selected_lesson    = isset( $trigger['meta']['LESSON'] ) ? intval( $trigger['meta']['LESSON'] ) : -1;
		$selected_course    = isset( $trigger['meta']['COURSE'] ) ? intval( $trigger['meta']['COURSE'] ) : -1;

		$achieved_percent = floatval( $quiz_result->score );

		// Match course.
		if ( -1 !== $selected_course && absint( $quiz->space_id ) !== $selected_course ) {
			return false;
		}

		// Match lesson.
		if ( -1 !== $selected_lesson && absint( $quiz->parent_id ) !== $selected_lesson ) {
			return false;
		}

		// Match quiz.
		if ( -1 !== $selected_quiz && absint( $quiz->id ) !== $selected_quiz ) {
			return false;
		}

		// Match percentage condition.
		switch ( $selected_condition ) {
			case '<':
				return $achieved_percent < $required_percent;
			case '>':
				return $achieved_percent > $required_percent;
			case '=':
				return abs( $achieved_percent - $required_percent ) < 0.01;
			case '!=':
				return abs( $achieved_percent - $required_percent ) >= 0.01;
			case '>=':
				return $achieved_percent >= $required_percent;
			case '<=':
				return $achieved_percent <= $required_percent;
			default:
				return false;
		}
	}

	/**
	 * Hydrate tokens.
	 *
	 * @param mixed $trigger The trigger.
	 * @param mixed $hook_args The arguments.
	 * @return array
	 */
	public function hydrate_tokens( $trigger, $hook_args ) {
		$quiz_result = isset( $hook_args[0] ) ? $hook_args[0] : null;
		$quiz        = isset( $hook_args[2] ) ? $hook_args[2] : null;

		if ( empty( $quiz_result ) || empty( $quiz ) ) {
			return array();
		}

		$course = \FluentCommunity\Modules\Course\Model\Course::find( $quiz->space_id );
		$lesson = \FluentCommunity\Modules\Course\Model\CourseLesson::find( $quiz->parent_id );
		$meta   = is_array( $quiz_result->meta ) ? $quiz_result->meta : array();

		return array(
			'NUMBERCOND' => $trigger['meta']['NUMBERCOND_readable'] ?? '',
			'QUIZ_ID'           => $quiz->id,
			'QUIZ_TITLE'        => $quiz->title ?? '',
			'QUIZ'        => $quiz->title ?? '',
			'LESSON_ID'         => $quiz->parent_id ?? '',
			'LESSON_TITLE'      => $lesson->title ?? '',
			'LESSON'      => $lesson->title ?? '',
			'COURSE_ID'         => $quiz->space_id ?? '',
			'COURSE_TITLE'      => $course->title ?? '',
			'COURSE'      => $course->title ?? '',
			'ACHIEVED_PERCENT'  => $quiz_result->score ?? 0,
			'QUIZ_STATUS'       => $quiz_result->status ?? '',
			'TOTAL_QUESTIONS'   => $meta['total_questions'] ?? 0,
			'CORRECT_ANSWERS'   => $meta['correct_answers'] ?? 0,
			'ATTEMPTS'          => $meta['attempts'] ?? 0,
			'QUIZPERCENT'       => $trigger['meta']['QUIZPERCENT'] ?? '',
		);
	}

	/**
	 * Define tokens.
	 *
	 * @param mixed $trigger The trigger.
	 * @param mixed $tokens The destination.
	 * @return array
	 */
	public function define_tokens( $trigger, $tokens ) {
		$custom_tokens = array(
			'QUIZ_ID'          => array(
				'name'      => esc_html_x( 'Quiz ID', 'FluentCommunity', 'uncanny-automator' ),
				'type'      => 'int',
				'tokenId'   => 'QUIZ_ID',
				'tokenName' => esc_html_x( 'Quiz ID', 'FluentCommunity', 'uncanny-automator' ),
			),
			'QUIZ_TITLE'       => array(
				'name'      => esc_html_x( 'Quiz title', 'FluentCommunity', 'uncanny-automator' ),
				'type'      => 'text',
				'tokenId'   => 'QUIZ_TITLE',
				'tokenName' => esc_html_x( 'Quiz title', 'FluentCommunity', 'uncanny-automator' ),
			),
			'LESSON_ID'        => array(
				'name'      => esc_html_x( 'Lesson ID', 'FluentCommunity', 'uncanny-automator' ),
				'type'      => 'int',
				'tokenId'   => 'LESSON_ID',
				'tokenName' => esc_html_x( 'Lesson ID', 'FluentCommunity', 'uncanny-automator' ),
			),
			'LESSON_TITLE'     => array(
				'name'      => esc_html_x( 'Lesson title', 'FluentCommunity', 'uncanny-automator' ),
				'type'      => 'text',
				'tokenId'   => 'LESSON_TITLE',
				'tokenName' => esc_html_x( 'Lesson title', 'FluentCommunity', 'uncanny-automator' ),
			),
			'COURSE_ID'        => array(
				'name'      => esc_html_x( 'Course ID', 'FluentCommunity', 'uncanny-automator' ),
				'type'      => 'int',
				'tokenId'   => 'COURSE_ID',
				'tokenName' => esc_html_x( 'Course ID', 'FluentCommunity', 'uncanny-automator' ),
			),
			'COURSE_TITLE'     => array(
				'name'      => esc_html_x( 'Course title', 'FluentCommunity', 'uncanny-automator' ),
				'type'      => 'text',
				'tokenId'   => 'COURSE_TITLE',
				'tokenName' => esc_html_x( 'Course title', 'FluentCommunity', 'uncanny-automator' ),
			),
			'ACHIEVED_PERCENT' => array(
				'name'      => esc_html_x( 'Achieved percentage', 'FluentCommunity', 'uncanny-automator' ),
				'type'      => 'text',
				'tokenId'   => 'ACHIEVED_PERCENT',
				'tokenName' => esc_html_x( 'Achieved percentage', 'FluentCommunity', 'uncanny-automator' ),
			),
			'QUIZ_STATUS'      => array(
				'name'      => esc_html_x( 'Quiz status', 'FluentCommunity', 'uncanny-automator' ),
				'type'      => 'text',
				'tokenId'   => 'QUIZ_STATUS',
				'tokenName' => esc_html_x( 'Quiz status (passed/failed)', 'FluentCommunity', 'uncanny-automator' ),
			),
			'TOTAL_QUESTIONS'  => array(
				'name'      => esc_html_x( 'Total questions', 'FluentCommunity', 'uncanny-automator' ),
				'type'      => 'int',
				'tokenId'   => 'TOTAL_QUESTIONS',
				'tokenName' => esc_html_x( 'Total questions', 'FluentCommunity', 'uncanny-automator' ),
			),
			'CORRECT_ANSWERS'  => array(
				'name'      => esc_html_x( 'Correct answers', 'FluentCommunity', 'uncanny-automator' ),
				'type'      => 'int',
				'tokenId'   => 'CORRECT_ANSWERS',
				'tokenName' => esc_html_x( 'Correct answers', 'FluentCommunity', 'uncanny-automator' ),
			),
			'ATTEMPTS'         => array(
				'name'      => esc_html_x( 'Attempts', 'FluentCommunity', 'uncanny-automator' ),
				'type'      => 'int',
				'tokenId'   => 'ATTEMPTS',
				'tokenName' => esc_html_x( 'Attempts', 'FluentCommunity', 'uncanny-automator' ),
			),
			'QUIZPERCENT'      => array(
				'name'      => esc_html_x( 'Threshold percentage', 'FluentCommunity', 'uncanny-automator' ),
				'type'      => 'int',
				'tokenId'   => 'QUIZPERCENT',
				'tokenName' => esc_html_x( 'Threshold percentage', 'FluentCommunity', 'uncanny-automator' ),
			),
		);

		return array_merge( (array) $tokens, $custom_tokens );
	}
}
