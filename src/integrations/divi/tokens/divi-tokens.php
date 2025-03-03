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
		add_filter( 'automator_maybe_parse_token', array( $this, 'divi_token' ), 100, 6 );
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
		$trigger_code = $args['triggers_meta']['code'];
		$form_fields  = array(
			array(
				'field_id'    => 'name',
				'field_title' => esc_html__( 'Name (if available)', 'uncanny-automator' ),
				'field_type'  => 'text',
			),
			array(
				'field_id'    => 'email',
				'field_title' => esc_html__( 'Email address (if available)', 'uncanny-automator' ),
				'field_type'  => 'email',
			),
			array(
				'field_id'    => 'message',
				'field_title' => esc_html__( 'Message (if available)', 'uncanny-automator' ),
				'field_type'  => 'text',
			),
		);

		if ( intval( '-1' ) !== intval( $form_id ) ) {
			$form_fields = Divi_Helpers::get_form_by_id( $form_id );
			if ( 'ANON_DIVI_SUBMIT_FORM' === $trigger_code || 'DIVI_SUBMIT_FORM' === $trigger_code ) {
				$form_fields = Divi_Helpers::get_form_by_id( $form_id, true );
			}
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

		if ( empty( $pieces ) ) {
			return $value;
		}

		$piece = 'DIVIFORM';

		if ( ! in_array( $piece, $pieces, true ) ) {
			return $value;
		}

		if ( empty( $trigger_data ) ) {
			return $value;
		}

		foreach ( $trigger_data as $trigger ) {
			// Meta for form name
			if ( ( 'DIVISUBMITFORM' === $pieces[1] || 'ANONDIVISUBMITFORM' === $pieces[1] ) || ( 'DIVI_SUBMIT_FORM' === $pieces[1] || 'ANON_DIVI_SUBMIT_FORM' === $pieces[1] ) && 'DIVIFORM' === $pieces[2] ) {
				if ( isset( $trigger['meta'][ $pieces[2] . '_readable' ] ) ) {
					$value = $trigger['meta'][ $pieces[2] . '_readable' ];
					if ( 'Any form' === $value ) {
						$value = esc_html__( 'Divi form', 'uncanny-automator' );
					}
				}
				return $value;
			}

			$trigger_id     = absint( $trigger['ID'] );
			$trigger_log_id = absint( $replace_args['trigger_log_id'] );
			$parse_tokens   = array(
				'trigger_id'     => $trigger_id,
				'trigger_log_id' => $trigger_log_id,
				'user_id'        => $user_id,
			);

			$meta_key = sprintf( '%d:%s', $pieces[0], $pieces[1] );

			$entry = Automator()->db->trigger->get_token_meta( $meta_key, $parse_tokens );

			if ( empty( $entry ) ) {
				continue;
			}

			$value       = $entry;
			$token_piece = $pieces[2];
			$main_parts  = array();
			$suffix      = null;

			if ( strpos( $token_piece, '__' ) !== false ) {
				// Split the string by '__' and '|'
				$main_parts = explode( '__', $token_piece );
				$suffix     = strstr( $token_piece, '|' );

				// Combine the first two elements with a hyphen and append the suffix
				$token_piece = $main_parts[0] . '-' . $main_parts[1] . $suffix;
			}

			if ( in_array( '-1', explode( '|', $token_piece ), false ) ) {
				$value = $this->match_token_suffix( $token_piece, $entry );

			} elseif ( is_array( $entry ) ) {
				$value = isset( $entry[ $token_piece ] ) ? $entry[ $token_piece ] : '';
				if ( is_array( $value ) ) {
					$value = implode( ', ', $value );
				}
			}

			// If the token is not found, try to find it with unique ID + suffix
			if ( empty( $value ) && ! empty( $main_parts[1] ) && ! empty( $suffix ) ) {
				$token_piece = $main_parts[1] . $suffix;
				if ( is_array( $entry ) ) {
					foreach ( $entry as $key => $_value ) {
						if ( strpos( $key, $token_piece ) !== false ) {
							$value = $_value;
							break;
						}
					}
				}
			}
		}

		return $value;
	}

	/**
	 * @param $search_key_suffix
	 * @param $array
	 *
	 * @return mixed|string
	 */
	public function match_token_suffix( $search_key_suffix, $array ) {
		// Initialize a variable to store the matched value
		$matched_value     = null;
		$search_key_suffix = str_replace( '-1|', '', $search_key_suffix );

		// Iterate through the array to find a key that ends with the search suffix
		foreach ( $array as $key => $value ) {
			if ( substr( $key, -strlen( $search_key_suffix ) ) === $search_key_suffix ) {
				$matched_value = $value;
				break;
			}
		}

		if ( $matched_value !== null ) {
			return $matched_value;
		}
			return '';
	}
}
