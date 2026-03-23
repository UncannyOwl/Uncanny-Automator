<?php

namespace Uncanny_Automator;

use Uncanny_Automator\Recipe;
use uncanny_learndash_codes;

/**
 * Class UC_ADD_BATCH_CODES
 *
 * @package Uncanny_Automator
 */
class UC_ADD_BATCH_CODES {

	use Recipe\Actions;

	/**
	 * Set up Automator action constructor.
	 */
	public function __construct() {
		$this->setup_action();
	}

	/**
	 * Define and register the action by pushing it into the Automator object
	 */
	protected function setup_action() {
		$this->set_integration( 'UNCANNYCODE' );
		$this->set_action_code( 'UCADDBATCHCODES' );
		$this->set_action_meta( 'WPUCADDBATCHCODES' );
		$this->set_requires_user( false );
		$this->set_is_pro( false );

		/* translators: Action - Uncanny Codes */
		$this->set_sentence( sprintf( esc_attr__( 'Add {{a number of:%1$s}} codes in {{a batch:%2$s}}', 'uncanny-automator' ), 'UCNUMBERS:' . $this->get_action_meta(), $this->get_action_meta() ) );

		/* translators: Action - Uncanny Codes */
		$this->set_readable_sentence( esc_attr__( 'Add {{a number of}} codes in {{a batch}}', 'uncanny-automator' ) );

		$this->set_options_callback( array( $this, 'load_options' ) );
		$this->register_action();
	}

	/**
	 * load_options
	 *
	 * @return array
	 */
	public function load_options() {

		$options = array(
			'options_group' => array(
				$this->get_action_meta() => array(
					Automator()->helpers->recipe->field->int(
						array(
							'option_code' => 'UCNUMBERS',
							'label'       => esc_attr__( 'Number', 'uncanny-automator' ),
							'placeholder' => esc_attr__( 'Number of unique codes', 'uncanny-automator' ),
						)
					),
					Automator()->helpers->recipe->uncanny_codes->options->get_all_code_batch(
						esc_html__( 'Batch', 'uncanny-automator' ),
						$this->get_action_meta(),
						false
					),
					Automator()->helpers->recipe->field->text(
						array(
							'option_code' => 'UCADDPREFIX',
							'label'       => esc_attr__( 'Prefix', 'uncanny-automator' ),
							'placeholder' => '',
							'required'    => false,
						)
					),
					Automator()->helpers->recipe->field->text(
						array(
							'option_code' => 'UCADDUSFFIX',
							'label'       => esc_attr__( 'Suffix', 'uncanny-automator' ),
							'placeholder' => '',
							'required'    => false,
						)
					),
				),
			),
		);

		return Automator()->utilities->keep_order_of_options( $options );
	}

	/**
	 * Process the action.
	 *
	 * @param $user_id
	 * @param $action_data
	 * @param $recipe_id
	 * @param $args
	 * @param $parsed
	 *
	 * @return void.
	 * @throws \Exception
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {
		$batch_id        = isset( $parsed[ $this->get_action_meta() ] ) ? absint( wp_strip_all_tags( $parsed[ $this->get_action_meta() ] ) ) : 0;
		$number_of_codes = isset( $parsed['UCNUMBERS'] ) ? absint( sanitize_text_field( $parsed['UCNUMBERS'] ) ) : 0;
		$prefix          = isset( $parsed['UCADDPREFIX'] ) ? sanitize_text_field( $parsed['UCADDPREFIX'] ) : '';
		$suffix          = isset( $parsed['UCADDUSFFIX'] ) ? sanitize_text_field( $parsed['UCADDUSFFIX'] ) : '';

		if ( $batch_id <= 0 || $number_of_codes <= 0 ) {
			Automator()->complete->action( $user_id, $action_data, $recipe_id, esc_html__( 'Invalid request.', 'uncanny-automator' ) );

			return;
		}

		$group_details = \uncanny_learndash_codes\Database::get_group_details( $batch_id );

		// Check if batch is valid.
		if ( empty( $group_details ) ) {
			Automator()->complete->action( $user_id, $action_data, $recipe_id, esc_html__( 'Invalid batch provided.', 'uncanny-automator' ) );

			return;
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

		$args = array(
			'generation_type' => $generation_type,
			'coupon_amount'   => (int) $number_of_codes,
			'custom_codes'    => '',
			'dashes'          => $dashes,
			'prefix'          => $prefix,
			'suffix'          => $suffix,
			'code_length'     => $code_length,
			'character_type'  => $character_type,
		);

		$args = apply_filters( 'ulc_automatr_codes_group_args', $args, $batch_id, $parsed );

		$inserted = \uncanny_learndash_codes\Database::add_codes_to_batch( $batch_id, array(), $args );

		if ( $inserted ) {
			do_action( 'ulc_codes_group_generated', $batch_id, $inserted );
		}

		Automator()->complete->action( $user_id, $action_data, $recipe_id );
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
