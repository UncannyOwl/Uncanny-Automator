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

		// Legacy-compat only. Resolves DIVIFORM tokens for recipes created by the
		// old trait-based triggers (save_tokens() stored PHP arrays keyed by
		// {post_id}-{uid}|field). Modern abstract-framework recipes are resolved
		// by Token_Identifier_Partitioner via the framework's own filter — this
		// parser reads a different meta key ({trigger_id}:{trigger_code} vs
		// the framework's {trigger_id}:DIVIFORM JSON bucket) so it is a no-op
		// for all modern Divi recipes.
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

		// Wildcard field shape lives on the modern class — single source of truth.
		$form_fields = \Uncanny_Automator\Integrations\Divi\Divi_Tokens::wildcard_field_definitions();

		if ( intval( '-1' ) !== intval( $form_id ) ) {
			$updated_form_id_codes = array(
				'ANON_DIVI_SUBMIT_FORM',
				'DIVI_SUBMIT_FORM',
				'ANON_DIVI_SUBMIT_FORM_FIELD',
				'DIVI_SUBMIT_FORM_FIELD',
			);
			$form_fields           = in_array( $trigger_code, $updated_form_id_codes, true )
				? Divi_Helpers::get_form_by_id( $form_id, true )
				: Divi_Helpers::get_form_by_id( $form_id );
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

		$form_name_codes = array( 'DIVISUBMITFORM', 'ANONDIVISUBMITFORM', 'DIVI_SUBMIT_FORM', 'ANON_DIVI_SUBMIT_FORM' );

		foreach ( $trigger_data as $trigger ) {
			// Meta for form name
			if ( in_array( $pieces[1], $form_name_codes, true ) && 'DIVIFORM' === $pieces[2] ) {
				if ( isset( $trigger['meta'][ $pieces[2] . '_readable' ] ) ) {
					$value = $trigger['meta'][ $pieces[2] . '_readable' ];
					if ( 'Any form' === $value ) {
						$value = esc_html_x( 'Divi form', 'Divi', 'uncanny-automator' );
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

			if ( false !== strpos( $token_piece, '__' ) ) {
				// Split the string by '__' and '|'
				$main_parts = explode( '__', $token_piece );
				$suffix     = strstr( $token_piece, '|' );

				// Combine the first two elements with a hyphen and append the suffix
				$token_piece = $main_parts[0] . '-' . $main_parts[1] . $suffix;
			}

			if ( in_array( '-1', explode( '|', $token_piece ), true ) ) {
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
						if ( false !== strpos( $key, $token_piece ) ) {
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
	 * @param string $search_key_suffix
	 * @param array  $entries Token meta map keyed by `{form_id}|{field_id}`.
	 *
	 * @return mixed|string
	 */
	public function match_token_suffix( $search_key_suffix, $entries ) {
		$matched_value     = null;
		$search_key_suffix = str_replace( '-1|', '', $search_key_suffix );

		foreach ( $entries as $key => $value ) {
			if ( substr( $key, -strlen( $search_key_suffix ) ) === $search_key_suffix ) {
				$matched_value = $value;
				break;
			}
		}

		if ( null !== $matched_value ) {
			return $matched_value;
		}

		return '';
	}
}
