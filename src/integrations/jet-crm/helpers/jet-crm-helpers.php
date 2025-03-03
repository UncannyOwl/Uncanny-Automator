<?php

namespace Uncanny_Automator;

/**
 * Class Jet_Crm_Helpers
 *
 * @package Uncanny_Automator
 */
class Jet_Crm_Helpers {

	/**
	 * @param $option_code
	 * @param $is_any
	 * @param $tokens
	 *
	 * @return array|mixed|void
	 */
	public function contact_statuses( $option_code, $is_any = false, $tokens = array() ) {

		global $zbsCustomerFields;
		$customer_fields = is_array( $zbsCustomerFields ) && isset( $zbsCustomerFields['status'] ) ? $zbsCustomerFields['status'] : array();
		$statuses        = is_array( $customer_fields ) && isset( $customer_fields[3] ) ? $customer_fields[3] : array();
		$options         = ! empty( $statuses ) ? array_combine( $statuses, $statuses ) : array();

		// Load defaults if empty.
		if ( empty( $options ) ) {
			$options = array(
				'Lead'                         => esc_html__( 'Lead', 'uncanny-automator' ),
				'Customer'                     => esc_html__( 'Customer', 'uncanny-automator' ),
				'Blacklisted'                  => esc_html__( 'Blacklisted', 'uncanny-automator' ),
				'Cancelled by Customer'        => esc_html__( 'Cancelled by Customer', 'uncanny-automator' ),
				'Cancelled by Us (Post-Quote)' => esc_html__( 'Cancelled by Us (Post-Quote)', 'uncanny-automator' ),
				'Cancelled by Us (Pre-Quote)'  => esc_html__( 'Cancelled by Us (Pre-Quote)', 'uncanny-automator' ),
				'Refused'                      => esc_html__( 'Refused', 'uncanny-automator' ),
			);
		}

		// Add Any Option.
		if ( true === $is_any ) {
			$options = array( '-1' => esc_html__( 'Any status', 'uncanny-automator' ) ) + $options;
		}

		$option = array(
			'option_code'     => $option_code,
			'label'           => esc_attr__( 'Status', 'uncanny-automator' ),
			'input_type'      => 'select',
			'required'        => true,
			'options_show_id' => false,
			'relevant_tokens' => $tokens,
			'options'         => $options,
		);

		return apply_filters( 'uap_option_contact_statuses', $option );
	}

	/**
	 * @param $option_code
	 * @param $is_any
	 * @param $tokens
	 * @param $tag_type
	 *
	 * @return array|mixed|void
	 */
	public function get_all_jetpack_tags( $option_code, $is_any = false, $tokens = array(), $tag_type = ZBS_TYPE_CONTACT, $empty_val = false ) {

		global $wpdb;
		$all_tags = $wpdb->get_results( $wpdb->prepare( "SELECT `ID`,`zbstag_name` FROM `{$wpdb->prefix}zbs_tags` WHERE zbstag_objtype = %d", $tag_type ) );
		$tags     = array();

		if ( ! empty( $all_tags ) ) {
			foreach ( $all_tags as $tag ) {
				$tags[ $tag->ID ] = $tag->zbstag_name;
			}
		}

		if ( true === $is_any ) {
			$tags = array( '-1' => esc_html__( 'Any tag', 'uncanny-automator' ) ) + $tags;
		}

		if ( true === $empty_val ) {
			$tags = array( '0' => esc_html__( 'Select a tag', 'uncanny-automator' ) ) + $tags;
		}

		$option = array(
			'option_code'           => $option_code,
			'label'                 => esc_attr__( 'Tag', 'uncanny-automator' ),
			'input_type'            => 'select',
			'required'              => true,
			'options_show_id'       => false,
			'relevant_tokens'       => $tokens,
			'options'               => $tags,
			'supports_custom_value' => true,
		);

		return apply_filters( 'uap_option_get_all_jetpack_tags', $option );
	}

	/**
	 * @param $option_code
	 * @param $is_any
	 *
	 * @return array|mixed|void
	 */
	public function get_all_jetpack_companies( $option_code, $is_any = false, $supports_custom_value = false, $empty_val = false ) {

		global $wpdb;
		$all_companies = $wpdb->get_results( "SELECT `ID`,`zbsco_name` FROM `{$wpdb->prefix}zbs_companies`" );
		$companies     = array();

		if ( ! empty( $all_companies ) ) {
			foreach ( $all_companies as $company ) {
				$companies[ $company->ID ] = $company->zbsco_name;
			}
		}

		if ( true === $is_any ) {
			$companies = array( '-1' => esc_html__( 'Any company', 'uncanny-automator' ) ) + $companies;
		}

		if ( true === $empty_val ) {
			$companies = array( '0' => esc_html__( 'Select a company', 'uncanny-automator' ) ) + $companies;
		}

		$option = array(
			'option_code'           => $option_code,
			'label'                 => esc_attr__( 'Company', 'uncanny-automator' ),
			'input_type'            => 'select',
			'required'              => false,
			'options_show_id'       => false,
			'relevant_tokens'       => array(),
			'options'               => $companies,
			'supports_custom_value' => $supports_custom_value,
		);

		return apply_filters( 'uap_option_get_all_jetpack_companies', $option );
	}

	/**
	 * @param $tag
	 * @param $tag_type
	 *
	 * @return int|mixed
	 */
	public function check_if_tag_exists( $tag, $tag_type ) {
		global $wpdb;
		$check_tag = $wpdb->get_var( $wpdb->prepare( "SELECT `ID` FROM `{$wpdb->prefix}zbs_tags` WHERE zbstag_objtype = %d AND (zbstag_name LIKE %s OR ID=%d)", $tag_type, $tag, $tag ) );
		if ( empty( $check_tag ) ) {
			$slug = preg_replace( '#[^\\pL\d]+#u', '-', strtolower( $tag ) );
			$wpdb->insert(
				"{$wpdb->prefix}zbs_tags",
				array(
					'zbs_site'           => zeroBSCRM_site(),
					'zbs_team'           => zeroBSCRM_team(),
					'zbs_owner'          => - 1,
					'zbstag_objtype'     => $tag_type,
					'zbstag_name'        => $tag,
					'zbstag_slug'        => $slug,
					'zbstag_created'     => time(),
					'zbstag_lastupdated' => time(),
				),
				array( '%d', '%d', '%d', '%d', '%s', '%s', '%d', '%d' )
			);
			$tag = $wpdb->insert_id;
		}

		return $tag;
	}

	/**
	 * @param $tag
	 * @param $object_id
	 * @param $object_type
	 *
	 * @return void
	 */
	public function link_tag_with_object( $tag, $object_id, $object_type ) {
		global $wpdb;
		$wpdb->replace(
			"{$wpdb->prefix}zbs_tags_links",
			array(
				'zbs_site'      => zeroBSCRM_site(),
				'zbs_team'      => zeroBSCRM_team(),
				'zbs_owner'     => 0,
				'zbstl_objtype' => $object_type,
				'zbstl_objid'   => $object_id,
				'zbstl_tagid'   => $tag,
			),
			array( '%d', '%d', '%d', '%d', '%d', '%d' )
		);

	}

	/**
	 * @return array
	 */
	public function get_contact_fields() {
		$fields = array(
			'prefix'      => esc_html__( 'Prefix', 'uncanny-automator' ),
			'fname'       => esc_html__( 'First name', 'uncanny-automator' ),
			'lname'       => esc_html__( 'Last name', 'uncanny-automator' ),
			'addr1'       => esc_html__( 'Main address - Address line 1', 'uncanny-automator' ),
			'addr2'       => esc_html__( 'Main address - Address line 2', 'uncanny-automator' ),
			'city'        => esc_html__( 'Main address - City', 'uncanny-automator' ),
			'county'      => esc_html__( 'Main address - County', 'uncanny-automator' ),
			'country'     => esc_html__( 'Main address - Country', 'uncanny-automator' ),
			'postcode'    => esc_html__( 'Main address - Post code', 'uncanny-automator' ),
			'secaddr1'    => esc_html__( 'Second address - Address line 1', 'uncanny-automator' ),
			'secaddr2'    => esc_html__( 'Second address - Address line 2', 'uncanny-automator' ),
			'seccity'     => esc_html__( 'Second address - City', 'uncanny-automator' ),
			'seccounty'   => esc_html__( 'Second address - County', 'uncanny-automator' ),
			'seccountry'  => esc_html__( 'Second address - Country', 'uncanny-automator' ),
			'secpostcode' => esc_html__( 'Second address - Post code', 'uncanny-automator' ),
			'hometel'     => esc_html__( 'Home telephone', 'uncanny-automator' ),
			'worktel'     => esc_html__( 'Work telephone', 'uncanny-automator' ),
			'mobtel'      => esc_html__( 'Mobile telephone', 'uncanny-automator' ),
			'email'       => esc_html__( 'Email', 'uncanny-automator' ),
			'tw'          => esc_html__( 'Social profile - Twitter', 'uncanny-automator' ),
			'li'          => esc_html__( 'Social profile - LinkedIn', 'uncanny-automator' ),
			'fb'          => esc_html__( 'Social profile - Facebook', 'uncanny-automator' ),
		);

		return $fields;
	}

}
