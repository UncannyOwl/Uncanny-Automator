<?php

namespace Uncanny_Automator;


use MeprOptions;

/**
 * Class Mp_Tokens
 * @package Uncanny_Automator
 */
class Mp_Tokens {

	/**
	 * Integration code
	 * @var string
	 */
	public static $integration = 'MP';

	public function __construct() {
		add_filter( 'automator_maybe_trigger_mp_mpproduct_tokens', [ $this, 'mp_possible_tokens' ], 20, 2 );
		add_filter( 'automator_maybe_parse_token', [ $this, 'mp_token' ], 20, 6 );
	}

	/**
	 * Only load this integration and its triggers and actions if the related plugin is active
	 *
	 * @param $status
	 * @param $plugin
	 *
	 * @return bool
	 */
	public function plugin_active( $status, $plugin ) {

		if ( self::$integration === $plugin ) {
			if ( class_exists( 'GFFormsModel' ) ) {
				$status = true;
			} else {
				$status = false;
			}
		}

		return $status;
	}

	/**
	 * @param array $tokens
	 * @param array $args
	 *
	 * @return array
	 */
	public function mp_possible_tokens( $tokens = array(), $args = array() ) {
		$form_id             = $args['value'];
		$trigger_integration = $args['integration'];
		$trigger_meta        = $args['meta'];
		$mepr_options        = MeprOptions::fetch();

		$fields = array();
		if ( $mepr_options->show_fname_lname ) {
			$fields[] = [
				'tokenId'         => 'first_name',
				'tokenName'       => 'First Name',
				'tokenType'       => 'text',
				'tokenIdentifier' => $trigger_meta,
			];
			$fields[] = [
				'tokenId'         => 'last_name',
				'tokenName'       => 'Last Name',
				'tokenType'       => 'text',
				'tokenIdentifier' => $trigger_meta,
			];
		}

		if ( $mepr_options->show_address_fields && ! empty( $mepr_options->address_fields ) ) {
			foreach ( $mepr_options->address_fields as $address_field ) {
				$fields[] = [
					'tokenId'         => $address_field->field_key,
					'tokenName'       => $address_field->field_name,
					'tokenType'       => $address_field->field_type,
					'tokenIdentifier' => $trigger_meta,
				];
			}
		}

		$custom_fields = $mepr_options->custom_fields;
		if ( ! empty( $custom_fields ) ) {
			foreach ( $custom_fields as $_field ) {
				$fields[] = [
					'tokenId'         => $_field->field_key,
					'tokenName'       => $_field->field_name,
					'tokenType'       => $_field->field_type,
					'tokenIdentifier' => $trigger_meta,
				];
			}
		}

		if ( ! empty( $fields ) ) {
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
	 *
	 * @return mixed|string
	 */
	public function mp_token( $value, $pieces, $recipe_id, $trigger_data, $user_id, $replace_args ) {
		if ( $pieces ) {
			$matches = [ 'MPPRODUCT', 'MPPRODUCT_ID', 'MPPRODUCT_URL', 'first_name', 'last_name' ];
			if ( array_intersect( $matches, $pieces ) ) {
				//
				//$user_id = wp_get_current_user()->ID;
				// all memberpress values will be saved in usermeta.
				$value = get_user_meta( $user_id, 'MPPRODUCT', true );
				if ( is_array( $value ) ) {
					$value = implode( ', ', $value );
				} else {
					switch ( $pieces[2] ) {
						case 'MPPRODUCT_ID':
							$value = absint( $value );
							break;
						case 'MPPRODUCT_URL':
							$value = get_the_permalink( $value );
							break;
						case 'first_name':
							$value = get_user_by( 'ID', $user_id )->first_name;
							break;
						case 'last_name':
							$value = get_user_by( 'ID', $user_id )->last_name;
							break;
						default:
							$value = get_the_title( $value );
							break;
					}
				}
			}
		}

		return $value;
	}
}
