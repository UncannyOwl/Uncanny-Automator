<?php

namespace Uncanny_Automator;

/**
 * Class Jetcrm_Tokens
 *
 * @package Uncanny_Automator
 */
class Jetcrm_Tokens {


	/**
	 * __construct
	 *
	 * @return void
	 */
	public function __construct() {
		add_action( 'automator_before_trigger_completed', array( $this, 'save_token_data' ), 20, 2 );
		add_filter( 'automator_maybe_trigger_jetcrm_tokens', array( $this, 'jetcrm_possible_tokens' ), 20, 2 );
		add_filter(
			'automator_maybe_trigger_jetcrm_tokens',
			array(
				$this,
				'jetcrm_companies_possible_tokens',
			),
			20,
			2
		);
		add_filter( 'automator_maybe_parse_token', array( $this, 'parse_jetcrm_tokens' ), 20, 6 );
	}

	/**
	 * save_token_data
	 *
	 * @param mixed $args
	 * @param mixed $trigger
	 *
	 * @return void
	 */
	public function save_token_data( $args, $trigger ) {
		if ( ! isset( $args['trigger_args'] ) || ! isset( $args['entry_args']['code'] ) ) {
			return;
		}

		$trigger_meta_validations = apply_filters(
			'automator_jet_crm_validate_common_triggers_tokens_save',
			array( 'JETCRM_CONTACT_CREATED', 'JETCRM_CONTACT_STATUS_UPDATED', 'JETCRM_COMPANY_CREATED' ),
			$args
		);

		if ( in_array( $args['entry_args']['code'], $trigger_meta_validations ) ) {
			$object_id         = array_shift( $args['trigger_args'] );
			$trigger_log_entry = $args['trigger_entry'];
			if ( ! empty( $object_id ) ) {
				Automator()->db->token->save( 'object_id', $object_id, $trigger_log_entry );
			}
		}
	}

	/**
	 * @param array $tokens
	 * @param array $args
	 *
	 * @return array|array[]|mixed
	 */
	public function jetcrm_possible_tokens( $tokens = array(), $args = array() ) {
		$trigger_code = $args['triggers_meta']['code'];

		$trigger_meta_validations = apply_filters(
			'automator_jet_crm_validate_common_possible_triggers_tokens',
			array( 'JETCRM_CONTACT_CREATED', 'JETCRM_CONTACT_STATUS_UPDATED' ),
			$args
		);

		if ( in_array( $trigger_code, $trigger_meta_validations, true ) ) {

			$fields = array(
				array(
					'tokenId'         => 'object_id',
					'tokenName'       => __( 'Customer ID', 'uncanny-automator' ),
					'tokenType'       => 'int',
					'tokenIdentifier' => $trigger_code,
				),
				array(
					'tokenId'         => 'zbsc_status',
					'tokenName'       => __( 'Contact status', 'uncanny-automator' ),
					'tokenType'       => 'text',
					'tokenIdentifier' => $trigger_code,
				),
				array(
					'tokenId'         => 'zbsc_prefix',
					'tokenName'       => __( 'Contact prefix', 'uncanny-automator' ),
					'tokenType'       => 'text',
					'tokenIdentifier' => $trigger_code,
				),
				array(
					'tokenId'         => 'zbsc_fname',
					'tokenName'       => __( 'Contact first name', 'uncanny-automator' ),
					'tokenType'       => 'text',
					'tokenIdentifier' => $trigger_code,
				),
				array(
					'tokenId'         => 'zbsc_lname',
					'tokenName'       => __( 'Contact last name', 'uncanny-automator' ),
					'tokenType'       => 'text',
					'tokenIdentifier' => $trigger_code,
				),
				array(
					'tokenId'         => 'zbsc_email',
					'tokenName'       => __( 'Contact email', 'uncanny-automator' ),
					'tokenType'       => 'email',
					'tokenIdentifier' => $trigger_code,
				),
				array(
					'tokenId'         => 'zbsc_addr1',
					'tokenName'       => __( 'Main address - Address line 1', 'uncanny-automator' ),
					'tokenType'       => 'text',
					'tokenIdentifier' => $trigger_code,
				),
				array(
					'tokenId'         => 'zbsc_addr2',
					'tokenName'       => __( 'Main address - Address line 2', 'uncanny-automator' ),
					'tokenType'       => 'text',
					'tokenIdentifier' => $trigger_code,
				),
				array(
					'tokenId'         => 'zbsc_city',
					'tokenName'       => __( 'Main address - City', 'uncanny-automator' ),
					'tokenType'       => 'text',
					'tokenIdentifier' => $trigger_code,
				),
				array(
					'tokenId'         => 'zbsc_county',
					'tokenName'       => __( 'Main address - County', 'uncanny-automator' ),
					'tokenType'       => 'text',
					'tokenIdentifier' => $trigger_code,
				),
				array(
					'tokenId'         => 'zbsc_postcode',
					'tokenName'       => __( 'Main address - Postal code', 'uncanny-automator' ),
					'tokenType'       => 'text',
					'tokenIdentifier' => $trigger_code,
				),
				array(
					'tokenId'         => 'zbsc_country',
					'tokenName'       => __( 'Main address - Country', 'uncanny-automator' ),
					'tokenType'       => 'text',
					'tokenIdentifier' => $trigger_code,
				),
				array(
					'tokenId'         => 'zbsc_secaddr1',
					'tokenName'       => __( 'Second address - Address line 1', 'uncanny-automator' ),
					'tokenType'       => 'text',
					'tokenIdentifier' => $trigger_code,
				),
				array(
					'tokenId'         => 'zbsc_secaddr2',
					'tokenName'       => __( 'Second address - Address line 2', 'uncanny-automator' ),
					'tokenType'       => 'text',
					'tokenIdentifier' => $trigger_code,
				),
				array(
					'tokenId'         => 'zbsc_seccity',
					'tokenName'       => __( 'Second address - City', 'uncanny-automator' ),
					'tokenType'       => 'text',
					'tokenIdentifier' => $trigger_code,
				),
				array(
					'tokenId'         => 'zbsc_seccounty',
					'tokenName'       => __( 'Second address - County', 'uncanny-automator' ),
					'tokenType'       => 'text',
					'tokenIdentifier' => $trigger_code,
				),
				array(
					'tokenId'         => 'zbsc_secpostcode',
					'tokenName'       => __( 'Second address - Postal code', 'uncanny-automator' ),
					'tokenType'       => 'text',
					'tokenIdentifier' => $trigger_code,
				),
				array(
					'tokenId'         => 'zbsc_seccountry',
					'tokenName'       => __( 'Second address - Country', 'uncanny-automator' ),
					'tokenType'       => 'text',
					'tokenIdentifier' => $trigger_code,
				),
				array(
					'tokenId'         => 'zbsc_hometel',
					'tokenName'       => __( 'Contact home telephone', 'uncanny-automator' ),
					'tokenType'       => 'text',
					'tokenIdentifier' => $trigger_code,
				),
				array(
					'tokenId'         => 'zbsc_worktel',
					'tokenName'       => __( 'Contact work telephone', 'uncanny-automator' ),
					'tokenType'       => 'text',
					'tokenIdentifier' => $trigger_code,
				),
				array(
					'tokenId'         => 'zbsc_mobtel',
					'tokenName'       => __( 'Contact mobile telephone', 'uncanny-automator' ),
					'tokenType'       => 'text',
					'tokenIdentifier' => $trigger_code,
				),
			);

			$tokens = array_merge( $tokens, $fields );
		}

		return $tokens;
	}

	/**
	 * @param $tokens
	 * @param $args
	 *
	 * @return array|array[]|mixed
	 */
	public function jetcrm_companies_possible_tokens( $tokens = array(), $args = array() ) {
		$trigger_code = $args['triggers_meta']['code'];

		$trigger_meta_validations = apply_filters(
			'automator_jet_crm_validate_common_companies_possible_triggers_tokens',
			array( 'JETCRM_COMPANY_CREATED' ),
			$args
		);

		if ( in_array( $trigger_code, $trigger_meta_validations, true ) ) {

			$fields = array(
				array(
					'tokenId'         => 'object_id',
					'tokenName'       => __( 'Company ID', 'uncanny-automator' ),
					'tokenType'       => 'int',
					'tokenIdentifier' => $trigger_code,
				),
				array(
					'tokenId'         => 'zbsco_status',
					'tokenName'       => __( 'Company status', 'uncanny-automator' ),
					'tokenType'       => 'text',
					'tokenIdentifier' => $trigger_code,
				),
				array(
					'tokenId'         => 'zbsco_name',
					'tokenName'       => __( 'Company name', 'uncanny-automator' ),
					'tokenType'       => 'text',
					'tokenIdentifier' => $trigger_code,
				),
				array(
					'tokenId'         => 'zbsco_email',
					'tokenName'       => __( 'Company email', 'uncanny-automator' ),
					'tokenType'       => 'email',
					'tokenIdentifier' => $trigger_code,
				),
				array(
					'tokenId'         => 'zbsco_addr1',
					'tokenName'       => __( 'Main address - Address line 1', 'uncanny-automator' ),
					'tokenType'       => 'text',
					'tokenIdentifier' => $trigger_code,
				),
				array(
					'tokenId'         => 'zbsco_addr2',
					'tokenName'       => __( 'Main address - Address line 2', 'uncanny-automator' ),
					'tokenType'       => 'text',
					'tokenIdentifier' => $trigger_code,
				),
				array(
					'tokenId'         => 'zbsco_city',
					'tokenName'       => __( 'Main address - City', 'uncanny-automator' ),
					'tokenType'       => 'text',
					'tokenIdentifier' => $trigger_code,
				),
				array(
					'tokenId'         => 'zbsco_county',
					'tokenName'       => __( 'Main address - County', 'uncanny-automator' ),
					'tokenType'       => 'text',
					'tokenIdentifier' => $trigger_code,
				),
				array(
					'tokenId'         => 'zbsco_postcode',
					'tokenName'       => __( 'Main address - Postal code', 'uncanny-automator' ),
					'tokenType'       => 'text',
					'tokenIdentifier' => $trigger_code,
				),
				array(
					'tokenId'         => 'zbsco_country',
					'tokenName'       => __( 'Main address - Country', 'uncanny-automator' ),
					'tokenType'       => 'text',
					'tokenIdentifier' => $trigger_code,
				),
				array(
					'tokenId'         => 'zbsco_secaddr1',
					'tokenName'       => __( 'Second address - Address line 1', 'uncanny-automator' ),
					'tokenType'       => 'text',
					'tokenIdentifier' => $trigger_code,
				),
				array(
					'tokenId'         => 'zbsco_secaddr2',
					'tokenName'       => __( 'Second address - Address line 2', 'uncanny-automator' ),
					'tokenType'       => 'text',
					'tokenIdentifier' => $trigger_code,
				),
				array(
					'tokenId'         => 'zbsco_seccity',
					'tokenName'       => __( 'Second address - City', 'uncanny-automator' ),
					'tokenType'       => 'text',
					'tokenIdentifier' => $trigger_code,
				),
				array(
					'tokenId'         => 'zbsco_seccounty',
					'tokenName'       => __( 'Second address - County', 'uncanny-automator' ),
					'tokenType'       => 'text',
					'tokenIdentifier' => $trigger_code,
				),
				array(
					'tokenId'         => 'zbsco_secpostcode',
					'tokenName'       => __( 'Second address - Postal code', 'uncanny-automator' ),
					'tokenType'       => 'text',
					'tokenIdentifier' => $trigger_code,
				),
				array(
					'tokenId'         => 'zbsco_seccountry',
					'tokenName'       => __( 'Second address - Country', 'uncanny-automator' ),
					'tokenType'       => 'text',
					'tokenIdentifier' => $trigger_code,
				),
				array(
					'tokenId'         => 'zbsco_maintel',
					'tokenName'       => __( 'Main telephone', 'uncanny-automator' ),
					'tokenType'       => 'text',
					'tokenIdentifier' => $trigger_code,
				),
				array(
					'tokenId'         => 'zbsco_sectel',
					'tokenName'       => __( 'Secondary telephone', 'uncanny-automator' ),
					'tokenType'       => 'text',
					'tokenIdentifier' => $trigger_code,
				),
			);

			$tokens = array_merge( $tokens, $fields );
		}

		return $tokens;
	}

	/**
	 * @param $value
	 * @param $pieces
	 * @param $recipe_id
	 * @param $trigger_data
	 * @param $user_id
	 * @param $replace_args
	 *
	 * @return mixed
	 */
	public function parse_jetcrm_tokens( $value, $pieces, $recipe_id, $trigger_data, $user_id, $replace_args ) {

		if ( ! is_array( $pieces ) || ! isset( $pieces[1] ) || ! isset( $pieces[2] ) ) {
			return $value;
		}

		$trigger_meta_validations = apply_filters(
			'automator_jet_crm_validate_common_triggers_tokens_parse',
			array( 'JETCRM_CONTACT_CREATED', 'JETCRM_CONTACT_STATUS_UPDATED' ),
			array(
				'pieces'       => $pieces,
				'recipe_id'    => $recipe_id,
				'trigger_data' => $trigger_data,
				'user_id'      => $user_id,
				'replace_args' => $replace_args,
			)
		);

		$companies_trigger_metas = apply_filters(
			'automator_jet_crm_validate_common_companies_triggers_tokens_parse',
			array( 'JETCRM_COMPANY_CREATED' ),
			array(
				'pieces'       => $pieces,
				'recipe_id'    => $recipe_id,
				'trigger_data' => $trigger_data,
				'user_id'      => $user_id,
				'replace_args' => $replace_args,
			)
		);

		if ( ! array_intersect( $trigger_meta_validations, $pieces ) && ! array_intersect( $companies_trigger_metas, $pieces ) ) {
			return $value;
		}

		global $wpdb;
		$to_replace = $pieces[2];
		$contact_id = Automator()->db->token->get( 'object_id', $replace_args );
		$contact    = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$wpdb->prefix}zbs_contacts` WHERE ID = %d", $contact_id ) );
		if ( array_intersect( $companies_trigger_metas, $pieces ) ) {
			$contact = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$wpdb->prefix}zbs_companies` WHERE ID = %d", $contact_id ) );
		}

		switch ( $to_replace ) {
			case 'zbsc_prefix':
				$value = $contact->zbsc_prefix;
				break;
			case 'zbsc_fname':
				$value = $contact->zbsc_fname;
				break;
			case 'zbsc_lname':
				$value = $contact->zbsc_lname;
				break;
			case 'zbsc_hometel':
				$value = $contact->zbsc_hometel;
				break;
			case 'zbsc_worktel':
				$value = $contact->zbsc_worktel;
				break;
			case 'zbsc_mobtel':
				$value = $contact->zbsc_mobtel;
				break;
			case 'zbsc_email':
				$value = $contact->zbsc_email;
				break;
			case 'zbsc_status':
				$value = $contact->zbsc_status;
				break;
			case 'zbsc_addr1':
				$value = $contact->zbsc_addr1;
				break;
			case 'zbsc_addr2':
				$value = $contact->zbsc_addr2;
				break;
			case 'zbsc_city':
				$value = $contact->zbsc_city;
				break;
			case 'zbsc_postcode':
				$value = $contact->zbsc_postcode;
				break;
			case 'zbsc_county':
				$value = $contact->zbsc_county;
				break;
			case 'zbsc_country':
				$value = $contact->zbsc_country;
				break;
			case 'zbsc_secaddr1':
				$value = $contact->zbsc_secaddr1;
				break;
			case 'zbsc_secaddr2':
				$value = $contact->zbsc_secaddr2;
				break;
			case 'zbsc_seccity':
				$value = $contact->zbsc_seccity;
				break;
			case 'zbsc_secpostcode':
				$value = $contact->zbsc_secpostcode;
				break;
			case 'zbsc_seccounty':
				$value = $contact->zbsc_seccounty;
				break;
			case 'zbsc_seccountry':
				$value = $contact->zbsc_seccountry;
				break;
			case 'zbsco_name':
				$value = $contact->zbsco_name;
				break;
			case 'zbsco_maintel':
				$value = $contact->zbsco_maintel;
				break;
			case 'zbsco_sectel':
				$value = $contact->zbsco_sectel;
				break;
			case 'zbsco_email':
				$value = $contact->zbsco_email;
				break;
			case 'zbsco_status':
				$value = $contact->zbsco_status;
				break;
			case 'zbsco_addr1':
				$value = $contact->zbsco_addr1;
				break;
			case 'zbsco_addr2':
				$value = $contact->zbsco_addr2;
				break;
			case 'zbsco_city':
				$value = $contact->zbsco_city;
				break;
			case 'zbsco_postcode':
				$value = $contact->zbsco_postcode;
				break;
			case 'zbsco_county':
				$value = $contact->zbsco_county;
				break;
			case 'zbsco_country':
				$value = $contact->zbsco_country;
				break;
			case 'zbsco_secaddr1':
				$value = $contact->zbsco_secaddr1;
				break;
			case 'zbsco_secaddr2':
				$value = $contact->zbsco_secaddr2;
				break;
			case 'zbsco_seccity':
				$value = $contact->zbsco_seccity;
				break;
			case 'zbsco_secpostcode':
				$value = $contact->zbsco_secpostcode;
				break;
			case 'zbsco_seccounty':
				$value = $contact->zbsco_seccounty;
				break;
			case 'zbsco_seccountry':
				$value = $contact->zbsco_seccountry;
				break;
			case 'object_id';
				$value = $contact_id;
				break;
		}

		return $value;
	}

}
