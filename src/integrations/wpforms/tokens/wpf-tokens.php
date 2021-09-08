<?php

namespace Uncanny_Automator;

use WPForms_Form_Handler;

/**
 * Class Wpf_Tokens
 * @package Uncanny_Automator
 */
class Wpf_Tokens {

	/**
	 * Wpf_Tokens constructor.
	 */
	public function __construct() {

		add_filter( 'automator_maybe_trigger_wpf_anonwpfforms_tokens', [ $this, 'wpf_possible_tokens' ], 20, 2 );
		add_filter( 'automator_maybe_trigger_wpf_wpfforms_tokens', [ $this, 'wpf_possible_tokens' ], 20, 2 );
		add_filter( 'automator_maybe_parse_token', [ $this, 'wpf_token' ], 20, 6 );
		add_filter( 'automator_save_wp_form', [ $this, 'wpf_form_save_entry' ], 20, 4 );
		add_action( 'automator_save_anon_wp_form', [ $this, 'wpf_form_save_entry' ], 20, 4 );
	}

	/**
	 * @param array $tokens
	 * @param array $args
	 *
	 * @return array
	 */
	function wpf_possible_tokens( $tokens = array(), $args = array() ) {
		$form_id      = $args['value'];
		$trigger_meta = $args['meta'];
		$form_ids     = array();
		$wpforms      = new WPForms_Form_Handler();
		if ( ! empty( $form_id ) && 0 !== $form_id && is_numeric( $form_id ) ) {
			$form = $wpforms->get( $form_id );
			if ( $form ) {
				$form_ids[] = $form->ID;
			}
		}

		if ( empty( $form_ids ) ) {
			return $tokens;
		}
		$allowed_token_types = array( 'url', 'email', 'float', 'int', 'text');
		if ( ! empty( $form_ids ) ) {
			foreach ( $form_ids as $form_id ) {
				$fields = array();
				$form   = $wpforms->get( $form_id );
				$meta   = wpforms_decode( $form->post_content );
				if ( is_array( $meta['fields'] ) ) {
					foreach ( $meta['fields'] as $field ) {
						$input_id    = $field['id'];
						$input_title = isset($field['label'])? $field['label'] :$field['name'];
						$token_id    = "$form_id|$input_id";
						$fields[]    = [
							'tokenId'         => $token_id,
							'tokenName'       => $input_title,
							'tokenType'       => in_array( $field['type'], $allowed_token_types) ? $field['type'] : 'text',
							'tokenIdentifier' => $trigger_meta,
						];
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
	 * @param $trigger_data
	 * @param $user_id
	 * @param $replace_args
	 *
	 * @return null|string
	 */
	public function wpf_token( $value, $pieces, $recipe_id, $trigger_data, $user_id, $replace_args ) {
		$piece = 'WPFFORMS';
		if ( $pieces ) {
			if ( in_array( $piece, $pieces ) || in_array( 'ANONWPFFORMS', $pieces ) ) {
				global $wpdb;
				$trigger_id   = $pieces[0];
				$trigger_meta = $pieces[1];
				$field        = $pieces[2];
				if ( $piece === $field || 'ANONWPFFORMS' === $field ) {
					if ( $trigger_data ) {
						foreach ( $trigger_data as $trigger ) {
							if ( array_key_exists( $field . '_readable', $trigger['meta'] ) ) {
								return $trigger['meta'][ $field . '_readable' ];
							}
						}
					}
				}
				$trigger_log_id = isset( $replace_args['trigger_log_id'] ) ? absint( $replace_args['trigger_log_id'] ) : 0;
				$entry          = $wpdb->get_var( "SELECT meta_value
													FROM {$wpdb->prefix}uap_trigger_log_meta
													WHERE meta_key = '{$trigger_meta}'
													AND automator_trigger_log_id = {$trigger_log_id}
													AND automator_trigger_id = {$trigger_id}
													LIMIT 0,1" );
				if ( empty( $entry ) ) {
					$entry = $wpdb->get_var( "SELECT meta_value
												FROM {$wpdb->prefix}uap_trigger_log_meta
												WHERE meta_key = '$field'
												AND automator_trigger_log_id = $trigger_log_id
												AND automator_trigger_id = {$trigger_id}
												LIMIT 0,1" );
				}
				$entry    = maybe_unserialize( $entry );
				$to_match = "{$trigger_id}:{$trigger_meta}:{$field}";

				if ( is_array( $entry ) && key_exists( $to_match, $entry ) ) {
					$value = $entry[ $to_match ];
				}
			}
		}

		return $value;
	}

	/**
	 * @param $fields
	 * @param $form_data
	 * @param $recipes
	 * @param $args
	 *
	 * @return void
	 */
	public function wpf_form_save_entry( $fields, $form_data, $recipes, $args ) {
		if ( ! is_array( $args ) ) {
			return;
		}
		foreach ( $args as $trigger_result ) {
			if ( true !== $trigger_result['result'] ) {
				continue;
			}

			if ( ! $recipes ) {
				continue;
			}
			foreach ( $recipes as $recipe ) {
				$triggers = $recipe['triggers'];
				if ( ! $triggers ) {
					continue;
				}
				foreach ( $triggers as $trigger ) {
					$trigger_id = $trigger['ID'];
					if ( ! array_key_exists( 'WPFFORMS', $trigger['meta'] ) && ! array_key_exists( 'ANONWPFFORMS', $trigger['meta'] ) ) {
						continue;
					}
					$trigger_args = $trigger_result['args'];
					$meta_key     = $trigger_args['meta'];
					$form_id      = $form_data['id'];
					$data         = array();
					if ( $fields ) {
						foreach ( $fields as $field ) {
							$field_id     = $field['id'];
							$key          = "{$trigger_id}:{$meta_key}:{$form_id}|{$field_id}";
							$data[ $key ] = $field['value'];
						}
					}

					$user_id        = (int) $trigger_result['args']['user_id'];
					$trigger_log_id = (int) $trigger_result['args']['get_trigger_id'];
					$run_number     = (int) $trigger_result['args']['run_number'];

					$args = [
						'user_id'        => $user_id,
						'trigger_id'     => $trigger_id,
						'meta_key'       => $meta_key,
						'meta_value'     => maybe_serialize( $data ),
						'run_number'     => $run_number, //get run number
						'trigger_log_id' => $trigger_log_id,
					];

					Automator()->insert_trigger_meta( $args );
				}
			}
		}
	}
}
