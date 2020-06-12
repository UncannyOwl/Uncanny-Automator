<?php

namespace Uncanny_Automator;

/**
 * Class Wpff_Tokens
 * @package Uncanny_Automator
 */
class Wpff_Tokens {

	/**
	 * Integration code
	 * @var string
	 */
	public static $integration = 'WPFF';

	/**
	 * Wpff_Tokens constructor.
	 */
	public function __construct() {
		add_filter( 'automator_maybe_trigger_wpff_wpffforms_tokens', [ $this, 'wpff_possible_tokens' ], 20, 2 );
		add_filter( 'automator_maybe_parse_token', [ $this, 'wpff_token' ], 20, 6 );
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
			if ( class_exists( 'Ninja_Forms' ) ) {
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
	function wpff_possible_tokens( $tokens = [], $args = [] ) {
		$form_id      = $args['value'];
		$trigger_meta = $args['meta'];

		$form_ids = [];
		if ( ! empty( $form_id ) && 0 !== $form_id && is_numeric( $form_id ) ) {
			$form = wpFluent()->table( 'fluentform_forms' )->where( 'id', '=', $form_id )
			                  ->select( [ 'id', 'title', 'form_fields' ] )
			                  ->orderBy( 'id', 'DESC' )
			                  ->get();
			if ( $form ) {
				$form                  = array_pop( $form );
				$form_ids[ $form->id ] = json_decode( $form->form_fields, true );
			}
		}

		if ( empty( $form_ids ) ) {
			$forms = wpFluent()->table( 'fluentform_forms' )
			                   ->select( [ 'id', 'title', 'form_fields' ] )
			                   ->orderBy( 'id', 'DESC' )
			                   ->get();
			foreach ( $forms as $form ) {
				$form_ids[ $form->id ] = json_decode( $form->form_fields, true );
			}
		}

		if ( ! empty( $form_ids ) ) {
			foreach ( $form_ids as $form_id => $meta ) {
				$fields     = [];
				$raw_fields = isset( $meta['fields'] ) ? $meta['fields'] : [];
				if ( is_array( $meta ) && ! empty( $raw_fields ) ) {
					foreach ( $raw_fields as $field ) {
						$parent_input_id = $field['attributes']['name'];
						if ( isset( $field['fields'] ) ) {
							foreach ( $field['fields'] as $f_field_id => $f_fields ) {
								if ( 1 === (int) $f_fields['settings']['visible'] ) {
									$input_id    = $f_field_id;
									$input_title = $f_fields['settings']['label'];
									$token_id    = "$form_id|$parent_input_id|$input_id";
									$fields[]    = [
										'tokenId'         => $token_id,
										'tokenName'       => $input_title,
										'tokenType'       => isset( $f_fields['attributes']['type'] ) ? $f_fields['attributes']['type'] : 'text',
										'tokenIdentifier' => $trigger_meta,
									];
								}
							}
							continue;
						}

						$input_title = $field['settings']['label'];
						$token_id    = "$form_id|$parent_input_id";
						$fields[]    = [
							'tokenId'         => $token_id,
							'tokenName'       => $input_title,
							'tokenType'       => isset( $field['attributes']['type'] ) ? $field['attributes']['type'] : 'text',
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
	public function wpff_token( $value, $pieces, $recipe_id, $trigger_data, $user_id, $replace_args ) {
		if ( $pieces ) {
			if ( in_array( 'WPFFFORMS', $pieces ) ) {
				global $wpdb;
				$trigger_id     = $pieces[0];
				$trigger_meta   = $pieces[1];
				$field          = $pieces[2];
				$trigger_log_id = isset( $replace_args['trigger_log_id'] ) ? absint( $replace_args['trigger_log_id'] ) : 0;
				$entry          = $wpdb->get_var( "SELECT meta_value 
													FROM {$wpdb->prefix}uap_trigger_log_meta 
													WHERE meta_key = '$trigger_meta' 
													AND automator_trigger_log_id = $trigger_log_id
													AND automator_trigger_id = $trigger_id
													LIMIT 0, 1" );
				$entry          = maybe_unserialize( $entry );
				$to_match       = "{$trigger_id}:{$trigger_meta}:{$field}";
				if ( is_array( $entry ) && key_exists( $to_match, $entry ) ) {
					$value = $entry[ $to_match ];
				} else {
					$value = '';
				}
			}
		}

		return $value;
	}
}