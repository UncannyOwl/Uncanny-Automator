<?php

namespace Uncanny_Automator;


/**
 * Class Um_Tokens
 * @package Uncanny_Automator
 */
class Um_Tokens {

	/**
	 * Integration code
	 * @var string
	 */
	public static $integration = 'UM';

	/**
	 * Pmp_Tokens constructor.
	 */
	public function __construct() {
		//*************************************************************//
		// See this filter generator AT automator-get-data.php
		// in function recipe_trigger_tokens()
		//*************************************************************//
		//add_filter( 'automator_maybe_trigger_um_tokens', [ $this, 'um_general_tokens' ], 20, 2 );
		add_filter( 'automator_maybe_parse_token', [ $this, 'um_token' ], 20, 6 );
		add_filter( 'automator_maybe_trigger_um_umform_tokens', [ $this, 'um_possible_tokens' ], 20, 2 );
	}

	/**
	 * @param array $tokens
	 * @param array $args
	 *
	 * @return array
	 */
	public function um_possible_tokens( $tokens = array(), $args = array() ) {
		$form_id      = absint( $args['value'] );
		$trigger_meta = $args['meta'];
		if ( function_exists( 'UM' ) ) {
			$um_fields = UM()->query()->get_attr( 'custom_fields', $form_id );
			if ( $um_fields ) {
				$fields = array();
				foreach ( $um_fields as $field ) {
					if ( isset( $field['public'] ) && 1 === absint( $field['public'] ) ) {
						$input_id    = $field['metakey'];
						$input_title = $field['title'];
						$token_id    = "$form_id|$input_id";
						$input_type  = $field['type'];
						$fields[]    = [
							'tokenId'         => $token_id,
							'tokenName'       => $input_title,
							'tokenType'       => $input_type,
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
	 * Only load this integration and its triggers and actions if the related plugin is active
	 *
	 * @param $status
	 * @param $plugin
	 *
	 * @return bool
	 */
	public function plugin_active( $status, $plugin ) {

		if ( self::$integration === $plugin ) {
			if ( class_exists( 'UM' ) || defined( 'um_url' ) ) {
				$status = true;
			} else {
				$status = false;
			}
		}

		return $status;
	}

	/**
	 * @param $value
	 * @param $pieces
	 * @param $recipe_id
	 * @param $trigger_data
	 * @param $user_id
	 * @param $replace_args
	 *
	 * @return string|null
	 */
	public function um_token( $value, $pieces, $recipe_id, $trigger_data, $user_id, $replace_args ) {
		
		$to_match = [ 'WPROLE', 'UMFORM' ];
		
		if ( $pieces ) {
			if ( array_intersect( $to_match, $pieces ) ) {
				$piece = $pieces[1];
				$meta  = $pieces[2];
				if ( $trigger_data ) {
					// Initialize the return value to empty string.
					$value = '';
					foreach ( $trigger_data as $trigger ) {
						if ( 'WPROLE' === $piece && isset( $trigger['meta'][ $piece ] ) ) {
							$role = $trigger['meta'][ $piece ];
							foreach ( wp_roles()->roles as $role_name => $role_info ) {
								if ( $role == $role_name ) {
									$value = $role_info['name'];
								}
							}

						} elseif ( key_exists( $piece, $trigger['meta'] ) ) {
							$token_info = explode( '|', $meta );
							$form_id    = $token_info[0];
							$meta_key   = $token_info[1];
							$match      = "{$meta_key}-{$form_id}";
							if ( isset( $_POST[ $match ] ) ) {
								$value = sanitize_text_field( $_POST[ $match ] );
							} else {
								if ( isset( $_POST[ $meta_key ] ) && ! is_array( $_POST[ $meta_key ] ) ) {
									$value = sanitize_text_field( $_POST[ $meta_key ] );
								} elseif ( isset( $_POST[ $meta_key ] ) && is_array( $_POST[ $meta_key ] ) ) {
									$value = sanitize_text_field( join( ', ', $_POST[ $meta_key ] ) );
								} elseif ( isset( $_POST["{$meta_key}_select"] ) ) {
									if ( is_array( $_POST["{$meta_key}_select"] ) ) {
										$value = sanitize_text_field( join( ', ', $_POST["{$meta_key}_select"] ) );
									} else {
										$value = sanitize_text_field( $_POST["{$meta_key}_select"] );
									}
								} else {
									$m_k = str_replace( '_select', '', $meta_key );
									if ( isset( $_POST[ $m_k ] ) ) {
										if ( is_array( $_POST[ $m_k ] ) ) {
											$value = sanitize_text_field( join( ', ', $_POST[ $m_k ] ) );
										} else {
											$value = sanitize_text_field( $_POST[ $m_k ] );
										}
									}
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
