<?php
namespace Uncanny_Automator;

use Uncanny_Automator\Recipe\Trigger;

/**
 * Class THRIVE_APPRENTICE_USER_PASS_ASSESSMENT_IN_COURSE
 *
 * @package Uncanny_Automator
 */
class THRIVE_APPRENTICE_USER_PASS_ASSESSMENT_IN_COURSE extends Trigger {

	/**
	 * Constant TRIGGER_CODE.
	 *
	 * @var string
	 */
	const TRIGGER_CODE = 'THRIVE_APPRENTICE_USER_PASS_ASSESSMENT_IN_COURSE';

	/**
	 * Constant TRIGGER_META.
	 *
	 * @var string
	 */
	const TRIGGER_META = 'THRIVE_APPRENTICE_USER_PASS_ASSESSMENT_IN_COURSE_META';

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

		// Hook into assessment pass event
		$this->add_action( 'tva_assessment_passed' );

		$this->set_action_args_count( 1 );

		$this->set_sentence(
			sprintf(
				// translators:  %1$s: Assessment,  %2$s: Course
				esc_html_x( 'A user passes {{an assessment:%2$s}} in {{a course:%1$s}}', 'Thrive Apprentice', 'uncanny-automator' ),
				'COURSE:' . $this->get_trigger_meta(),
				$this->get_trigger_meta() . ':' . $this->get_trigger_meta()
			)
		);

		$this->set_readable_sentence(
			esc_html_x( 'A user passes {{an assessment}} in {{a course}}', 'Thrive Apprentice', 'uncanny-automator' )
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
				'label'           => esc_html_x( 'Course', 'Thrive Apprentice', 'uncanny-automator' ),
				'input_type'      => 'select',
				'required'        => true,
				'options'         => $this->helper->get_dropdown_options_courses( true, true ),
				'relevant_tokens' => array(),
			),
			array(
				'option_code'     => $this->get_trigger_meta(),
				'label'           => esc_html_x( 'Assessment', 'Thrive Apprentice', 'uncanny-automator' ),
				'input_type'      => 'select',
				'required'        => true,
				'options'         => array(),
				'relevant_tokens' => array(),
				'ajax'            => array(
					'event'         => 'parent_fields_change',
					'endpoint'      => 'automator_thrive_apprentice_assessments_handler',
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
		list( $user_assessment ) = $hook_args;

		if ( ! $user_assessment instanceof \TVA\Assessments\TVA_User_Assessment ) {
			return false;
		}

		$assessment_id = absint( $user_assessment->post_parent );
		$course_id     = absint( $user_assessment->get_course_id() );

		$selected_course_id     = $trigger['meta']['COURSE'];
		$selected_assessment_id = $trigger['meta'][ $this->get_trigger_meta() ];

		$user_id = $user_assessment->post_author;
		$author  = new \WP_User( $user_assessment->post_author );

		$this->set_user_id( absint( $author->ID ) );

		// If any course is selected, return true
		if ( intval( '-1' ) === intval( $selected_course_id ) ) {
			return true;
		}

		// If any assessment is selected, but course matches, return true
		if ( intval( '-1' ) === intval( $selected_assessment_id ) && absint( $course_id ) === absint( $selected_course_id ) ) {
			return true;
		}

		// If specific course and assessment are selected, return true
		if ( absint( $assessment_id ) === absint( $selected_assessment_id ) && absint( $course_id ) === absint( $selected_course_id ) ) {
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
		list( $user_assessment ) = $hook_args;

		if ( ! $user_assessment instanceof \TVA\Assessments\TVA_User_Assessment ) {
			return array();
		}

		$author = new \WP_User( $user_assessment->post_author );

		$assessment_id = absint( $user_assessment->post_parent );
		$assessment    = new \TVA_Assessment( get_post( (int) $assessment_id ) );
		$course_id     = absint( $user_assessment->get_course_id() );
		$course        = new \TVA_Course_V2( $course_id );

		return array(
			'ASSESSMENT_ID'    => $assessment_id,
			'ASSESSMENT_TITLE' => $assessment->post_title,
			'ASSESSMENT_TYPE'  => $assessment->get_type(),
			'SUBMISSION_DATE'  => $user_assessment->post_date,
			'COURSE_ID'        => $course_id,
			'COURSE_TITLE'     => $course->name,
			// 'ASSESSMENT_SCORE'    => $assessment->get_score(),
			// 'PASSING_SCORE'       => $assessment->get_passing_score(),
			// 'COURSE_URL'          => $course_data['course_url'],
			// 'COURSE_AUTHOR'       => $course_data['author'],
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
			'ASSESSMENT_ID'    => array(
				'name'      => esc_html_x( 'Assessment ID', 'Thrive Apprentice', 'uncanny-automator' ),
				'type'      => 'int',
				'tokenId'   => 'ASSESSMENT_ID',
				'tokenName' => esc_html_x( 'Assessment ID', 'Thrive Apprentice', 'uncanny-automator' ),
			),
			'ASSESSMENT_TITLE' => array(
				'name'      => esc_html_x( 'Assessment title', 'Thrive Apprentice', 'uncanny-automator' ),
				'type'      => 'text',
				'tokenId'   => 'ASSESSMENT_TITLE',
				'tokenName' => esc_html_x( 'Assessment title', 'Thrive Apprentice', 'uncanny-automator' ),
			),
			'ASSESSMENT_TYPE'  => array(
				'name'      => esc_html_x( 'Assessment type', 'Thrive Apprentice', 'uncanny-automator' ),
				'type'      => 'text',
				'tokenId'   => 'ASSESSMENT_TYPE',
				'tokenName' => esc_html_x( 'Assessment type', 'Thrive Apprentice', 'uncanny-automator' ),
			),
			'SUBMISSION_DATE'  => array(
				'name'      => esc_html_x( 'Submission date', 'Thrive Apprentice', 'uncanny-automator' ),
				'type'      => 'date',
				'tokenId'   => 'SUBMISSION_DATE',
				'tokenName' => esc_html_x( 'Submission date', 'Thrive Apprentice', 'uncanny-automator' ),
			),
			'COURSE_ID'        => array(
				'name'      => esc_html_x( 'Course ID', 'Thrive Apprentice', 'uncanny-automator' ),
				'type'      => 'int',
				'tokenId'   => 'COURSE_ID',
				'tokenName' => esc_html_x( 'Course ID', 'Thrive Apprentice', 'uncanny-automator' ),
			),
			'COURSE_TITLE'     => array(
				'name'      => esc_html_x( 'Course title', 'Thrive Apprentice', 'uncanny-automator' ),
				'type'      => 'text',
				'tokenId'   => 'COURSE_TITLE',
				'tokenName' => esc_html_x( 'Course title', 'Thrive Apprentice', 'uncanny-automator' ),
			),
			// 'ASSESSMENT_SCORE' => array(
			//  'name'      => esc_html_x( 'Assessment score', 'Thrive Apprentice', 'uncanny-automator' ),
			//  'type'      => 'float',
			//  'tokenId'   => 'ASSESSMENT_SCORE',
			//  'tokenName' => esc_html_x( 'Assessment score', 'Thrive Apprentice', 'uncanny-automator' ),
			// ),
			// 'PASSING_SCORE' => array(
			//  'name'      => esc_html_x( 'Passing score', 'Thrive Apprentice', 'uncanny-automator' ),
			//  'type'      => 'float',
			//  'tokenId'   => 'PASSING_SCORE',
			//  'tokenName' => esc_html_x( 'Passing score', 'Thrive Apprentice', 'uncanny-automator' ),
			// ),
			// 'COURSE_URL' => array(
			//  'name'      => esc_html_x( 'Course URL', 'Thrive Apprentice', 'uncanny-automator' ),
			//  'type'      => 'url',
			//  'tokenId'   => 'COURSE_URL',
			//  'tokenName' => esc_html_x( 'Course URL', 'Thrive Apprentice', 'uncanny-automator' ),
			// ),
			// 'COURSE_AUTHOR' => array(
			//  'name'      => esc_html_x( 'Course author', 'Thrive Apprentice', 'uncanny-automator' ),
			//  'type'      => 'text',
			//  'tokenId'   => 'COURSE_AUTHOR',
			//  'tokenName' => esc_html_x( 'Course author', 'Thrive Apprentice', 'uncanny-automator' ),
			// ),
		);
	}
}
