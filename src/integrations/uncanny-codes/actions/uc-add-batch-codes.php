<?php

namespace Uncanny_Automator\Integrations\Uncanny_Codes;

use uncanny_learndash_codes;

/**
 * Class UC_ADD_BATCH_CODES
 *
 * @package Uncanny_Automator
 * @property \Uncanny_Automator\Integrations\Uncanny_Codes\Uc_Helpers $item_helpers
 */
class UC_ADD_BATCH_CODES extends \Uncanny_Automator\Recipe\Action {

	/**
	 * Setup action configuration.
	 *
	 * @return void
	 */
	protected function setup_action() {
		$this->set_integration( 'UNCANNYCODE' );
		$this->set_action_code( 'UCADDBATCHCODES' );
		$this->set_action_meta( 'WPUCADDBATCHCODES' );
		$this->set_requires_user( false );
		$this->set_is_pro( false );
		// translators: %1$s is the number of codes, %2$s is the batch.
		$this->set_sentence( sprintf( esc_html_x( 'Add {{a number of:%1$s}} codes in {{a batch:%2$s}}', 'Uncanny Codes', 'uncanny-automator' ), 'UCNUMBERS:' . $this->get_action_meta(), $this->get_action_meta() ) );
		$this->set_readable_sentence( esc_html_x( 'Add {{a number of}} codes in {{a batch}}', 'Uncanny Codes', 'uncanny-automator' ) );
	}

	/**
	 * Define action options.
	 *
	 * @return array[]
	 */
	public function options() {
		return array(
			array(
				'option_code'           => 'UCNUMBERS',
				'label'                 => esc_html_x( 'Number', 'Uncanny Codes', 'uncanny-automator' ),
				'input_type'            => 'int',
				'required'              => true,
				'placeholder'           => esc_html_x( 'Number of unique codes', 'Uncanny Codes', 'uncanny-automator' ),
				'supports_custom_value' => true,
			),
			array(
				'option_code'           => $this->get_action_meta(),
				'label'                 => esc_html_x( 'Batch', 'Uncanny Codes', 'uncanny-automator' ),
				'input_type'            => 'select',
				'required'              => true,
				'remote_data'           => $this->item_helpers->remote_data_load_config( 'batches_strict' ),
				'options'               => array(),
				'supports_custom_value' => true,
			),
			array(
				'option_code'           => 'UCADDPREFIX',
				'label'                 => esc_html_x( 'Prefix', 'Uncanny Codes', 'uncanny-automator' ),
				'input_type'            => 'text',
				'required'              => false,
				'supports_custom_value' => true,
			),
			array(
				'option_code'           => 'UCADDUSFFIX',
				'label'                 => esc_html_x( 'Suffix', 'Uncanny Codes', 'uncanny-automator' ),
				'input_type'            => 'text',
				'required'              => false,
				'supports_custom_value' => true,
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

		$batch_id        = isset( $parsed[ $this->get_action_meta() ] ) ? absint( wp_strip_all_tags( $parsed[ $this->get_action_meta() ] ) ) : 0;
		$number_of_codes = isset( $parsed['UCNUMBERS'] ) ? absint( sanitize_text_field( $parsed['UCNUMBERS'] ) ) : 0;
		$prefix          = isset( $parsed['UCADDPREFIX'] ) ? sanitize_text_field( $parsed['UCADDPREFIX'] ) : '';
		$suffix          = isset( $parsed['UCADDUSFFIX'] ) ? sanitize_text_field( $parsed['UCADDUSFFIX'] ) : '';

		if ( $batch_id <= 0 || $number_of_codes <= 0 ) {
			$this->add_log_error( esc_html_x( 'Invalid request.', 'Uncanny Codes', 'uncanny-automator' ) );
			return false;
		}

		$group_details = \uncanny_learndash_codes\Database::get_group_details( $batch_id );

		// Check if batch is valid.
		if ( empty( $group_details ) ) {
			$this->add_log_error( esc_html_x( 'Invalid batch provided.', 'Uncanny Codes', 'uncanny-automator' ) );
			return false;
		}

		$generation_type = $group_details['generation_type'];
		$dashes          = $group_details['dashes'];
		$character_type  = $group_details['character_type'];

		// Use batch defaults when prefix/suffix are empty (Yoda style).
		if ( empty( $prefix ) && ! empty( $group_details['prefix'] ) ) {
			$prefix = $group_details['prefix'];
		}
		if ( empty( $suffix ) && ! empty( $group_details['suffix'] ) ) {
			$suffix = $group_details['suffix'];
		}

		// Calculate code_length from existing batch codes if available.
		$code_length = $this->get_batch_code_length( $batch_id, $prefix, $suffix, $dashes );

		$gen_args = array(
			'generation_type' => $generation_type,
			'coupon_amount'   => (int) $number_of_codes,
			'custom_codes'    => '',
			'dashes'          => $dashes,
			'prefix'          => $prefix,
			'suffix'          => $suffix,
			'code_length'     => $code_length,
			'character_type'  => $character_type,
		);

		$gen_args = apply_filters( 'ulc_automatr_codes_group_args', $gen_args, $batch_id, $parsed );

		$inserted = \uncanny_learndash_codes\Database::add_codes_to_batch( $batch_id, array(), $gen_args );

		if ( $inserted ) {
			do_action( 'ulc_codes_group_generated', $batch_id, $inserted );
		}

		return true;
	}

	/**
	 * Calculate code length by sampling existing codes from the batch.
	 *
	 * @param int    $batch_id The batch ID.
	 * @param string $prefix   The code prefix.
	 * @param string $suffix   The code suffix.
	 * @param array  $dashes   The dash configuration (unused, for compatibility).
	 *
	 * @return int The calculated code length.
	 */
	private function get_batch_code_length( $batch_id, $prefix, $suffix, $dashes ) {

		global $wpdb;
		$tbl_codes = $wpdb->prefix . \uncanny_learndash_codes\Config::$tbl_codes;

		// Sample existing codes from the batch to determine length.
		$sample_code = $wpdb->get_var( $wpdb->prepare( "SELECT code FROM $tbl_codes WHERE code_group = %d LIMIT 1", $batch_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// If batch has existing codes, calculate length from sample (Yoda style).
		if ( ! empty( $sample_code ) ) {
			// Remove dashes and prefix/suffix to get the actual code length.
			$code_without_dashes = str_replace( '-', '', $sample_code );
			$code_length         = strlen( $code_without_dashes ) - strlen( $prefix ) - strlen( $suffix );

			// Ensure positive length.
			return max( 1, $code_length );
		}

		// Fallback to 20 for empty batches (matches system default for new code generation).
		return 20 - strlen( $prefix ) - strlen( $suffix );
	}
}
