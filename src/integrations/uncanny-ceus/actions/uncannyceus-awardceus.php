<?php

namespace Uncanny_Automator\Integrations\Uncanny_Ceus;

use Uncanny_Automator\Recipe\Action;

/**
 * Class UNCANNYCEUS_AWARDCEUS
 *
 * Awards a custom CEU certificate to the user.
 *
 * @package Uncanny_Automator\Integrations\Uncanny_Ceus
 *
 * @property Uncanny_Ceus_Helpers $item_helpers
 */
class UNCANNYCEUS_AWARDCEUS extends Action {

	/**
	 * Setup action.
	 *
	 * @return void
	 */
	protected function setup_action() {

		// phpcs:ignore WordPress.WP.I18n.TextDomainMismatch
		$credit_designation_label_plural = get_option( 'credit_designation_label_plural', __( 'CEUs', 'uncanny-ceu' ) );

		$this->set_integration( 'UNCANNYCEUS' );
		$this->set_action_code( 'AWARDCEUS' );
		$this->set_action_meta( 'AWARDCEUS' );

		// translators: %1$s is the option code, %2$s is the credit designation label (plural).
		$this->set_sentence( sprintf( esc_html_x( 'Award {{a number:%1$s}} of custom %2$s to the user', 'Uncanny CEUs', 'uncanny-automator' ), 'AWARDCEUSAMOUNT:' . $this->get_action_meta(), $credit_designation_label_plural ) );
		// translators: %1$s is the credit designation label (plural).
		$this->set_readable_sentence( sprintf( esc_html_x( 'Award {{a number}} of custom %1$s to the user', 'Uncanny CEUs', 'uncanny-automator' ), $credit_designation_label_plural ) );
	}

	/**
	 * Action options.
	 *
	 * @return array[]
	 */
	public function options() {

		// phpcs:ignore WordPress.WP.I18n.TextDomainMismatch
		$credit_designation_label_plural = get_option( 'credit_designation_label_plural', __( 'CEUs', 'uncanny-ceu' ) );

		return array(
			array(
				'option_code' => 'AWARDCEUSDATE',
				'label'       => esc_html_x( 'Date', 'Uncanny CEUs', 'uncanny-automator' ),
				'input_type'  => 'text',
				'required'    => true,
				'description' => esc_html_x( 'Format: MM/DD/YYYY Example: 12/05/2020', 'Uncanny CEUs', 'uncanny-automator' ),
			),
			array(
				'option_code' => 'AWARDCEUSCOURSE',
				'label'       => esc_html_x( 'Description', 'Uncanny CEUs', 'uncanny-automator' ),
				'input_type'  => 'text',
				'required'    => true,
			),
			array(
				'option_code' => $this->get_action_meta(),
				// translators: %1$s is the credit designation label (plural).
				'label'       => sprintf( esc_html_x( 'Number of %1$s to award', 'Uncanny CEUs', 'uncanny-automator' ), $credit_designation_label_plural ),
				'input_type'  => 'float',
				'required'    => true,
			),
		);
	}

	/**
	 * Process the action.
	 *
	 * @param int   $user_id     The user ID.
	 * @param array $action_data The action configuration.
	 * @param int   $recipe_id   The recipe ID.
	 * @param array $args        Additional arguments.
	 * @param array $parsed      Parsed token values.
	 *
	 * @return bool
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		if ( ! class_exists( '\\uncanny_ceu\\Utilities' ) ) {
			$this->add_log_error( esc_html_x( 'Uncanny CEUs plugin is not available.', 'Uncanny CEUs', 'uncanny-automator' ) );
			return false;
		}

		$date_input = $parsed['AWARDCEUSDATE'] ?? '';
		$course     = $parsed['AWARDCEUSCOURSE'] ?? '';
		$ceus       = $parsed[ $this->get_action_meta() ] ?? '';

		// Convert user-entered date to the format the host plugin expects.
		$date = wp_date( 'F d Y, g:i:s a', strtotime( $date_input ) );

		$award_cert_class = \uncanny_ceu\Utilities::get_class_instance( 'AwardCertificate' );

		if ( ! is_object( $award_cert_class ) ) {
			$this->add_log_error( esc_html_x( 'Uncanny CEUs AwardCertificate class is not available.', 'Uncanny CEUs', 'uncanny-automator' ) );
			return false;
		}

		$version = \uncanny_ceu\Utilities::get_version();

		if ( version_compare( $version, '3.0.7', '>' ) ) {
			$course_data = array(
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
			// @deprecated CEUs 3.1 — host plugin legacy entry point.
			$legacy_data = array(
				'course'       => 0,
				'customCourse' => $course,
				'date'         => $date,
				'ceus'         => $ceus,
			);

			$returned_data = $award_cert_class->learndash_before_course_completed( $user_id, 'manual-ceu', true, $legacy_data );
		}

		if ( is_object( $returned_data ) && isset( $returned_data->success ) && false === $returned_data->success ) {
			$this->add_log_error( (string) ( $returned_data->error ?? '' ) );
			return false;
		}

		return true;
	}
}
