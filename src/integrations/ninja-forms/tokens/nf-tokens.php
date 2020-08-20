<?php

namespace Uncanny_Automator;


use function Ninja_Forms;

/**
 * Class Nf_Tokens
 * @package Uncanny_Automator
 */
class Nf_Tokens {

	/**
	 * Integration code
	 * @var string
	 */
	public static $integration = 'NF';

	public function __construct() {
		//*************************************************************//
		// See this filter generator AT automator-get-data.php
		// in function recipe_trigger_tokens()
		//*************************************************************//

		add_filter( 'automator_maybe_trigger_nf_nfforms_tokens', [ $this, 'nf_possible_tokens' ], 20, 2 );
		add_filter( 'automator_maybe_parse_token', [ $this, 'nf_token' ], 20, 6 );
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
	function nf_possible_tokens( $tokens = [], $args = [] ) {
		$form_id             = $args['value'];
		$trigger_integration = $args['integration'];
		$trigger_meta        = $args['meta'];

		$form_ids = [];
		if ( ! empty( $form_id ) && 0 !== $form_id && is_numeric( $form_id ) ) {
			$form = Ninja_Forms()->form( $form_id )->get();
			if ( $form ) {
				$form_ids[] = $form->get_id();
			}
		}

		if ( empty( $form_ids ) ) {
			$forms = Ninja_Forms()->form()->get_forms();
			foreach ( $forms as $form ) {
				$form_ids[] = $form->get_id();
			}
		}

		if ( ! empty( $form_ids ) ) {
			foreach ( $form_ids as $form_id ) {
				$fields = [];
				$meta   = Ninja_Forms()->form( $form_id )->get_fields();
				if ( is_array( $meta ) ) {
					foreach ( $meta as $field ) {
						if ( $field->get_setting( 'type' ) !== 'submit' ) {
							$input_id    = $field->get_id();
							$input_title = $field->get_setting( 'label' );
							$token_id    = "$form_id|$input_id";
							$fields[]    = [
								'tokenId'         => $token_id,
								'tokenName'       => $input_title,
								'tokenType'       => $field->get_setting( 'type' ),
								'tokenIdentifier' => $trigger_meta,
							];
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
			if ( in_array( 'NFFORMS', $pieces ) ) {
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
				}
			}
		}

		return $value;
	}
}