<?php

namespace Uncanny_Automator;

use Uncanny_Automator_Pro\Give_Pro_Helpers;

/**
 * Class Give_Helpers
 * @package Uncanny_Automator
 */
class Give_Helpers {

	/**
	 * @var Give_Helpers
	 */
	public $options;
	/**
	 * @var Give_Pro_Helpers
	 */
	public $pro;

	/**
	 * Give_Helpers constructor.
	 */
	public function __construct() {
	}

	/**
	 * @param Give_Helpers $options
	 */
	public function setOptions( Give_Helpers $options ) {
		$this->options = $options;
	}

	/**
	 * @param Give_Pro_Helpers $pro
	 */
	public function setPro( Give_Pro_Helpers $pro ) {
		$this->pro = $pro;
	}

	public function list_all_give_forms( $label = null, $option_code = 'MAKEDONATION', $args = array() ) {



		if ( ! $label ) {
			$label = __( 'Form', 'uncanny-automator' );
		}

		$token        = key_exists( 'token', $args ) ? $args['token'] : false;
		$is_ajax      = key_exists( 'is_ajax', $args ) ? $args['is_ajax'] : false;
		$target_field = key_exists( 'target_field', $args ) ? $args['target_field'] : '';
		$end_point    = key_exists( 'endpoint', $args ) ? $args['endpoint'] : '';
		$options      = array();

		$query_args = [
			'post_type'      => 'give_forms',
			'posts_per_page' => 9999,
			'post_status'    => 'publish',
		];
		$options    = Automator()->helpers->recipe->wp_query( $query_args, true, __( 'Any form', 'uncanny-automator' ) );
		$type       = 'select';

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
				'ACTUALDONATEDAMOUNT' => esc_attr__( 'Donated amount', 'uncanny-automator' ),
				$option_code          => esc_attr__( 'Form', 'uncanny-automator' ),
			],
		];

		return apply_filters( 'uap_option_list_all_give_forms', $option );
	}

	/**
	 * @param null $form_id
	 *
	 * @return mixed|void
	 */
	public function get_form_fields_and_ffm( $form_id = null ) {

		$fields = [
			'give_title'  => [
				'type'     => 'text',
				'required' => true,
				'label'    => __( 'Name title prefix', 'uncanny-automator' ),
				'key'      => 'title',
			],
			'give_first'  => [
				'type'     => 'text',
				'required' => true,
				'label'    => __( 'First name', 'uncanny-automator' ),
				'key'      => 'first_name',
			],
			'give_last'   => [
				'type'     => 'text',
				'required' => false,
				'label'    => __( 'Last name', 'uncanny-automator' ),
				'key'      => 'last_name',
			],
			'give_email'  => [
				'type'     => 'email',
				'required' => true,
				'label'    => __( 'Email', 'uncanny-automator' ),
				'key'      => 'user_email',
			],
			'give-amount' => [
				'type'     => 'tel',
				'required' => true,
				'label'    => __( 'Donation amount', 'uncanny-automator' ),
				'key'      => 'price',
			],
			'address1'    => [
				'type'     => 'text',
				'required' => true,
				'label'    => __( 'Address line 1', 'uncanny-automator' ),
				'key'      => 'address1',
			],
			'address2'    => [
				'type'     => 'text',
				'required' => true,
				'label'    => __( 'Address line 2', 'uncanny-automator' ),
				'key'      => 'address2',
			],
			'city'        => [
				'type'     => 'text',
				'required' => true,
				'label'    => __( 'City', 'uncanny-automator' ),
				'key'      => 'city',
			],
			'state'       => [
				'type'     => 'text',
				'required' => true,
				'label'    => __( 'State', 'uncanny-automator' ),
				'key'      => 'state',
			],
			'zip'         => [
				'type'     => 'text',
				'required' => true,
				'label'    => __( 'Zip', 'uncanny-automator' ),
				'key'      => 'zip',
			],
			'country'     => [
				'type'     => 'text',
				'required' => true,
				'label'    => __( 'Country', 'uncanny-automator' ),
				'key'      => 'country',
			],
		];

		if ( class_exists( '\Give_FFM_Render_Form' ) && $form_id != null && $form_id != '-1' ) {
			$customFormFields = \Give_FFM_Render_Form::get_input_fields( $form_id );
			if ( ! empty( $customFormFields[2] ) && is_array( $customFormFields[2] ) ) {
				foreach ( $customFormFields[2] as $custom_form_field ) {
					$custom_form_field['required']        = ( 'no' === $custom_form_field['required'] ) ? false : true;
					$fields[ $custom_form_field['name'] ] = [
						'type'     => $custom_form_field['input_type'],
						'required' => $custom_form_field['required'],
						'label'    => $custom_form_field['label'],
						'key'      => $custom_form_field['name'],
						'custom'   => true,
					];
				}
			}
		}


		return apply_filters( 'automator_give_wp_form_field', $fields );
	}

}
