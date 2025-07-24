<?php
namespace Uncanny_Automator\Integrations\Thrive_Apprentice;

use Uncanny_Automator\Recipe\Action;
use TVA\Assessments\TVA_User_Assessment;
use TVA\Assessments\Grading\Base as Grading;
use TVA\Assessments\Grading\Category;
use TVA\Assessments\Grading\Base as Grading_Base;

/**
 * Class THRIVE_APPRENTICE_GRADE_ASSESSMENT
 *
 * This action handles grading assessments in Thrive Apprentice.
 *
 * @package Uncanny_Automator
 */
class THRIVE_APPRENTICE_GRADE_ASSESSMENT extends Action {

	/**
	 * Constant ACTION_CODE.
	 *
	 * @var string
	 */
	const ACTION_CODE = 'THRIVE_APPRENTICE_GRADE_ASSESSMENT';

	/**
	 * Constant ACTION_META.
	 *
	 * @var string
	 */
	const ACTION_META = 'THRIVE_APPRENTICE_GRADE_ASSESSMENT_META';

	/**
	 * Helper instance
	 *
	 * @var Thrive_Apprentice_Helpers
	 */
	protected $helper;

	/**
	 * Setup action
	 *
	 * @return void
	 */
	protected function setup_action() {
		$this->helper = new Thrive_Apprentice_Helpers( false );

		$this->set_integration( 'THRIVE_APPRENTICE' );
		$this->set_action_code( self::ACTION_CODE );
		$this->set_action_meta( self::ACTION_META );
		$this->set_is_pro( false );
		$this->set_requires_user( true );

		$this->set_sentence(
			sprintf(
				// translators:  %1$s: Assessment
				esc_html_x( 'Grade {{an assessment:%1$s}} in {{a course:%2$s}} for the user', 'Thrive Apprentice', 'uncanny-automator' ),
				$this->get_action_meta() . ':' . $this->get_action_meta(),
				'COURSE:' . $this->get_action_meta()
			)
		);

		$this->set_readable_sentence(
			esc_html_x( 'Grade {{an assessment}} in {{a course}} for the user', 'Thrive Apprentice', 'uncanny-automator' )
		);

		$this->set_background_processing( false );
	}

	/**
	 * Define options
	 *
	 * @return array The options configuration.
	 */
	public function options() {
		return array(
			array(
				'option_code'     => 'COURSE',
				'label'           => esc_html_x( 'Course', 'Thrive Apprentice', 'uncanny-automator' ),
				'input_type'      => 'select',
				'required'        => true,
				'options'         => $this->helper->get_dropdown_options_courses( false, true ),
				'relevant_tokens' => array(),
			),
			array(
				'option_code'     => $this->get_action_meta(),
				'label'           => esc_html_x( 'Assessment', 'Thrive Apprentice', 'uncanny-automator' ),
				'input_type'      => 'select',
				'required'        => true,
				'options'         => array(),
				'relevant_tokens' => array(),
				'supports_tokens' => false,
				'ajax'            => array(
					'event'         => 'parent_fields_change',
					'endpoint'      => 'automator_thrive_apprentice_assessments_handler',
					'listen_fields' => array( 'COURSE' ),
				),
			),
			array(
				'option_code'     => 'GRADE_AUTOMATICALLY',
				'label'           => esc_html_x( 'Grade automatically', 'Thrive Apprentice', 'uncanny-automator' ),
				'input_type'      => 'checkbox',
				'is_toggle'       => true,
				'required'        => false,
				'options'         => array(
					'yes' => esc_html_x( 'Yes', 'Thrive Apprentice', 'uncanny-automator' ),
					'no'  => esc_html_x( 'No', 'Thrive Apprentice', 'uncanny-automator' ),
				),
				'default_value'   => 'yes',
				'supports_tokens' => false,
			),
			array(
				'option_code'        => 'SCORE',
				'label'              => esc_html_x( 'Score/Percentage', 'Thrive Apprentice', 'uncanny-automator' ),
				'input_type'         => 'int',
				'required'           => true,
				'description'        => esc_html_x( 'Enter a score or percentage', 'Thrive Apprentice', 'uncanny-automator' ),
				'supports_tokens'    => false,
				'dynamic_visibility' => array(
					'default_state'    => 'hidden',
					'visibility_rules' => array(
						array(
							'operator'             => 'AND',
							'rule_conditions'      => array(
								array(
									'option_code' => 'GRADE_AUTOMATICALLY',
									'compare'     => '!=',
									'value'       => true,
								),
							),
							'resulting_visibility' => 'show',
						),
					),
				),
			),
		);
	}

	/**
	 * Process the action to grade a user's assessment.
	 *
	 * @param int   $user_id     The user ID.
	 * @param array $action_data The action data.
	 * @param int   $recipe_id   The recipe ID.
	 * @param array $args        Additional arguments.
	 * @param array $parsed      Parsed token values.
	 *
	 * @return bool True on success, false on failure.
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {
		try {
			$course_id = absint( $parsed['COURSE'] );

			if ( ! $course_id ) {
				throw new \Exception( esc_html_x( 'Missing course ID.', 'Thrive Apprentice', 'uncanny-automator' ) );
			}

			$course = new \TVA_Course_V2( $course_id );

			if ( ! $course instanceof \TVA_Course_V2 ) {
				throw new \Exception( esc_html_x( 'Invalid course.', 'Thrive Apprentice', 'uncanny-automator' ) );
			}

			$assessment_id = absint( $parsed[ $this->get_action_meta() ] );

			if ( ! $assessment_id ) {
				throw new \Exception( esc_html_x( 'Missing assessment ID.', 'Thrive Apprentice', 'uncanny-automator' ) );
			}

			$assessment = new \TVA_Assessment( $assessment_id );

			if ( ! $assessment instanceof \TVA_Assessment ) {
				throw new \Exception( esc_html_x( 'Invalid assessment.', 'Thrive Apprentice', 'uncanny-automator' ) );
			}

			$score               = floatval( $parsed['SCORE'] );
			$grade_automatically = $parsed['GRADE_AUTOMATICALLY'];
			$grade_automatically = 'true' === (string) $grade_automatically ? 'yes' : 'no';

			if ( 'yes' !== $grade_automatically && ( $score < 0 || $score > 100 ) ) {
				throw new \Exception( esc_html_x( 'Score or percentage must be between 0 and 100.', 'Thrive Apprentice', 'uncanny-automator' ) );
			}

			// Get assessment post
			$assessment_post = array(
				'post_parent'   => $assessment_id,
				'type'          => get_post_meta( $assessment_id, 'tva_assessment_type', true ),
				'post_id'       => $assessment_id,
				'assessment_id' => $assessment_id,
				'quiz_id'       => get_post_meta( $assessment_id, 'tva_quiz_id', true ),
				'post_type'     => TVA_User_Assessment::POST_TYPE,
				'post_title'    => 'User Assessment',
				'post_status'   => 'draft',
				'post_author'   => $user_id,
			);

			// Create new user assessment
			$user_assessment = new TVA_User_Assessment( $assessment_post );
			$user_assessment->create();

			$grading_details = Grading::get_assessment_grading_details( $assessment_id );
			$grade_value     = $this->get_grade_value( $grading_details, $grade_automatically, $score );
			$notes           = esc_html_x( 'Graded by Automator. Recipe: ', 'Thrive Apprentice', 'uncanny-automator' ) . get_the_title( $recipe_id );

			$user_assessment->save_grade( $grade_value, $notes );

			return true;

		} catch ( \Exception $e ) {
			$this->add_log_error( $e->getMessage() );
			return false;
		}
	}

	/**
	 * Get the grade value based on the grading details and the grade automatically option.
	 * Code borrowed from Thrive Automator plugin.
	 *
	 * @param array  $grading_details The grading details.
	 * @param string $grade_automatically The grade automatically option.
	 * @param int    $score The score.
	 *
	 * @return int The grade value.
	 */
	private function get_grade_value( $grading_details, $grade_automatically, $score ) {

		if ( 'yes' === $grade_automatically ) {
			$grading_instance = Grading::factory( $grading_details );
			$grade_value      = $grading_instance->get_passing_grade();
			return $grade_value;
		}

		switch ( $grading_details['grading_method'] ) {
			case Grading::CATEGORY_METHOD:
				$grade_value = $score;
				$categories  = array_merge(
					$grading_details['grading_method_data'][ Category::PASS_META ],
					$grading_details['grading_method_data'][ Category::FAIL_META ]
				);

				/** Try to find the category id when grade is selected manually form input */
				foreach ( $categories as $category ) {
					if ( $category['name'] === $grade_value ) {
						$grade_value = $category['ID'];
					}
				}
				break;
			case Grading::PASS_FAIL_METHOD:
				$grade_value = $score;
				break;
			case Grading::PERCENTAGE_METHOD:
			case Grading::SCORE_METHOD:
				$grade_value = $score;
				break;
			default:
				$grade_value = $score;
		}

		return $grade_value;
	}
}
