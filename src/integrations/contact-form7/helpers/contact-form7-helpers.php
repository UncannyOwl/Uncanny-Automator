<?php


namespace Uncanny_Automator;


class Contact_Form7_Helpers {
	/**
	 * @var Contact_Form7_Helpers
	 */
	public $options;

	/**
	 * @var \Uncanny_Automator_Pro\Contact_Form7_Pro_Helpers
	 */
	public $pro;

	/**
	 * @param Contact_Form7_Helpers $options
	 */
	public function setOptions( Contact_Form7_Helpers $options ) {
		$this->options = $options;
	}

	/**
	 * @param \Uncanny_Automator_Pro\Contact_Form7_Pro_Helpers $pro
	 */
	public function setPro( \Uncanny_Automator_Pro\Contact_Form7_Pro_Helpers $pro ) {
		$this->pro = $pro;
	}

	/**
	 * @param string $label
	 * @param string $option_code
	 *
	 * @return mixed
	 */
	public function list_contact_form7_forms( $label = null, $option_code = 'CF7FORMS', $args = [] ) {

		if ( ! $label ) {
			$label = __( 'Form', 'uncanny-automator' );
		}
		$token        = key_exists( 'token', $args ) ? $args['token'] : false;
		$is_ajax      = key_exists( 'is_ajax', $args ) ? $args['is_ajax'] : false;
		$target_field = key_exists( 'target_field', $args ) ? $args['target_field'] : '';
		$end_point    = key_exists( 'endpoint', $args ) ? $args['endpoint'] : '';
		$options      = [];

		$args = [
			'post_type'      => 'wpcf7_contact_form',
			'posts_per_page' => 999,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'post_status'    => 'publish',
		];

		global $uncanny_automator;
		$options = $uncanny_automator->helpers->recipe->options->wp_query( $args );
		$type    = 'select';
//		$option = [
//			'option_code' => $option_code,
//			'label'       => $label,
//			'input_type'  => 'select',
//			'required'    => true,
//			'options'     => $options,
//		];
		$option = [
			'option_code'     => $option_code,
			'label'           => $label,
			'input_type'      => $type,
			'required'        => true,
			'supports_tokens' => $token,
			'is_ajax'         => $is_ajax,
			'fill_values_in'  => $target_field,
			'endpoint'        => $end_point,
			'options'         => $options,
			'relevant_tokens' => [
				$option_code          => __( 'Form title', 'uncanny-automator' ),
				$option_code . '_ID'  => __( 'Form ID', 'uncanny-automator' ),
				$option_code . '_URL' => __( 'Form URL', 'uncanny-automator' ),
			],
		];

		return apply_filters( 'uap_option_list_contact_form7_forms', $option );
	}
}