<?php

namespace Uncanny_Automator;


/**
 * Class Wpf_Tokens
 * @package uncanny_automator
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
		$wpforms  = new \WPForms_Form_Handler();
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
	 *
	 * @return null|string
	 */
	public function wpf_token( $value, $pieces, $recipe_id, $trigger_data, $user_id, $replace_args ) {
		$piece = 'WPFFORMS';
		if ( $pieces ) {
			if ( in_array( $piece, $pieces ) ) {
				global $uncanny_automator, $wpdb;
				if ( $trigger_data ) {
					$trigger_id     = $replace_args['trigger_id'];
					$trigger_log_id = $replace_args['trigger_log_id'];
					$token_info     = explode( '|', $pieces[2] );
					$form_id        = $token_info[0];
					$meta_key       = $token_info[1];
					$meta_field     = $piece . '_' . $form_id;
					$user_meta      = $this->get_form_data_from_trigger_meta( $user_id, $meta_field, $trigger_id, $trigger_log_id );
					if ( ! empty( $user_meta ) && key_exists( trim( $meta_key ), $user_meta ) ) {
						$value = $user_meta[ $meta_key ]['value'];
					}
				}
			}
		}
		/*if ( $pieces ) {
			if ( in_array( $piece, $pieces ) ) {
				global $uncanny_automator;
				//$user_id       = wp_get_current_user()->ID;
				//$recipe_log_id = $uncanny_automator->maybe_create_recipe_log_entry( $recipe_id, $user_id );
				$recipe_log_id_raw = $uncanny_automator->maybe_create_recipe_log_entry( $recipe_id, $user_id );
				$recipe_log_id     = null;
				if ( is_array( $recipe_log_id_raw ) && key_exists( 'recipe_log_id', $recipe_log_id_raw ) ) {
					$recipe_log_id = absint( $recipe_log_id_raw['recipe_log_id'] );
				}
				if ( $trigger_data ) {
					foreach ( $trigger_data as $trigger ) {
						if ( key_exists( $piece, $trigger['meta'] ) ) {
							$trigger_id     = $trigger['ID'];
							$trigger_log_id = $uncanny_automator->maybe_get_trigger_id( $user_id, $trigger_id, $recipe_id, $recipe_log_id );
							$trigger_log_id = $trigger_log_id['get_trigger_id'];
							$token_info     = explode( '|', $pieces[2] );
							$form_id        = $token_info[0];
							$meta_key       = $token_info[1];
							$meta_field     = $piece . '_' . $form_id;
							$user_meta      = $this->get_form_data_from_trigger_meta( $user_id, $meta_field, $trigger_id, $trigger_log_id );

							if ( key_exists( trim( $meta_key ), $user_meta ) ) {
								$value = $user_meta[ $meta_key ]['value'];
							}
						}
					}
				}
			}
		}*/

		return $value;
	}

	/**
	 * @param $user_id
	 * @param $meta_key
	 * @param $trigger_id
	 * @param $trigger_log_id
	 *
	 * @return mixed|string
	 */
	public function get_form_data_from_trigger_meta( $user_id, $meta_key, $trigger_id, $trigger_log_id ) {
		global $wpdb;
		if ( empty( $user_id ) || empty( $meta_key ) || empty( $trigger_id ) || empty( $trigger_log_id ) ) {
			return '';
		}

		$meta_value = $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->prefix}uap_trigger_log_meta WHERE user_id = %d AND meta_key = %s AND automator_trigger_id = %d AND automator_trigger_log_id = %d ORDER BY ID DESC LIMIT 0,1", $user_id, $meta_key, $trigger_id, $trigger_log_id ) );
		if ( ! empty( $meta_value ) ) {
			return maybe_unserialize( $meta_value );
		}

		return '';
	}

	/**
	 * @param $fields
	 * @param $form_data
	 * @param $recipes
	 *
	 * @return null|string
	 */
	public function wpf_form_save_entry( $fields, $form_data, $recipes, $args ) {
		if ( is_array( $args ) ) {
			$trigger_result = array_pop( $args );
			if ( TRUE === $trigger_result['result'] ) {
				global $uncanny_automator;
				if ( $recipes ) {
					$user_id = wp_get_current_user()->ID;
					foreach ( $recipes as $recipe ) {
						$triggers = $recipe['triggers'];
						if ( $triggers ) {
							foreach ( $triggers as $trigger ) {
								$trigger_id = $trigger['ID'];
								if ( ! key_exists( 'WPFFORMS', $trigger['meta'] ) ) {
									continue;
								} else {
									$form_id = (int) $trigger['meta']['WPFFORMS'];
									$data           = $fields;
									$user_id        = (int) $trigger_result['args']['user_id'];
									$trigger_log_id = (int) $trigger_result['args']['get_trigger_id'];
									$run_number     = (int) $trigger_result['args']['run_number'];

									$args = [
										'user_id'        => $user_id,
										'trigger_id'     => $trigger_id,
										'meta_key'       => 'WPFFORMS_' . $form_id,
										'meta_value'     => serialize( $data ),
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