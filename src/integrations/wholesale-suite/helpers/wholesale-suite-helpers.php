<?php

namespace Uncanny_Automator;

/**
 * Class Wholesale_Suite_Helpers
 *
 * @package Uncanny_Automator
 */
class Wholesale_Suite_Helpers {

	/**
	 * @param null $label
	 * @param string $option_code
	 * @param bool $is_any
	 * @param bool $is_all
	 *
	 * @return array|mixed|void
	 */
	public function get_all_wss_roles( $label = null, $option_code = 'CUSTOMER_ROLES', $is_any = false, $is_all = false, $custom_value = false ) {
		if ( ! $label ) {
			/* translators: WordPress role */
			$label = esc_attr__( 'Role', 'uncanny-automator' );
		}

		$roles = array();

		if ( true === $is_any ) {
			$roles['-1'] = esc_attr__( 'Any role', 'uncanny-automator' );
		}

		if ( true === $is_all ) {
			$roles['-1'] = esc_attr__( 'All roles', 'uncanny-automator' );
		}

		$wwp_wholesale_role = \WWP_Wholesale_Roles::getInstance();
		$wss_roles          = $wwp_wholesale_role->getAllRegisteredWholesaleRoles();

		foreach ( $wss_roles as $role_name => $role_info ) {
			$roles[ $role_name ] = $role_info['roleName'];
		}

		$option = array(
			'option_code'           => $option_code,
			'label'                 => $label,
			'input_type'            => 'select',
			'required'              => true,
			'options'               => $roles,
			'supports_custom_value' => $custom_value,
		);

		return apply_filters( 'uap_option_get_all_wss_roles', $option );
	}

	/**
	 * get all lead form fields (built-in and custom)
	 * for tokens and options
	 *
	 * @return array[]
	 */
	public function get_all_lead_form_fields() {
		$countryList = array();
		if ( is_plugin_active( 'woocommerce/woocommerce.php' ) ) {
			$countries = new \WC_Countries();
			$countries = $countries->get_countries();
			$index     = 0;
			foreach ( $countries as $key => $value ) {
				$countryList[ $index ]['value'] = $key;
				$countryList[ $index ]['text']  = $value;
				$index ++;
			}
		}
		$fields = array(
			'first_name'        => array(
				'label'       => apply_filters( 'wwlc_filter_first_name_field_form_label', __( 'First name', 'uncanny-automator' ) ),
				'type'        => 'text',
				'required'    => true,
				'active'      => true,
				'placeholder' => ( get_option( 'wwlc_fields_first_name_field_placeholder' ) ) ? get_option( 'wwlc_fields_first_name_field_placeholder' ) : '',
			),
			'last_name'         => array(
				'label'       => apply_filters( 'wwlc_filter_last_name_field_form_label', __( 'Last name', 'uncanny-automator' ) ),
				'type'        => 'text',
				'required'    => true,
				'active'      => true,
				'placeholder' => ( get_option( 'wwlc_fields_last_name_field_placeholder' ) ) ? get_option( 'wwlc_fields_last_name_field_placeholder' ) : '',
			),
			'wwlc_phone'        => array(
				'label'       => apply_filters( 'wwlc_filter_phone_field_form_label', __( 'Phone', 'uncanny-automator' ) ),
				'type'        => 'phone',
				'required'    => ( get_option( 'wwlc_fields_require_phone_field' ) == 'yes' ) ? true : false,
				'active'      => true,
				'placeholder' => ( get_option( 'wwlc_fields_phone_field_placeholder' ) ) ? get_option( 'wwlc_fields_phone_field_placeholder' ) : '',
			),
			'user_email'        => array(
				'label'       => apply_filters( 'wwlc_filter_email_field_form_label', __( 'Email', 'uncanny-automator' ) ),
				'type'        => 'email',
				'required'    => true,
				'active'      => true,
				'placeholder' => ( get_option( 'wwlc_fields_email_field_placeholder' ) ) ? get_option( 'wwlc_fields_email_field_placeholder' ) : '',
			),
			'wwlc_username'     => array(
				'label'       => apply_filters( 'wwlc_filter_username_form_label', __( 'Username', 'uncanny-automator' ) ),
				'type'        => 'text',
				'required'    => true,
				'active'      => ( get_option( 'wwlc_fields_username_active' ) == 'yes' ) ? true : false,
				'placeholder' => ( get_option( 'wwlc_fields_username_placeholder' ) ) ? get_option( 'wwlc_fields_username_placeholder' ) : '',
			),
			'wwlc_company_name' => array(
				'label'       => apply_filters( 'wwlc_filter_company_field_form_label', __( 'Company name', 'uncanny-automator' ) ),
				'type'        => 'text',
				'required'    => ( get_option( 'wwlc_fields_require_company_name_field' ) == 'yes' ) ? true : false,
				'active'      => ( get_option( 'wwlc_fields_activate_company_name_field' ) == 'yes' ) ? true : false,
				'placeholder' => ( get_option( 'wwlc_fields_company_field_placeholder' ) ) ? get_option( 'wwlc_fields_company_field_placeholder' ) : '',
			),
			'wwlc_country'      => array(
				'label'    => apply_filters( 'wwlc_filter_country_field_form_label', __( 'Country', 'uncanny-automator' ) ),
				'type'     => 'select',
				'required' => ( get_option( 'wwlc_fields_require_address_field' ) == 'yes' ) ? true : false,
				'active'   => ( get_option( 'wwlc_fields_activate_address_field' ) == 'yes' ) ? true : false,
				'options'  => $countryList,
			),
			'wwlc_address'      => array(
				'label'       => apply_filters( 'wwlc_filter_address1_field_form_label', __( 'Address line 1', 'uncanny-automator' ) ),
				'type'        => 'text',
				'required'    => ( get_option( 'wwlc_fields_require_address_field' ) == 'yes' ) ? true : false,
				'active'      => ( get_option( 'wwlc_fields_activate_address_field' ) == 'yes' ) ? true : false,
				'placeholder' => apply_filters( 'wwlc_filter_address1_field_form_placeholder', get_option( 'wwlc_fields_address_placeholder', '' ) ),
			),
			'wwlc_address_2'    => array(
				'label'       => apply_filters( 'wwlc_filter_address2_field_form_label', __( 'Address line 2', 'uncanny-automator' ) ),
				'type'        => 'text',
				'required'    => false,
				'active'      => ( get_option( 'wwlc_fields_activate_address_field' ) == 'yes' ) ? true : false,
				'placeholder' => apply_filters( 'wwlc_filter_address2_field_form_placeholder', get_option( 'wwlc_fields_address2_placeholder', '' ) ),
			),
			'wwlc_city'         => array(
				'label'       => apply_filters( 'wwlc_filter_city_field_form_label', __( 'City', 'uncanny-automator' ) ),
				'type'        => 'text',
				'required'    => ( get_option( 'wwlc_fields_require_address_field' ) == 'yes' ) ? true : false,
				'active'      => ( get_option( 'wwlc_fields_activate_address_field' ) == 'yes' ) ? true : false,
				'placeholder' => apply_filters( 'wwlc_filter_city_field_form_placeholder', get_option( 'wwlc_fields_city_placeholder', '' ) ),
			),
			'wwlc_state'        => array(
				'label'       => apply_filters( 'wwlc_filter_state_field_form_label', __( 'State', 'uncanny-automator' ) ),
				'type'        => 'text',
				'required'    => ( get_option( 'wwlc_fields_require_address_field' ) == 'yes' ) ? true : false,
				'active'      => ( get_option( 'wwlc_fields_activate_address_field' ) == 'yes' ) ? true : false,
				'placeholder' => apply_filters( 'wwlc_filter_state_field_form_placeholder', get_option( 'wwlc_fields_state_placeholder', '' ) ),
			),
			'wwlc_postcode'     => array(
				'label'       => apply_filters( 'wwlc_filter_postcode_field_form_label', __( 'Postcode', 'uncanny-automator' ) ),
				'type'        => 'text',
				'required'    => ( get_option( 'wwlc_fields_require_address_field' ) == 'yes' ) ? true : false,
				'active'      => ( get_option( 'wwlc_fields_activate_address_field' ) == 'yes' ) ? true : false,
				'placeholder' => apply_filters( 'wwlc_filter_postcode_field_form_placeholder', get_option( 'wwlc_fields_postcode_placeholder', '' ) ),
			),
			'wwlc_role'         => array(
				'label'       => __( 'Role', 'uncanny-automator' ),
				'type'        => 'text',
				'required'    => true,
				'active'      => true,
				'placeholder' => __( 'Set user role', 'uncanny-automator' ),
			),
		);

		$custom_fields = get_option( 'wwlc_option_registration_form_custom_fields', array() );
		if ( ! empty( $custom_fields ) ) {
			foreach ( $custom_fields as $field_id => $custom_field ) {
				$fields[ $field_id ] = array(
					'label'       => $custom_field['field_name'],
					'type'        => $custom_field['field_type'],
					'required'    => $custom_field['required'],
					'active'      => $custom_field['enabled'],
					'placeholder' => $custom_field['field_placeholder'],
				);
			}
		}

		return $fields;
	}
}
