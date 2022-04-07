<?php

namespace Uncanny_Automator;

/**
 * Divi Tokens file
 */
class Divi_Tokens {

	/**
	 * Divi_Tokens Constructor
	 */
	public function __construct() {

		add_filter( 'automator_maybe_trigger_divi_diviform_tokens', array( $this, 'divi_possible_tokens' ), 20, 2 );
		add_filter( 'automator_maybe_parse_token', array( $this, 'divi_token' ), 20, 6 );
	}

	/**
	 * Prepare tokens.
	 *
	 * @param array $tokens .
	 * @param array $args .
	 *
	 * @return array
	 */
	public function divi_possible_tokens( $tokens = array(), $args = array() ) {
		if ( ! automator_do_identify_tokens() ) {
			return $tokens;
		}
		$form_id      = $args['value'];
		$trigger_meta = $args['meta'];
		$form_fields  = array(
			array(
				'field_id'    => 'name',
				'field_title' => __( 'Name (if available)', 'uncanny-automator' ),
				'field_type'  => 'text',
			),
			array(
				'field_id'    => 'email',
				'field_title' => __( 'Email address (if available)', 'uncanny-automator' ),
				'field_type'  => 'email',
			),
			array(
				'field_id'    => 'message',
				'field_title' => __( 'Message (if available)', 'uncanny-automator' ),
				'field_type'  => 'text',
			),
		);

		if ( intval( '-1' ) !== intval( $form_id ) ) {
			$form_fields = Divi_Helpers::get_form_by_id( $form_id );
			if ( empty( $form_fields ) ) {
				return $tokens;
			}
		}
		$fields = array();
		foreach ( $form_fields as $form_field ) {
			$input_id   = $form_field['field_id'];
			$token_type = $form_field['field_type'];
			$token_id   = "$form_id|$input_id";
			$fields[]   = array(
				'tokenId'         => $token_id,
				'tokenName'       => $form_field['field_title'],
				'tokenType'       => $token_type,
				'tokenIdentifier' => $trigger_meta,
			);
		}

		return array_merge( $tokens, $fields );
	}

	/**
	 * Parse the token.
	 *
	 * @param $value
	 * @param $pieces
	 * @param $recipe_id
	 * @param $trigger_data
	 * @param $user_id
	 * @param $replace_args
	 *
	 * @return null|string
	 */
	public function divi_token( $value, $pieces, $recipe_id, $trigger_data, $user_id, $replace_args ) {

		$piece = 'DIVIFORM';
		if ( empty( $piece ) ) {
			return $value;
		}

		if ( ! in_array( $piece, $pieces, true ) ) {
			return $value;
		}
		if ( empty( $trigger_data ) ) {
			return $value;
		}
		foreach ( $trigger_data as $trigger ) {
			// Meta for form name
			if ( ( 'DIVISUBMITFORM' === $pieces[1] || 'ANONDIVISUBMITFORM' === $pieces[1] ) && 'DIVIFORM' === $pieces[2] ) {
				if ( isset( $trigger['meta'][ $pieces[2] . '_readable' ] ) ) {
					return $trigger['meta'][ $pieces[2] . '_readable' ];
				}
			}
			$trigger_id     = absint( $trigger['ID'] );
			$trigger_log_id = absint( $replace_args['trigger_log_id'] );
			$parse_tokens   = array(
				'trigger_id'     => $trigger_id,
				'trigger_log_id' => $trigger_log_id,
				'user_id'        => $user_id,
			);

			$meta_key = sprintf( '%d:%s', $pieces[0], $pieces[1] );
			$entry    = Automator()->db->trigger->get_token_meta( $meta_key, $parse_tokens );
			if ( empty( $entry ) ) {
				continue;
			}
			$value = $entry;
			if ( is_array( $value ) ) {
				$value = isset( $entry[ $pieces[2] ] ) ? $entry[ $pieces[2] ] : '';
				if ( is_array( $value ) ) {
					$value = implode( ', ', $value );
				}
			}
		}

		return $value;
	}
}
