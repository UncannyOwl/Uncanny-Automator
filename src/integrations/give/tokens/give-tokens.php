<?php

namespace Uncanny_Automator;

/**
 * Class Give_Tokens
 *
 * @package Uncanny_Automator
 */
class Give_Tokens {

	/**
	 * Integration code
	 *
	 * @var string
	 */
	public static $integration = 'GIVEWP';

	/**
	 * Give_Tokens constructor.
	 */
	public function __construct() {

		add_filter(
			'automator_maybe_trigger_givewp_givewpmakedonation_tokens',
			array(
				$this,
				'givewp_possible_tokens',
			),
			30,
			2
		);

		add_filter( 'automator_maybe_parse_token', array( $this, 'parse_give_donation_token' ), 30, 6 );
	}

	/**
	 * @param     $value
	 * @param     $pieces
	 * @param     $recipe_id
	 * @param     $trigger_data
	 * @param int $user_id
	 * @param     $replace_args
	 *
	 * @return mixed
	 */
	public function parse_give_donation_token( $value, $pieces, $recipe_id, $trigger_data, $user_id, $replace_args ) {
		$tokens = array(
			'GIVEWPMAKEDONATION',
			'GIVEWPMAKEDONATION_ID',
			'ACTUALDONATEDAMOUNT',
			'DONATIONFORM',
		);

		if ( $pieces && isset( $pieces[2] ) ) {
			$meta_field = $pieces[2];

			if ( ! empty( $meta_field ) && in_array( $meta_field, $tokens, false ) ) {
				if ( $trigger_data ) {
					foreach ( $trigger_data as $trigger ) {
						switch ( $meta_field ) {
							case 'NUMBERCOND':
								$value = $trigger['meta']['NUMBERCOND_readable'];
								break;
							default:
								global $wpdb;
								$meta_value = $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->prefix}uap_trigger_log_meta WHERE meta_key = %s AND automator_trigger_id = %d AND automator_trigger_log_id = %d ORDER BY ID DESC LIMIT 0,1", $meta_field, $trigger['ID'], $replace_args['trigger_log_id'] ) );
								if ( ! empty( $meta_value ) ) {
									$value = maybe_unserialize( $meta_value );
								}
								break;
						}
					}
				}
			} else {
				if ( 'DONATIONFORM' === $pieces[1] ) {
					$billing_fields = array( 'address1', 'address2', 'city', 'state', 'zip', 'country' );
					global $wpdb;
					if ( $trigger_data ) {
						foreach ( $trigger_data as $trigger ) {
							$field_keys = explode( '|', $pieces[2] );
							$field_key  = isset( $field_keys[1] ) ? $field_keys[1] : '';
							if ( ! empty( $field_key ) && ! in_array( $field_key, $billing_fields, true ) ) {
								$meta_key   = 'payment_data';
								$meta_value = $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->prefix}uap_trigger_log_meta WHERE meta_key = %s AND automator_trigger_id = %d AND automator_trigger_log_id = %d  ORDER BY ID DESC LIMIT 0,1", $meta_key, $trigger['ID'], $replace_args['trigger_log_id'] ) );
								if ( ! empty( $meta_value ) ) {
									$meta_value  = maybe_unserialize( $meta_value );
									$form_fields = Automator()->helpers->recipe->give->get_form_fields_and_ffm( $meta_value['give_form_id'] );
									$form_field  = isset( $form_fields[ $field_key ] ) ? $form_fields[ $field_key ] : array();
									if ( ! empty( $form_field ) ) {
										if ( ! empty( $meta_value ) ) {
											if ( isset( $meta_value[ $form_field['key'] ] ) ) {
												$value = $meta_value[ $form_field['key'] ];
											} elseif ( isset( $meta_value['user_info'][ $form_field['key'] ] ) ) {
												$value = $meta_value['user_info'][ $form_field['key'] ];
											}
										}
									}
								}
							} elseif ( ! empty( $field_key ) && in_array( $field_key, $billing_fields, true ) ) {
								$meta_key   = 'payment_id';
								$meta_value = $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->prefix}uap_trigger_log_meta WHERE meta_key = %s AND automator_trigger_id = %d AND automator_trigger_log_id = %d  ORDER BY ID DESC LIMIT 0,1", $meta_key, $trigger['ID'], $replace_args['trigger_log_id'] ) );
								if ( ! empty( $meta_value ) && function_exists( 'give_get_meta' ) ) {
									$inner_meta_key = '_give_donor_billing_' . $field_key;
									$value          = give_get_meta( $meta_value, $inner_meta_key, true );
								}
							}
						}
					}
				}
			}
		}

		return $value;
	}

	/**
	 * @param array $tokens
	 * @param array $args
	 *
	 * @return array
	 */
	public function givewp_possible_tokens( $tokens = array(), $args = array() ) {
		if ( ! automator_do_identify_tokens() ) {
			return $tokens;
		}
		$form_id = absint( $args['value'] );

		if ( empty( $form_id ) ) {
			return $tokens;
		}

		$form_fields = Automator()->helpers->recipe->give->get_form_fields_and_ffm( $form_id );

		if ( empty( $form_fields ) ) {
			return $tokens;
		}

		$fields = array();
		foreach ( $form_fields as $key => $_field ) {

			$input_id   = $key;
			$token_id   = "$form_id|$input_id";
			$token_type = 'text';
			if ( strpos( $_field['type'], 'email' ) || 'email*' === $_field['type'] || 'email' === $_field['type'] ) {
				$token_type = 'email';
			}

			$existing_tokens = array_column( $tokens, 'tokenId' );
			if ( ! in_array( $token_id, $existing_tokens, false ) ) {
				$fields[] = array(
					'tokenId'         => $token_id,
					'tokenName'       => $_field['label'],
					'tokenType'       => $token_type,
					'tokenIdentifier' => 'DONATIONFORM',
				);
			}
		}

		$tokens = array_merge( $tokens, $fields );

		return $tokens;
	}

}
