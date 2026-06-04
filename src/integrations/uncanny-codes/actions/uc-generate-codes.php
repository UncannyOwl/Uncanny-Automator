<?php

namespace Uncanny_Automator\Integrations\Uncanny_Codes;

use uncanny_learndash_codes\Database;

/**
 * Class UC_GENERATE_CODES
 *
 * @package Uncanny_Automator
 * @property \Uncanny_Automator\Integrations\Uncanny_Codes\Uc_Helpers $item_helpers
 */
class UC_GENERATE_CODES extends \Uncanny_Automator\Recipe\Action {

	/**
	 * Setup action configuration.
	 *
	 * @return void
	 */
	protected function setup_action() {
		$this->set_integration( 'UNCANNYCODE' );
		$this->set_action_code( 'UCGENERATECODES' );
		$this->set_action_meta( 'WPUCGENERATECODES' );
		$this->set_is_pro( false );
		$this->set_requires_user( false );
		// translators: %1$s is the batch of codes.
		$this->set_sentence( sprintf( esc_html_x( 'Generate {{a batch of codes:%1$s}} for Automator', 'Uncanny Codes', 'uncanny-automator' ), $this->get_action_meta() ) );
		$this->set_readable_sentence( esc_html_x( 'Generate {{a batch of codes}} for Automator', 'Uncanny Codes', 'uncanny-automator' ) );
		$this->set_action_tokens(
			array(
				'BATCH_ID'        => array(
					'name' => esc_html_x( 'Batch ID', 'Uncanny Codes', 'uncanny-automator' ),
					'type' => 'int',
				),
				'CODES_GENERATED' => array(
					'name' => esc_html_x( 'Generated codes', 'Uncanny Codes', 'uncanny-automator' ),
					'type' => 'text',
				),
			),
			$this->get_action_code()
		);
	}

	/**
	 * Define action options.
	 *
	 * @return array[]
	 */
	public function options() {
		return array(
			array(
				'option_code'           => 'UCBATCHNAME',
				'label'                 => esc_html_x( 'Batch name', 'Uncanny Codes', 'uncanny-automator' ),
				'input_type'            => 'text',
				'required'              => true,
				'supports_custom_value' => true,
			),
			array(
				'option_code'           => 'UCNOOFCODES',
				'label'                 => esc_html_x( 'Number of codes', 'Uncanny Codes', 'uncanny-automator' ),
				'input_type'            => 'int',
				'required'              => true,
				'supports_custom_value' => true,
			),
			array(
				'option_code'           => 'UCUSERPERCODE',
				'label'                 => esc_html_x( 'Number of uses per code', 'Uncanny Codes', 'uncanny-automator' ),
				'input_type'            => 'int',
				'required'              => true,
				'default_value'         => 1,
				'supports_custom_value' => true,
			),
			array(
				'option_code'           => 'UCEXPIRYDATE',
				'label'                 => esc_html_x( 'Expiry date', 'Uncanny Codes', 'uncanny-automator' ),
				'input_type'            => 'date',
				'required'              => false,
				'supports_custom_value' => true,
			),
			array(
				'option_code'           => 'UCEXPIRYTIME',
				'label'                 => esc_html_x( 'Expiry time', 'Uncanny Codes', 'uncanny-automator' ),
				'input_type'            => 'time',
				'required'              => false,
				'supports_custom_value' => true,
			),
			array(
				'option_code'           => 'UCPREFIX',
				'label'                 => esc_html_x( 'Prefix', 'Uncanny Codes', 'uncanny-automator' ),
				'input_type'            => 'text',
				'required'              => false,
				'supports_custom_value' => true,
			),
			array(
				'option_code'           => 'UCSUFFIX',
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

		$batch_name         = isset( $parsed['UCBATCHNAME'] ) ? sanitize_text_field( $parsed['UCBATCHNAME'] ) : '';
		$no_of_codes        = isset( $parsed['UCNOOFCODES'] ) ? absint( $parsed['UCNOOFCODES'] ) : 0;
		$no_of_use_per_code = isset( $parsed['UCUSERPERCODE'] ) ? absint( $parsed['UCUSERPERCODE'] ) : 1;
		$expiry_date        = isset( $parsed['UCEXPIRYDATE'] ) ? sanitize_text_field( $parsed['UCEXPIRYDATE'] ) : '';
		$expiry_time        = isset( $parsed['UCEXPIRYTIME'] ) ? sanitize_text_field( $parsed['UCEXPIRYTIME'] ) : '';
		$prefix             = isset( $parsed['UCPREFIX'] ) ? sanitize_text_field( $parsed['UCPREFIX'] ) : '';
		$suffix             = isset( $parsed['UCSUFFIX'] ) ? sanitize_text_field( $parsed['UCSUFFIX'] ) : '';
		$character_type     = array( 'uppercase-letters', 'numbers' );
		$codes              = array();

		$gen_args = array(
			'generation_type' => 'auto',
			'coupon_amount'   => $no_of_codes,
			'custom_codes'    => '',
			'dashes'          => array( 4, 4, 4, 4, 4 ),
			'prefix'          => $prefix,
			'suffix'          => $suffix,
			'code_length'     => 20,
			'character_type'  => $character_type,
		);

		// Sanitize values.
		$data = array(
			'coupon-amount'         => $no_of_codes,
			'coupon-prefix'         => $prefix,
			'coupon-suffix'         => $suffix,
			'coupon-dash'           => '4-4-4-4-4',
			'coupon-length'         => '20',
			'generation-type'       => 'auto',
			'dependency'            => 'automator',
			'coupon-for'            => 'automator',
			'group-name'            => $batch_name,
			'coupon-courses'        => '',
			'coupon-group'          => '',
			'expiry-date'           => $expiry_date,
			'expiry-time'           => $expiry_time,
			'coupon-paid-unpaid'    => 'default',
			'coupon-max-usage'      => $no_of_use_per_code,
			'coupon-character-type' => $character_type,
		);

		$data     = apply_filters( 'automator_uo_codes_generate_group_data', $data, $this );
		$group_id = Database::add_code_group_batch( $data );
		$inserted = Database::add_codes_to_batch( $group_id, $codes, $gen_args );

		if ( 0 === $inserted && $inserted !== $no_of_codes ) {
			$this->add_log_error( esc_html_x( 'Something went wrong! Codes not generated, Try again.', 'Uncanny Codes', 'uncanny-automator' ) );
			return false;
		}

		$this->hydrate_tokens(
			array(
				'BATCH_ID'        => $group_id,
				'CODES_GENERATED' => $this->get_generated_codes( $group_id ),
			)
		);

		return true;
	}

	/**
	 * Get generated codes as CSV string.
	 *
	 * @param int $group_id The group ID.
	 *
	 * @return string
	 */
	private function get_generated_codes( $group_id ) {

		global $wpdb;

		$tbl_codes = $wpdb->prefix . \uncanny_learndash_codes\Config::$tbl_codes;

		$codes = $wpdb->get_col( $wpdb->prepare( "SELECT `code` FROM $tbl_codes WHERE code_group = %d", $group_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return implode( ', ', $codes );
	}
}
