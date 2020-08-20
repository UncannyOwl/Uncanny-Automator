<?php

namespace Uncanny_Automator;


use WPForms_Form_Handler;

/**
 * Class Wpf_Tokens
 * @package Uncanny_Automator
 */
class Wpf_Tokens {

	/**
	 * Integration code
	 * @var string
	 */
	public static $integration = 'WPF';

	public function __construct() {
		//*************************************************************//
		// See this filter generator AT automator-get-data.php
		// in function recipe_trigger_tokens()
		//*************************************************************//

		add_filter( 'automator_maybe_trigger_wpf_wpfforms_tokens', [ $this, 'wpf_possible_tokens' ], 20, 2 );
		add_filter( 'automator_maybe_parse_token', [ $this, 'wpf_token' ], 20, 6 );
		add_filter( 'automator_save_wp_form', [ $this, 'wpf_form_save_entry' ], 20, 4 );
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
			if ( class_exists( 'WPForms' ) ) {
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
	function wpf_possible_tokens( $tokens = [], $args = [] ) {
		$form_id             = $args['value'];
		$trigger_integration = $args['integration'];
		$trigger_meta        = $args['meta'];

		$form_ids = [];
		$wpforms  = new WPForms_Form_Handler();
		if ( ! empty( $form_id ) && 0 !== $form_id && is_numeric( $form_id ) ) {
			$form = $wpforms->get( $form_id );
			if ( $form ) {
				$form_ids[] = $form->ID;
			}
		}

		if ( empty( $form_ids ) ) {
			$forms = $wpforms->get( '', array( 'orderby' => 'title' ) );
			if ( ! empty( $forms ) ) {
				foreach ( $forms as $form ) {
					$form_ids[] = $form->ID;
				}
			}
		}

		if ( ! empty( $form_ids ) ) {
			foreach ( $form_ids as $form_id ) {
				$fields = [];
				$form   = $wpforms->get( $form_id );
				$meta   = wpforms_decode( $form->post_content );
				if ( is_array( $meta['fields'] ) ) {
					foreach ( $meta['fields'] as $field ) {
						$input_id    = $field['id'];
						$input_title = $field['label'];
						$token_id    = "$form_id|$input_id";
						$fields[]    = [
							'tokenId'         => $token_id,
							'tokenName'       => $input_title,
							'tokenType'       => $field['type'],
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
			if ( in_array( $piece, $pieces ) ) {
				global $wpdb;
				$trigger_id     = $pieces[0];
				$trigger_meta   = $pieces[1];
				$field          = $pieces[2];
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
	 * @return null|string
	 */
	public function wpf_form_save_entry( $fields, $form_data, $recipes, $args ) {
		if ( is_array( $args ) ) {
			foreach ( $args as $trigger_result ) {
				//$trigger_result = array_pop( $args );
				if ( true === $trigger_result['result'] ) {
					global $uncanny_automator;
					if ( $recipes ) {
						foreach ( $recipes as $recipe ) {
							$triggers = $recipe['triggers'];
							if ( $triggers ) {
								foreach ( $triggers as $trigger ) {
									$trigger_id = $trigger['ID'];
									if ( ! key_exists( 'WPFFORMS', $trigger['meta'] ) ) {
										continue;
									} else {
										$trigger_args = $trigger_result['args'];
										$meta_key     = $trigger_args['meta'];
										$form_id      = $form_data['id'];
										$data         = [];
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

										$uncanny_automator->insert_trigger_meta( $args );
									}
								}
							}
						}
					}
				}
			}
		}
	}
}