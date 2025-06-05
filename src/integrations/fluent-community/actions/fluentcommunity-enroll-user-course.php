<?php
namespace Uncanny_Automator\Integrations\Fluent_Community;

use Uncanny_Automator\Recipe\Action;
use FluentCommunity\Modules\Course\Model\Course;
use FluentCommunity\Modules\Course\Services\CourseHelper;

/**
 * Class FLUENTCOMMUNITY_ENROLL_USER_COURSE
 */
class FLUENTCOMMUNITY_ENROLL_USER_COURSE extends Action {

	protected $prefix = 'FLUENTCOMMUNITY_ENROLL_USER_COURSE';

	protected $helpers;

	/**
	 * Setup action.
	 */
	protected function setup_action() {
		$this->helpers = array_shift( $this->dependencies );

		$this->set_integration( 'FLUENT_COMMUNITY' );
		$this->set_action_code( $this->prefix . '_CODE' );
		$this->set_action_meta( $this->prefix . '_META' );
		$this->set_is_pro( false );
		$this->set_requires_user( true );

		$this->set_sentence(
			sprintf(
				/* translators: %1$s - Course */
				esc_html_x( 'Enroll the user in {{a course:%1$s}}', 'FluentCommunity', 'uncanny-automator' ),
				$this->get_action_meta()
			)
		);

		$this->set_readable_sentence(
			esc_html_x( 'Enroll the user in {{a course}}', 'FluentCommunity', 'uncanny-automator' )
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
				'option_code'           => $this->get_action_meta(),
				'label'                 => esc_html_x( 'Course', 'FluentCommunity', 'uncanny-automator' ),
				'input_type'            => 'select',
				'required'              => true,
				'options'               => $this->helpers->all_courses( false ),
				'relevant_tokens'       => array(),
				'supports_custom_value' => false,
			),
		);
	}
	/**
	 * Process action.
	 *
	 * @param mixed $user_id The user ID.
	 * @param mixed $action_data The data.
	 * @param mixed $recipe_id The ID.
	 * @param mixed $args The arguments.
	 * @param mixed $parsed The parsed.
	 * @return mixed
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {
		$course_id = absint( $parsed[ $this->get_action_meta() ] );

		if ( ! $course_id || ! $user_id ) {
			throw new \Exception( esc_html_x( 'Missing course ID or user ID.', 'FluentCommunity', 'uncanny-automator' ) );
		}

		$course = Course::findOrFail( $course_id );

		if ( ! $course ) {
			throw new \Exception( esc_html_x( 'The specified course does not exist.', 'FluentCommunity', 'uncanny-automator' ) );
		}

		if ( CourseHelper::isEnrolled( $course_id, $user_id ) ) {
			throw new \Exception( esc_html_x( 'The user was already enrolled in the specified course.', 'FluentCommunity', 'uncanny-automator' ) );
		}

		$enrolled = CourseHelper::enrollCourse( $course, $user_id );

		if ( ! $enrolled ) {
			throw new \Exception( esc_html_x( 'Failed to enroll the user in the course.', 'FluentCommunity', 'uncanny-automator' ) );
		}

		return true;
	}
}
