<?php

namespace Uncanny_Automator\Integrations\Uncanny_Ceus;

/**
 * Class Uncanny_Ceus_Tokens
 *
 * Shared trigger token definitions and hydration for the Uncanny CEUs integration.
 *
 * Token IDs are stored in recipe databases and must NEVER change.
 *
 * @package Uncanny_Automator\Integrations\Uncanny_Ceus
 */
class Uncanny_Ceus_Tokens {

	/**
	 * Helpers collaborator providing host-plugin lookups.
	 *
	 * @var Uncanny_Ceus_Helpers
	 */
	private $helpers;

	/**
	 * Constructor.
	 *
	 * @param Uncanny_Ceus_Helpers $helpers Helpers collaborator.
	 */
	public function __construct( Uncanny_Ceus_Helpers $helpers ) {
		$this->helpers = $helpers;
	}

	/**
	 * Token definitions used by the legacy "earns CEUs" pair of triggers.
	 *
	 * Token IDs (AMOUNTSCEUS, AMOUNTSCEUS_title, AMOUNTSCEUS_date) are the
	 * exact keys written by the pre-migration code via insert_trigger_meta().
	 *
	 * @return array[]
	 */
	public function ceus_award_tokens() {

		$credit_designation_label_plural = $this->get_credit_designation_label_plural();

		return array(
			array(
				'tokenId'   => 'AMOUNTSCEUS',
				// translators: %1$s is the credit designation label (plural).
				'tokenName' => sprintf( esc_html_x( '%1$s amount', 'Uncanny CEUs', 'uncanny-automator' ), $credit_designation_label_plural ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'AMOUNTSCEUS_title',
				// translators: %1$s is the credit designation label (plural).
				'tokenName' => sprintf( esc_html_x( 'Course or %1$s title', 'Uncanny CEUs', 'uncanny-automator' ), $credit_designation_label_plural ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'AMOUNTSCEUS_date',
				'tokenName' => esc_html_x( 'Date awarded', 'Uncanny CEUs', 'uncanny-automator' ),
				'tokenType' => 'date',
			),
		);
	}

	/**
	 * Hydrate the legacy "earns CEUs" tokens from hook arguments.
	 *
	 * Hook signature: ceus_after_updated_user_ceu_record(
	 *   $current_user, $is_manual_creation, $completion_date,
	 *   $current_course_id, $current_course_title, $course_slug, $ceu_value
	 * )
	 *
	 * @param array $hook_args The hook arguments.
	 *
	 * @return array<string,mixed>
	 */
	public function hydrate_ceus_award_tokens( $hook_args ) {

		$completion_date      = $hook_args[2] ?? '';
		$current_course_title = $hook_args[4] ?? '';
		$ceu_value            = $hook_args[6] ?? '';

		return array(
			'AMOUNTSCEUS'       => (float) $ceu_value,
			'AMOUNTSCEUS_title' => $current_course_title,
			'AMOUNTSCEUS_date'  => date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), absint( $completion_date ) ),
		);
	}

	/**
	 * Token definitions used by the "earns N or more" trigger.
	 *
	 * @return array[]
	 */
	public function ceus_threshold_tokens() {

		return array(
			array(
				'tokenId'   => 'CEUS_AMOUNT',
				'tokenName' => esc_html_x( 'CEUs amount', 'Uncanny CEUs', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'CEUS_TITLE',
				'tokenName' => esc_html_x( 'Course or CEUs title', 'Uncanny CEUs', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'CEUS_DATE_AWARDED',
				'tokenName' => esc_html_x( 'Date awarded', 'Uncanny CEUs', 'uncanny-automator' ),
				'tokenType' => 'date',
			),
			array(
				'tokenId'   => 'CEUS_LABEL',
				'tokenName' => esc_html_x( 'Credit label for CEUs', 'Uncanny CEUs', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
		);
	}

	/**
	 * Hydrate the "earns N or more" trigger tokens.
	 *
	 * @param array $hook_args The hook arguments.
	 *
	 * @return array<string,mixed>
	 */
	public function hydrate_ceus_threshold_tokens( $hook_args ) {

		$completion_date      = $hook_args[2] ?? '';
		$current_course_title = $hook_args[4] ?? '';
		$ceu_value            = $hook_args[6] ?? '';

		return array(
			'CEUS_AMOUNT'       => $ceu_value,
			'CEUS_TITLE'        => $current_course_title,
			'CEUS_DATE_AWARDED' => date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), absint( $completion_date ) ),
			'CEUS_LABEL'        => $this->get_credit_designation_label_plural(),
		);
	}

	/**
	 * Get the plural credit designation label configured by the host plugin.
	 *
	 * @return string
	 */
	protected function get_credit_designation_label_plural() {
		// phpcs:ignore WordPress.WP.I18n.TextDomainMismatch
		return get_option( 'credit_designation_label_plural', __( 'CEUs', 'uncanny-ceu' ) );
	}
}
