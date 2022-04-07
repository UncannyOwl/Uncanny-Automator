<?php

namespace Uncanny_Automator;

use MeprOptions;

/**
 * Class Mp_Tokens
 *
 * @package Uncanny_Automator
 */
class Mp_Tokens {

	public function __construct() {
		add_filter( 'automator_maybe_trigger_mp_mpproduct_tokens', array( $this, 'mp_possible_tokens' ), 20, 2 );
		add_filter( 'automator_maybe_parse_token', array( $this, 'mp_token' ), 20, 6 );
	}

	/**
	 * @param array $tokens
	 * @param array $args
	 *
	 * @return array
	 */
	public function mp_possible_tokens( $tokens = array(), $args = array() ) {
		if ( ! automator_do_identify_tokens() ) {
			return $tokens;
		}
		$trigger_meta = $args['meta'];
		$mepr_options = MeprOptions::fetch();
		$fields       = array();
		if ( $mepr_options->show_fname_lname ) {
			$fields[] = array(
				'tokenId'         => 'first_name',
				'tokenName'       => __( 'First name', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => $trigger_meta,
			);
			$fields[] = array(
				'tokenId'         => 'last_name',
				'tokenName'       => __( 'Last name', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => $trigger_meta,
			);
		}

		if ( $mepr_options->show_address_fields && ! empty( $mepr_options->address_fields ) ) {
			foreach ( $mepr_options->address_fields as $address_field ) {
				$fields[] = array(
					'tokenId'         => $address_field->field_key,
					'tokenName'       => $address_field->field_name,
					'tokenType'       => $address_field->field_type,
					'tokenIdentifier' => $trigger_meta,
				);
			}
		}

		$custom_fields = $mepr_options->custom_fields;
		if ( ! empty( $custom_fields ) ) {
			foreach ( $custom_fields as $_field ) {
				$fields[] = array(
					'tokenId'         => $_field->field_key,
					'tokenName'       => $_field->field_name,
					'tokenType'       => $_field->field_type,
					'tokenIdentifier' => $trigger_meta,
				);
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
	 * @param $replace_args
	 *
	 * @return mixed|string
	 */
	public function mp_token( $value, $pieces, $recipe_id, $trigger_data, $user_id, $replace_args ) {
		if ( ! $pieces ) {
			return $value;
		}
		$matches = array(
			'MPPRODUCT',
			'MPPRODUCT_ID',
			'MPPRODUCT_URL',
		);

		$mepr_options = MeprOptions::fetch();
		if ( $mepr_options->show_fname_lname ) {
			$matches[] = 'first_name';
			$matches[] = 'last_name';
		}

		if ( $mepr_options->show_address_fields && ! empty( $mepr_options->address_fields ) ) {
			foreach ( $mepr_options->address_fields as $address_field ) {
				$matches[] = $address_field->field_key;
			}
		}

		$custom_fields = $mepr_options->custom_fields;
		if ( ! empty( $custom_fields ) ) {
			foreach ( $custom_fields as $_field ) {
				$matches[] = $_field->field_key;
			}
		}

		if ( ! array_intersect( $matches, $pieces ) ) {
			return $value;
		}

		if ( empty( $trigger_data ) ) {
			return $value;
		}

		if ( ! isset( $pieces[2] ) ) {
			return $value;
		}
		foreach ( $trigger_data as $trigger ) {
			// all memberpress values will be saved in usermeta.
			$trigger_id     = absint( $trigger['ID'] );
			$trigger_log_id = absint( $replace_args['trigger_log_id'] );
			$parse_tokens   = array(
				'trigger_id'     => $trigger_id,
				'trigger_log_id' => $trigger_log_id,
				'user_id'        => $user_id,
			);

			$meta_key   = 'MPPRODUCT';
			$product_id = Automator()->db->trigger->get_token_meta( $meta_key, $parse_tokens );
			if ( empty( $product_id ) ) {
				continue;
			}
			switch ( $pieces[2] ) {
				case 'MPPRODUCT':
					$value = get_the_title( $product_id );
					break;
				case 'MPPRODUCT_ID':
					$value = absint( $product_id );
					break;
				case 'MPPRODUCT_URL':
					$value = get_the_permalink( $product_id );
					break;
				default:
					$value = get_user_meta( $user_id, $pieces[2], true );
					break;
			}
		}

		return $value;
	}
}
