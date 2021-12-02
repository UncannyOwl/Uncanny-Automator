<?php


namespace Uncanny_Automator;

use Uncanny_Automator_Pro\Contact_Form7_Pro_Helpers;

/**
 * Class Contact_Form7_Helpers
 *
 * @package Uncanny_Automator
 */
class Contact_Form7_Helpers {
	/**
	 * @var Contact_Form7_Helpers
	 */
	public $options;

	/**
	 * @var Contact_Form7_Pro_Helpers
	 */
	public $pro;

	/**
	 * @var bool
	 */
	public $load_options;

	/**
	 * Contact_Form7_Helpers constructor.
	 */
	public function __construct() {

		$this->load_options = Automator()->helpers->recipe->maybe_load_trigger_options( __CLASS__ );
	}

	/**
	 * @param Contact_Form7_Helpers $options
	 */
	public function setOptions( Contact_Form7_Helpers $options ) {
		$this->options = $options;
	}

	/**
	 * @param Contact_Form7_Pro_Helpers $pro
	 */
	public function setPro( Contact_Form7_Pro_Helpers $pro ) {
		$this->pro = $pro;
	}

	/**
	 * @param string $label
	 * @param string $option_code
	 *
	 * @return mixed
	 */
	public function list_contact_form7_forms( $label = null, $option_code = 'CF7FORMS', $args = array() ) {
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

		$args = array(
			'post_type'      => 'wpcf7_contact_form',
			'posts_per_page' => 999,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'post_status'    => 'publish',
		);

		$options = Automator()->helpers->recipe->options->wp_query( $args );
		$type    = 'select';

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
				$option_code          => esc_attr__( 'Form title', 'uncanny-automator' ),
				$option_code . '_ID'  => esc_attr__( 'Form ID', 'uncanny-automator' ),
				$option_code . '_URL' => esc_attr__( 'Form URL', 'uncanny-automator' ),
			),
		);

		return apply_filters( 'uap_option_list_contact_form7_forms', $option );
	}
}
