<?php
namespace Uncanny_Automator\Integrations\Fluent_Community;

use Uncanny_Automator\Recipe\Trigger;

/**
 * Class FLUENTCOMMUNITY_USER_LESSON_COMPLETED
 *
 * @package Uncanny_Automator
 */
class FLUENTCOMMUNITY_USER_LESSON_COMPLETED extends Trigger {

	protected $prefix = 'FLUENTCOMMUNITY_USER_LESSON_COMPLETED';

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

		$this->add_action( 'fluent_community/course/lesson_completed' );
		$this->set_action_args_count( 2 );

		$this->set_sentence(
			sprintf(
				/* translators: %1$s - Lesson, %2$s - Course */
				esc_html_x( 'A user completes {{a lesson:%1$s}} in {{a course:%2$s}}', 'FluentCommunity', 'uncanny-automator' ),
				$this->get_trigger_meta() . ':' . $this->get_trigger_meta(),
				'COURSE:' . $this->get_trigger_meta()
			)
		);

		$this->set_readable_sentence(
			esc_html_x( 'A user completes {{a lesson}} in {{a course}}', 'FluentCommunity', 'uncanny-automator' )
		);
	}
	/**
	 * Options.
	 *
	 * @return mixed
	 */
	public function options() {
		return array(
			array(
				'option_code'           => 'COURSE',
				'label'                 => esc_html_x( 'Course', 'FluentCommunity', 'uncanny-automator' ),
				'input_type'            => 'select',
				'required'              => true,
				'options'               => $this->helpers->all_courses( true ),
				'relevant_tokens'       => array(),
				'supports_custom_value' => false,
			),
			array(
				'option_code'           => $this->get_trigger_meta(),
				'label'                 => esc_html_x( 'Lesson', 'FluentCommunity', 'uncanny-automator' ),
				'input_type'            => 'select',
				'required'              => true,
				'options'               => array(),
				'relevant_tokens'       => array(),
				'ajax'                  => array(
					'endpoint'      => 'automator_fluentcommunity_lessons_fetch',
					'event'         => 'parent_fields_change',
					'listen_fields' => array( 'COURSE' ),
				),
				'supports_custom_value' => false,
			),
		);
	}
	/**
	 * Validate.
	 *
	 * @param mixed $trigger The trigger.
	 * @param mixed $hook_args The arguments.
	 * @return mixed
	 */
	public function validate( $trigger, $hook_args ) {
		$lesson  = isset( $hook_args[0] ) && is_object( $hook_args[0] ) ? $hook_args[0] : null;
		$user_id = isset( $hook_args[1] ) ? absint( $hook_args[1] ) : null;

		if ( empty( $user_id ) || empty( $lesson->id ) ) {
			return false;
		}

		$this->set_user_id( $user_id );

		$selected_lesson = isset( $trigger['meta'][ $this->get_trigger_meta() ] ) ? intval( $trigger['meta'][ $this->get_trigger_meta() ] ) : -1;
		$selected_course = isset( $trigger['meta']['COURSE'] ) ? intval( $trigger['meta']['COURSE'] ) : -1;

		$lesson_match = ( intval( '-1' ) === intval( $selected_lesson ) || absint( $lesson->id ) === $selected_lesson );
		$course_match = ( intval( '-1' ) === intval( $selected_course ) || absint( $lesson->space_id ) === $selected_course );

		return $lesson_match && $course_match;
	}

	/**
	 * Hydrate tokens.
	 *
	 * @param mixed $trigger The trigger.
	 * @param mixed $hook_args The arguments.
	 * @return mixed
	 */
	public function hydrate_tokens( $trigger, $hook_args ) {
		$lesson = isset( $hook_args[0] ) ? $hook_args[0] : null;

		if ( ! is_object( $lesson ) || empty( $lesson->id ) ) {
			return array();
		}

		$course = \FluentCommunity\Modules\Course\Model\Course::find( $lesson->space_id );

		return array(
			'LESSON_ID'    => $lesson->id,
			'LESSON_TITLE' => $lesson->title ?? '',
			'COURSE_ID'    => $lesson->space_id ?? '',
			'COURSE_TITLE' => $course->title ?? '',
		);
	}

	/**
	 * Define tokens.
	 *
	 * @param mixed $trigger The trigger.
	 * @param mixed $tokens The destination.
	 * @return mixed
	 */
	public function define_tokens( $trigger, $tokens ) {
		return array(
			'LESSON_ID'    => array(
				'name'      => esc_html_x( 'Lesson ID', 'FluentCommunity', 'uncanny-automator' ),
				'type'      => 'int',
				'tokenId'   => 'LESSON_ID',
				'tokenName' => esc_html_x( 'Lesson ID', 'FluentCommunity', 'uncanny-automator' ),
			),
			'LESSON_TITLE' => array(
				'name'      => esc_html_x( 'Lesson title', 'FluentCommunity', 'uncanny-automator' ),
				'type'      => 'text',
				'tokenId'   => 'LESSON_TITLE',
				'tokenName' => esc_html_x( 'Lesson title', 'FluentCommunity', 'uncanny-automator' ),
			),
			'COURSE_ID'    => array(
				'name'      => esc_html_x( 'Course ID', 'FluentCommunity', 'uncanny-automator' ),
				'type'      => 'int',
				'tokenId'   => 'COURSE_ID',
				'tokenName' => esc_html_x( 'Course ID', 'FluentCommunity', 'uncanny-automator' ),
			),
			'COURSE_TITLE' => array(
				'name'      => esc_html_x( 'Course title', 'FluentCommunity', 'uncanny-automator' ),
				'type'      => 'text',
				'tokenId'   => 'COURSE_TITLE',
				'tokenName' => esc_html_x( 'Course title', 'FluentCommunity', 'uncanny-automator' ),
			),
		);
	}
}
