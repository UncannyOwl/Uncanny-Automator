<?php

namespace Uncanny_Automator;

use uncanny_ceu\Utilities;

/**
 * Class UNCANNYCEUS_AWARDCEUS
 *
 * @package Uncanny_Automator
 */
class UNCANNYCEUS_AWARDCEUS {

	/**
	 * Integration code
	 *
	 * @var string
	 */
	public static $integration = 'UNCANNYCEUS';
	private $action_code;

	/**
	 * Set up Automator action constructor.
	 */
	public function __construct() {
		$this->action_code = 'AWARDCEUS';
		$this->define_action();
	}

	/**
	 * Define and register the action by pushing it into the Automator object
	 */
	public function define_action() {

		$credit_designation_label_plural = get_option( 'credit_designation_label_plural', __( 'CEUs', 'uncanny-ceu' ) );

		$action = array(
			'author'             => Automator()->get_author_name( $this->action_code ),
			'support_link'       => Automator()->get_author_support_link( $this->action_code, 'knowledge-base/uncanny-continuing-education-credits' ),
			'integration'        => self::$integration,
			'code'               => $this->action_code,
			/* translators: Logged-in trigger - Uncanny CEUs. 2. Credit designation label (plural) */
			'sentence'           => sprintf( esc_attr__( 'Award {{a number:%1$s}} of custom %2$s to the user', 'uncanny-automator' ), 'AWARDCEUSAMOUNT:AWARDCEUS', $credit_designation_label_plural ),
			/* translators: Logged-in trigger - Uncanny CEUs. 1. Credit designation label (plural) */
			'select_option_name' => sprintf( esc_attr__( 'Award {{a number}} of custom %1$s to the user', 'uncanny-automator' ), $credit_designation_label_plural ),
			'priority'           => 10,
			'accepted_args'      => 1,
			'execution_function' => array( $this, 'award_ceus' ),
			'options_group'      =>
				array(
					'AWARDCEUS' =>
						array(
							array(
								'option_code' => 'AWARDCEUSDATE',
								'label'       => esc_attr__( 'Date', 'uncanny-automator' ),
								'input_type'  => 'text',
								'required'    => true,
								'description' => __( 'Format: MM/DD/YYYY Example: 12/05/2020', 'uncanny-automator' ),
							),
							array(
								'option_code' => 'AWARDCEUSCOURSE',
								'label'       => esc_attr__( 'Description', 'uncanny-automator' ),
								'input_type'  => 'text',
								'required'    => true,
							),
							array(
								'option_code' => 'AWARDCEUS',
								/* translators: Uncanny CEUs. 1. Credit designation label (plural) */
								'label'       => sprintf( esc_attr__( 'Number of %1$s to award', 'uncanny-automator' ), $credit_designation_label_plural ),
								'input_type'  => 'float',
								'required'    => true,

							),
						),
				),
		);

		Automator()->register->action( $action );
	}

	/**
	 * Validation function when the trigger action is hit
	 *
	 * @param $user_id
	 * @param $action_data
	 * @param $recipe_id
	 */
	public function award_ceus( $user_id, $action_data, $recipe_id, $args ) {

		$date   = Automator()->parse->text( $action_data['meta']['AWARDCEUSDATE'], $recipe_id, $user_id, $args );
		$course = Automator()->parse->text( $action_data['meta']['AWARDCEUSCOURSE'], $recipe_id, $user_id, $args );
		$ceus   = absint( Automator()->parse->text( $action_data['meta']['AWARDCEUS'], $recipe_id, $user_id, $args ) );

		// convert date from user input to accepted input
		$date = date( 'F d Y, g:i:s a', strtotime( $date ) );

		$data = array(
			'course'       => 0, // It is not a real course
			'customCourse' => $course, // The fake course to save data against
			'date'         => $date, // date to store CEU fon in format F d Y, g:i:s a
			'ceus'         => $ceus, // the amount of CEUs
		);

		// The class contains all ceu creation code
		$award_cert_class = \uncanny_ceu\Utilities::get_class_instance( 'AwardCertificate' );

		$version = \uncanny_ceu\Utilities::get_version();
		if ( version_compare( $version, '3.0.7', '>' ) ) {
			$course_data   = $data = array(
				'user'             => new \WP_User( $user_id ),
				'course'           => null,
				'course_completed' => 0,
				'custom_course'    => $course,
				'custom_date'      => $date,
				'custom_ceus'      => $ceus,
				'custom_creation'  => true,
			);
			$returned_data = $award_cert_class->learndash_course_completed( $course_data );
		} else {
			//* @deprecated CEUs 3.1
			$returned_data = $award_cert_class->learndash_before_course_completed( $user_id, 'manual-ceu', true, $data );
		}

		$error = '';
		if ( isset( $returned_data->success ) && false === $returned_data->success ) {
			$error = $returned_data->error;
		}

		Automator()->complete_action( $user_id, $action_data, $recipe_id, $error );

		return;
	}
}
