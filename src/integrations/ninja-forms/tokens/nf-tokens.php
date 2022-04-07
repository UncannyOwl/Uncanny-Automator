<?php

namespace Uncanny_Automator;

use function Ninja_Forms;

/**
 * Class Nf_Tokens
 *
 * @package Uncanny_Automator
 */
class Nf_Tokens {

	public function __construct() {
		add_filter( 'automator_maybe_trigger_nf_nfforms_tokens', array( $this, 'nf_possible_tokens' ), 20, 2 );
		add_filter( 'automator_maybe_parse_token', array( $this, 'nf_token' ), 20, 6 );
		add_filter( 'automator_maybe_trigger_nf_anonnfforms_tokens', array( $this, 'nf_possible_tokens' ), 20, 2 );
	}

	/**
	 * @param array $tokens
	 * @param array $args
	 *
	 * @return array
	 */
	public function nf_possible_tokens( $tokens = array(), $args = array() ) {
		if ( ! automator_do_identify_tokens() ) {
			return $tokens;
		}
		$form_id      = $args['value'];
		$trigger_meta = $args['meta'];

		$form_ids = array();
		if ( ! empty( $form_id ) && 0 !== $form_id && is_numeric( $form_id ) ) {
			$form = Ninja_Forms()->form( $form_id )->get();
			if ( $form ) {
				$form_ids[] = $form->get_id();
			}
		}

		// if no form exist then return
		if ( empty( $form_ids ) ) {
			return $tokens;
		}

		if ( ! empty( $form_ids ) ) {
			foreach ( $form_ids as $form_id ) {
				$fields = array();
				$meta   = Ninja_Forms()->form( $form_id )->get_fields();
				if ( is_array( $meta ) ) {
					foreach ( $meta as $field ) {
						if ( $field->get_setting( 'type' ) !== 'submit' ) {
							$input_id    = $field->get_id();
							$input_title = $field->get_setting( 'label' );
							$token_id    = "$form_id|$input_id";
							$fields[]    = array(
								'tokenId'         => $token_id,
								'tokenName'       => $input_title,
								'tokenType'       => $field->get_setting( 'type' ),
								'tokenIdentifier' => $trigger_meta,
							);
						}
					}
				}
				$tokens = array_merge( $tokens, $fields );
			}
		}

		return $tokens;
	}

	/**
	 * @param $value
	 * @param $pieces
	 * @param $recipe_id
	 *
	 * @return null|string
	 */
	public function nf_token( $value, $pieces, $recipe_id, $trigger_data, $user_id, $replace_args ) {
		if ( $pieces ) {
			if ( in_array( 'NFFORMS', $pieces, true ) || in_array( 'NFSUBFIELD', $pieces, true ) || in_array( 'ANONNFSUBFIELD', $pieces, true ) || in_array( 'ANONNFFORMS', $pieces, true ) || in_array( 'ANONNFSUBFORM', $pieces, true ) ) {


				// Render Form Name
				if ( isset( $pieces[2] ) && ( 'NFFORMS' === $pieces[2] || 'ANONNFFORMS' === $pieces[2] ) ) {
					foreach ( $trigger_data as $t_d ) {
						if ( empty( $t_d ) ) {
							continue;
						}
						if ( isset( $t_d['meta']['NFFORMS_readable'] ) ) {
							return $t_d['meta']['NFFORMS_readable'];
						}
						if ( isset( $t_d['meta']['ANONNFFORMS_readable'] ) ) {
							return $t_d['meta']['ANONNFFORMS_readable'];
						}
					}
				}

				$field = $pieces[2];

				// Form specific field
				if ( 'NFSUBFIELD' === $field || 'ANONNFSUBFIELD' === $field ) {
					if ( $trigger_data ) {
						foreach ( $trigger_data as $trigger ) {
							if ( array_key_exists( $field . '_readable', $trigger['meta'] ) ) {
								return $trigger['meta'][ $field . '_readable' ];
							}
						}
					}
				}

				// Form specific field value
				if ( 'SUBVALUE' === $field ) {
					if ( $trigger_data ) {
						foreach ( $trigger_data as $trigger ) {
							if ( array_key_exists( $field, $trigger['meta'] ) ) {
								return $trigger['meta'][ $field ];
							}
						}
					}
				}
				// Render Form ID
				if ( isset( $pieces[2] ) && ( 'NFFORMS_ID' === $pieces[2] || 'ANONNFFORMS_ID' === $pieces[2] ) ) {
					foreach ( $trigger_data as $t_d ) {
						if ( empty( $t_d ) ) {
							continue;
						}
						if ( isset( $t_d['meta']['NFFORMS'] ) ) {
							return $t_d['meta']['NFFORMS'];
						}
						if ( isset( $t_d['meta']['ANONNFFORMS'] ) ) {
							return $t_d['meta']['ANONNFFORMS'];
						}
					}
				}

				global $wpdb;
				$trigger_id     = $pieces[0];
				$trigger_meta   = $pieces[1];
				$field          = $pieces[2];
				$trigger_log_id = isset( $replace_args['trigger_log_id'] ) ? absint( $replace_args['trigger_log_id'] ) : 0;
				$entry          = $wpdb->get_var(
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
				$entry          = maybe_unserialize( $entry );
				$to_match       = "{$trigger_id}:{$trigger_meta}:{$field}";
				if ( is_array( $entry ) && key_exists( $to_match, $entry ) ) {
					$value = $entry[ $to_match ];
				}
				if ( is_array( $value ) ) {
					$value = join( ', ', $value );
				}
			}
		}

		return $value;
	}
}
