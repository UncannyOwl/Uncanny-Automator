<?php

namespace Uncanny_Automator;

use MeprOptions;

/**
 * Class Mp_Tokens
 *
 * @package Uncanny_Automator
 */
class Mp_Tokens {

	/**
	 *
	 */
	public function __construct() {
		add_filter( 'automator_maybe_trigger_mp_mpproduct_tokens', array( $this, 'mp_possible_tokens' ), 20, 2 );
		add_filter( 'automator_maybe_parse_token', array( $this, 'mp_token' ), 222, 6 );
		add_action( 'automator_before_trigger_completed', array( $this, 'save_token_data' ), 20, 2 );
	}

	/**
	 * @param $args
	 * @param $trigger
	 *
	 * @return void
	 */
	public function save_token_data( $args, $trigger ) {
		if ( ! isset( $args['trigger_args'] ) || ! isset( $args['entry_args']['code'] ) ) {
			return;
		}

		$trigger_meta_validations = apply_filters(
			'automator_mepr_validate_trigger_meta_pieces_save',
			array( 'MP_RENEW_SUBSCRIPTION' ),
			$args
		);

		if ( in_array( $args['entry_args']['code'], $trigger_meta_validations, true ) ) {

			$event = array_shift( $args['trigger_args'] );

			/** @var \MeprTransaction $transaction */
			$mepr_transaction = $event->get_data();

			$trigger_log_entry = $args['trigger_entry'];
			if ( ! empty( $mepr_transaction ) ) {
				Automator()->db->token->save( 'mp_txn_id', $mepr_transaction->id, $trigger_log_entry );
				Automator()->db->token->save( 'mp_txn_amount', $mepr_transaction->amount, $trigger_log_entry );
			}
		}
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
				'tokenName'       => esc_html__( 'First name', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => $trigger_meta,
			);
			$fields[] = array(
				'tokenId'         => 'last_name',
				'tokenName'       => esc_html__( 'Last name', 'uncanny-automator' ),
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
			'MPPRODUCT_TXN_ID',
			'MPPRODUCT_TXN_AMOUNT',
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
				case 'MPPRODUCT_TXN_ID':
					$txn_id = Automator()->db->trigger->get_token_meta( 'mp_txn_id', $parse_tokens );
					$value  = absint( $txn_id );
					break;
				case 'MPPRODUCT_TXN_AMOUNT':
					$txn_amount = Automator()->db->trigger->get_token_meta( 'mp_txn_amount', $parse_tokens );
					$value      = $txn_amount;
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
