<?php

namespace Uncanny_Automator;

use Caldera_Forms_Forms;

/**
 * Class Cf_Tokens
 *
 * @package Uncanny_Automator
 */
class Cf_Tokens {

	public function __construct() {
		add_filter( 'automator_maybe_trigger_cf_cfforms_tokens', array( $this, 'cf_possible_tokens' ), 20, 2 );
		add_filter( 'automator_maybe_parse_token', array( $this, 'parse_cf_token' ), 20, 6 );
		add_filter( 'automator_maybe_trigger_cf_anoncfforms_tokens', array( $this, 'cf_possible_tokens' ), 20, 2 );
		// Entry tokens
		add_filter( 'automator_maybe_trigger_cf_tokens', array( $this, 'cf_entry_possible_tokens' ), 20, 2 );
		add_filter( 'automator_maybe_parse_token', array( $this, 'cf_entry_tokens' ), 20, 6 );
	}

	/**
	 * @param array $tokens
	 * @param array $args
	 *
	 * @return array
	 */
	public function cf_possible_tokens( $tokens = array(), $args = array() ) {
		if ( ! automator_do_identify_tokens() ) {
			return $tokens;
		}
		$form_id      = $args['value'];
		$trigger_meta = $args['meta'];
		$fields       = array();
		if ( empty( $form_id ) ) {
			return $tokens;
		}

		$form = Caldera_Forms_Forms::get_form( $form_id );

		if ( ! empty( $form['fields'] ) ) {
			foreach ( $form['fields'] as $field ) {
				if ( $field['type'] !== 'html' && $field['type'] !== 'summary' && $field['type'] !== 'section_break' && $field['type'] !== 'button' ) {
					$input_id    = $field['ID'];
					$input_title = $field['label'];
					$token_id    = "$form_id|$input_id";
					$token_type  = $field['type'];
					$fields[]    = array(
						'tokenId'         => $token_id,
						'tokenName'       => $input_title,
						'tokenType'       => $token_type,
						'tokenIdentifier' => $trigger_meta,
					);
				}
			}
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
	public function parse_cf_token( $value, $pieces, $recipe_id, $trigger_data, $user_id, $replace_args ) {
		if ( $pieces ) {
			if ( in_array( 'CFFORMS', $pieces ) || in_array( 'ANONCFFORMS', $pieces ) ) {
				// Check if Form name token is used
				if ( isset( $pieces[2] ) && ( 'CFFORMS' === $pieces[2] || 'ANONCFFORMS' === $pieces[2] ) ) {
					foreach ( $trigger_data as $t_d ) {
						if ( isset( $t_d['meta']['CFFORMS'] ) || isset( $t_d['meta']['ANONCFFORMS'] ) ) {
							$form_id = isset( $t_d['meta']['ANONCFFORMS'] ) ? $t_d['meta']['ANONCFFORMS'] : $t_d['meta']['CFFORMS'];
							$form    = Caldera_Forms_Forms::get_form( $form_id );

							return $form['name'];
						}
					}
				}
				$token_info = explode( '|', $pieces[2] );
				$form_id    = (int) sanitize_text_field( $token_info[0] );
				$meta_key   = sanitize_text_field( $token_info[1] );

				$request_form_id  = automator_filter_input( 'formId', INPUT_POST );
				$request_meta_key = automator_filter_input( $meta_key, INPUT_POST );

				if ( isset( $request_form_id ) && absint( $request_form_id ) === $form_id && isset( $request_meta_key ) ) {
					if ( is_array( $request_meta_key ) ) {
						$value = sanitize_text_field( implode( ', ', $request_meta_key ) );
					} else {
						$value = sanitize_text_field( $request_meta_key );
					}
				}
			}//end if
		}//end if

		return $value;
	}

	/**
	 * @param $tokens
	 * @param $args
	 *
	 * @return array|mixed|\string[][]
	 */
	public function cf_entry_possible_tokens( $tokens = array(), $args = array() ) {
		$fields = array(
			array(
				'tokenId'         => 'CFENTRYID',
				'tokenName'       => __( 'Entry ID', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => 'CFENTRYTOKENS',
			),
			array(
				'tokenId'         => 'CFENTRYDATE',
				'tokenName'       => __( 'Entry submission date', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => 'CFENTRYTOKENS',
			),
		);

		$tokens = array_merge( $tokens, $fields );

		return $tokens;
	}

	/**
	 * @param $value
	 * @param $pieces
	 * @param $recipe_id
	 * @param $trigger_data
	 * @param $user_id
	 *
	 * @return string|null
	 */
	public function cf_entry_tokens( $value, $pieces, $recipe_id, $trigger_data, $user_id, $replace_args ) {
		if ( in_array( 'CFENTRYTOKENS', $pieces ) ) {
			if ( $trigger_data ) {
				foreach ( $trigger_data as $trigger ) {
					$trigger_id     = $trigger['ID'];
					$trigger_log_id = $replace_args['trigger_log_id'];
					$meta_key       = $pieces[2];
					$meta_value     = Automator()->helpers->recipe->get_form_data_from_trigger_meta( $meta_key, $trigger_id, $trigger_log_id, $user_id );
					if ( ! empty( $meta_value ) ) {
						$value = maybe_unserialize( $meta_value );
					}
				}
			}
		}

		return $value;
	}
}
