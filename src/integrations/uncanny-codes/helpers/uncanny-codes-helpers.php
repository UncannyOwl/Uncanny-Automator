<?php


namespace Uncanny_Automator;

/**
 * Class Uncanny_Codes_Helpers
 *
 * @package Uncanny_Automator
 */
class Uncanny_Codes_Helpers {

	/**
	 * @var Uncanny_Codes_Helpers
	 */
	public $options;

	/**
	 * @var bool
	 */
	public $load_options;

	/**
	 * Uoa_Helpers constructor.
	 */
	public function __construct() {

		$this->load_options = true;
	}

	/**
	 * @param Uncanny_Codes_Helpers $options
	 */
	public function setOptions( Uncanny_Codes_Helpers $options ) {
		$this->options = $options;
	}

	/**
	 * @param $label
	 * @param $option_code
	 *
	 * @return array|mixed|void
	 */
	public function get_all_codes( $label = null, $option_code = 'UNCANNYCODES' ) {
		if ( ! $this->load_options ) {

			return Automator()->helpers->recipe->build_default_options_array( $label, $option_code );
		}

		if ( ! $label ) {
			$label = esc_attr__( 'Code', 'uncanny-automator' );
		}

		global $wpdb;

		$options = array();

		$all_codes = $wpdb->get_results( 'SELECT ID,code FROM ' . $wpdb->prefix . 'uncanny_codes_codes', ARRAY_A );

		foreach ( $all_codes as $code ) {
			$options[ $code['ID'] ] = $code['code'];
		}

		$option = array(
			'option_code'     => $option_code,
			'label'           => $label,
			'input_type'      => 'select',
			'required'        => true,
			'options'         => $options,
			'relevant_tokens' => array(
				$option_code         => esc_attr__( 'Code', 'uncanny-automator' ),
				$option_code . '_ID' => esc_attr__( 'Code ID', 'uncanny-automator' ),
			),
		);

		return apply_filters( 'uap_option_get_all_codes', $option );
	}

	/**
	 * @param $label
	 * @param $option_code
	 *
	 * @return array|mixed|void
	 */
	public function get_all_code_prefix( $label = null, $option_code = 'UCPREFIX' ) {
		if ( ! $this->load_options ) {

			return Automator()->helpers->recipe->build_default_options_array( $label, $option_code );
		}

		if ( ! $label ) {
			$label = esc_attr__( 'Prefix', 'uncanny-automator' );
		}

		global $wpdb;

		$options = array();

		$all_codes = $wpdb->get_results( 'SELECT DISTINCT prefix FROM ' . $wpdb->prefix . 'uncanny_codes_groups', ARRAY_A );
		if ( ! $all_codes ) {
			return Automator()->helpers->recipe->build_default_options_array( $label, $option_code );
		}
		foreach ( $all_codes as $code ) {
			if ( ! empty( $code['prefix'] ) ) {
				$prefix             = Automator()->utilities->automator_sanitize( $code['prefix'] );
				$options[ $prefix ] = $code['prefix'];
			}
		}

		$option = array(
			'option_code'     => $option_code,
			'label'           => $label,
			'input_type'      => 'select',
			'required'        => true,
			'options'         => $options,
			'relevant_tokens' => array(
				$option_code => esc_attr__( 'Prefix', 'uncanny-automator' ),
			),
		);

		return apply_filters( 'uap_option_get_all_code_prefix', $option );
	}

	/**
	 * @param $label
	 * @param $option_code
	 *
	 * @return array|mixed|void
	 */
	public function get_all_code_suffix( $label = null, $option_code = 'UCSUFFIX' ) {
		if ( ! $this->load_options ) {

			return Automator()->helpers->recipe->build_default_options_array( $label, $option_code );
		}

		if ( ! $label ) {
			$label = esc_attr__( 'Suffix', 'uncanny-automator' );
		}

		global $wpdb;

		$options = array();

		$all_codes = $wpdb->get_results( 'SELECT DISTINCT suffix FROM ' . $wpdb->prefix . 'uncanny_codes_groups', ARRAY_A );
		if ( ! $all_codes ) {
			return Automator()->helpers->recipe->build_default_options_array( $label, $option_code );
		}

		foreach ( $all_codes as $code ) {
			if ( ! empty( $code['suffix'] ) ) {
				$suffix             = Automator()->utilities->automator_sanitize( $code['suffix'] );
				$options[ $suffix ] = $code['suffix'];
			}
		}

		$option = array(
			'option_code'     => $option_code,
			'label'           => $label,
			'input_type'      => 'select',
			'required'        => true,
			'options'         => $options,
			'relevant_tokens' => array(
				$option_code => esc_attr__( 'Suffix', 'uncanny-automator' ),
			),
		);

		return apply_filters( 'uap_option_get_all_code_suffix', $option );
	}

	/**
	 * @param null $label
	 * @param string $option_code
	 * @param bool $is_any
	 *
	 * @return array|mixed|void
	 */
	public function get_all_code_batch( $label = null, $option_code = 'UCBATCH', $is_any = false ) {
		if ( ! $this->load_options ) {

			return Automator()->helpers->recipe->build_default_options_array( $label, $option_code );
		}

		if ( ! $label ) {
			$label = esc_attr__( 'Prefix', 'uncanny-automator' );
		}

		global $wpdb;

		$options = array();
		if ( $is_any ) {
			$options['-1'] = __( 'Any batch', 'uncanny-automator' );
		}
		$option      = array(
			'option_code'     => $option_code,
			'label'           => $label,
			'input_type'      => 'select',
			'required'        => true,
			'options'         => $options,
			'relevant_tokens' => array(
				$option_code              => esc_attr__( 'Batch', 'uncanny-automator' ),
				'UNCANNYCODESBATCHEXPIRY' => esc_attr__( 'Batch expiry date', 'uncanny-automator' ),
			),
		);
		$all_batches = $wpdb->get_results( 'SELECT DISTINCT id, name FROM ' . $wpdb->prefix . 'uncanny_codes_groups', ARRAY_A );

		if ( empty( $all_batches ) ) {
			return apply_filters( 'uap_option_get_all_code_batch', $option );
		}
		foreach ( $all_batches as $batch ) {
			if ( ! empty( $batch['name'] ) ) {
				$options[ $batch['id'] ] = $batch['name'];
			}
		}

		natcasesort( $options );
		$option['options'] = $options;

		return apply_filters( 'uap_option_get_all_code_batch', $option );
	}

	/**
	 * Method uc_get_batch_info.
	 *
	 * @return array A list of data returned with batch info.
	 */
	public function uc_get_batch_info( $batch_id ) {
		global $wpdb;

		$tbl_groups = $wpdb->prefix . 'uncanny_codes_groups';
		$tbl_codes  = $wpdb->prefix . 'uncanny_codes_codes';

		$batch_data = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$tbl_groups} WHERE `ID` = %d GROUP BY ID", $batch_id ), OBJECT );  //phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$codes_data = $wpdb->get_row( $wpdb->prepare( "SELECT GROUP_CONCAT(`code`) as codes FROM {$tbl_codes} WHERE `code_group` = %d", $batch_id ), OBJECT );  //phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return array(
			'batch_data' => $batch_data,
			'codes_data' => $codes_data,
		);

	}

	/**
	 * @param $coupon_id
	 *
	 * @return string code
	 */
	public function uc_get_code_redeemed( $coupon_id ) {
		global $wpdb;
		$tbl_codes = $wpdb->prefix . 'uncanny_codes_codes';
		$code      = $wpdb->get_var( $wpdb->prepare( "SELECT `code` FROM {$tbl_codes} WHERE `ID` = %d", $coupon_id ) );  //phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return $code;

	}

}
