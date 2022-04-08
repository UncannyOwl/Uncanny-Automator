<?php //phpcs:ignore WordPress.Files.FileName.InvalidClassFileName

namespace Uncanny_Automator;

use Uncanny_Automator_Pro\Wpforms_Pro_Helpers;
use WPForms_Form_Handler;

/**
 * Class Wpforms_Helpers
 *
 * @package Uncanny_Automator
 */
class Wpforms_Helpers {

	/**
	 * Options.
	 *
	 * @var Wpforms_Helpers
	 */
	public $options;

	/**
	 * Pro helpers.
	 *
	 * @var Wpforms_Pro_Helpers
	 */
	public $pro;

	/**
	 * Load options.
	 *
	 * @var bool
	 */
	public $load_options = true;

	/**
	 * Wpforms_Helpers constructor.
	 */
	public function __construct() {

	}

	/**
	 * Set options.
	 *
	 * @param Wpforms_Helpers $options
	 */
	public function setOptions( Wpforms_Helpers $options ) { //phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		$this->options = $options;
	}

	/**
	 * Set pro.
	 *
	 * @param Wpforms_Pro_Helpers $pro
	 */
	public function setPro( Wpforms_Pro_Helpers $pro ) { //phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		$this->pro = $pro;
	}

	/**
	 * List wp_forms.
	 *
	 * @param string $label
	 * @param string $option_code
	 * @param array $args
	 *
	 * @return mixed
	 */

	public function list_wp_forms( $label = null, $option_code = 'WPFFORMS', $args = array() ) {

		if ( ! $this->load_options ) {
			return Automator()->helpers->recipe->build_default_options_array( $label, $option_code );
		}

		if ( ! $label ) {
			$label = esc_attr__( 'Form', 'uncanny-automator' );
		}

		$token        = key_exists( 'token', $args ) ? $args['token'] : false;
		$is_ajax      = key_exists( 'is_ajax', $args ) ? $args['is_ajax'] : false;
		$target_field = key_exists( 'target_field', $args ) ? $args['target_field'] : '';
		$end_point    = key_exists( 'endpoint', $args ) ? $args['endpoint'] : '';
		$options      = array();

		if ( Automator()->helpers->recipe->load_helpers ) {
			$wpforms = new WPForms_Form_Handler();

			$forms = $wpforms->get( '', array( 'orderby' => 'title' ) );

			if ( ! empty( $forms ) ) {
				foreach ( $forms as $form ) {
					$options[ $form->ID ] = esc_html( $form->post_title );
				}
			}
		}
		$type = 'select';

		$option = array(
			'option_code'     => $option_code,
			'label'           => $label,
			'input_type'      => $type,
			'required'        => true,
			'supports_tokens' => $token,
			'is_ajax'         => $is_ajax,
			'fill_values_in'  => $target_field,
			'endpoint'        => $end_point,
			'options'         => $options,
			'relevant_tokens' => array(
				$option_code         => esc_attr__( 'Form title', 'uncanny-automator' ),
				$option_code . '_ID' => esc_attr__( 'Form ID', 'uncanny-automator' ),
			),
		);

		return apply_filters( 'uap_option_list_wp_forms', $option );
	}

	/**
	 * @param $date
	 *
	 * @return string
	 */
	public function get_entry_date( $date ) {
		$datetime_offset = get_option( 'gmt_offset' ) * 3600;

		return sprintf( /* translators: %1$s - date for the entry; %2$s - time for the entry. */
			esc_html__( '%1$s at %2$s', 'wpforms' ),
			date_i18n( 'M j, Y', $date + $datetime_offset ),
			date_i18n( get_option( 'time_format' ), $date + $datetime_offset )
		);
	}

	/**
	 * @param $entry_id
	 *
	 * @return string
	 */
	public function get_entry_user_ip_address( $entry_id ) {
		$user_ip = 'N/A';
		if ( wpforms()->entry && method_exists( wpforms()->entry, 'get' ) ) {
			$entry_details = wpforms()->entry->get( $entry_id, array( 'cap' => false ) );
			$user_ip       = $entry_details->ip_address;
		} elseif ( isset( $_SERVER['HTTP_X_REAL_IP'] ) ) {
			$user_ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_REAL_IP'] ) );
		} elseif ( isset( $_SERVER['REMOTE_ADDR'] ) ) {
			$user_ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		}

		return $user_ip;
	}

	/**
	 * @param $entry_id
	 *
	 * @return false|int|string
	 */
	public function get_entry_entry_date( $entry_id ) {
		$entry_date = current_time( 'timestamp' ); //phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested
		if ( wpforms()->entry && method_exists( wpforms()->entry, 'get' ) ) {
			$entry_details = wpforms()->entry->get( $entry_id, array( 'cap' => false ) );
			$entry_date    = strtotime( $entry_details->date );
		}

		return $entry_date;
	}

	/**
	 * @param $entry_id
	 *
	 * @return mixed
	 */
	public function get_entry_entry_id( $entry_id ) {
		if ( wpforms()->entry && method_exists( wpforms()->entry, 'get' ) ) {
			$entry_details = wpforms()->entry->get( $entry_id, array( 'cap' => false ) );
			$entry_id      = $entry_details->entry_id;
		}

		return $entry_id;
	}
}
