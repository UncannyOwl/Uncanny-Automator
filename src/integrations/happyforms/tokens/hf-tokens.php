<?php

namespace Uncanny_Automator;

/**
 * Class Hf_Tokens
 *
 * @package Uncanny_Automator
 */
class Hf_Tokens {

	public function __construct() {
		add_filter( 'automator_maybe_trigger_hf_hfform_tokens', array( $this, 'hf_possible_tokens' ), 20, 2 );
		add_filter( 'automator_maybe_parse_token', array( $this, 'hf_token' ), 20, 6 );
		add_filter( 'automator_maybe_trigger_hf_anonhfform_tokens', array( $this, 'hf_possible_tokens' ), 20, 2 );
	}

	/**
	 * Prepare tokens.
	 *
	 * @param array $tokens .
	 * @param array $args .
	 *
	 * @return array
	 */
	public function hf_possible_tokens( $tokens = array(), $args = array() ) {
		if ( ! automator_do_identify_tokens() ) {
			return $tokens;
		}
		$form_id             = $args['value'];
		$trigger_integration = $args['integration'];
		$trigger_meta        = $args['meta'];

		if ( ! empty( $form_id ) && 0 !== $form_id && is_numeric( $form_id ) ) {
			$form_controller = happyforms_get_form_controller();
			$form            = $form_controller->get( $form_id );
			if ( $form ) {
				$fields = array();
				$meta   = $form['parts'];
				if ( is_array( $meta ) && ! empty( $meta ) ) {
					foreach ( $meta as $field ) {
						$input_id   = $field['id'];
						$field_type = 'text';
						if ( 'int' === $field['type'] || 'numeric' === $field['type'] || 'number' === $field['type'] ) {
							$field_type = 'int';
						}
						if ( 'email' === $field['type'] ) {
							$field_type = 'email';
						}
						$input_title = empty( $field['label'] ) ? $field['type'] : $field['label'];
						$token_id    = "$form_id|$input_id";
						$fields[]    = array(
							'tokenId'         => $token_id,
							'tokenName'       => $input_title,
							'tokenType'       => $field_type,
							'tokenIdentifier' => $trigger_meta,
						);
					}
				}
				$tokens = array_merge( $tokens, $fields );
			}
		}

		return $tokens;
	}

	/**
	 * Parse the token.
	 *
	 * @param string $value .
	 * @param array $pieces .
	 * @param string $recipe_id .
	 *
	 * @param        $trigger_data
	 * @param        $user_id
	 * @param        $replace_args
	 *
	 * @return null|string
	 */
	public function hf_token( $value, $pieces, $recipe_id, $trigger_data, $user_id, $replace_args ) {

		if ( $pieces ) {

			if ( in_array( 'HFFORM', $pieces, true ) || in_array( 'ANONHFFORM', $pieces, true ) ) {

				global $wpdb;

				if ( ! empty( $trigger_data ) ) {

					foreach ( $trigger_data as $trigger ) {

						$trigger_id   = absint( $pieces[0] );
						$trigger_meta = $pieces[1];
						$field        = $pieces[2];

						if ( absint( $trigger['ID'] ) === $trigger_id ) {
							// check if readable meta exist.
							if ( ! empty( $trigger['meta'] ) ) {
								if ( isset( $trigger['meta'][ $field . '_readable' ] ) ) {
									$value = $trigger['meta'][ $field . '_readable' ];
								} elseif ( isset( $trigger['meta'][ $field ] ) ) {
									$value = $trigger['meta'][ $field ];
								} else {
									$value = ''; // Fix. Pass empty value to $value to fire the next empty condition.
								}
							}

							if ( empty( $value ) ) {

								$trigger_log_id = isset( $replace_args['trigger_log_id'] ) ? absint( $replace_args['trigger_log_id'] ) : 0;

								$entry = $wpdb->get_var(
									$wpdb->prepare(
										"SELECT meta_value
										FROM {$wpdb->prefix}uap_trigger_log_meta
										WHERE meta_key = %s
										AND automator_trigger_log_id = %d
										AND automator_trigger_id = %d
										LIMIT 0, 1",
										$trigger_meta,
										$trigger_log_id,
										$trigger_id
									)
								);

								$entry    = maybe_unserialize( $entry );
								$to_match = "{$trigger_id}:{$trigger_meta}:{$field}";
								if ( is_array( $entry ) && key_exists( $to_match, $entry ) ) {
									$value = $entry[ $to_match ];
								}
							}
						}
					}
				}
			}
		}

		return $value;
	}
}
