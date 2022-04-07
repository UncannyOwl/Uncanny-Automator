<?php

namespace Uncanny_Automator;

/**
 * Class Um_Tokens
 *
 * @package Uncanny_Automator
 */
class Um_Tokens {

	/**
	 * Pmp_Tokens constructor.
	 */
	public function __construct() {
		add_filter( 'automator_maybe_parse_token', array( $this, 'um_token' ), 20, 6 );
		add_filter( 'automator_maybe_trigger_um_umform_tokens', array( $this, 'um_possible_tokens' ), 20, 2 );
	}

	/**
	 * List all possible tokens.
	 *
	 * @param array $tokens
	 * @param array $args
	 *
	 * @return array
	 */
	public function um_possible_tokens( $tokens = array(), $args = array() ) {
		if ( ! automator_do_identify_tokens() ) {
			return $tokens;
		}
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
						$fields[]    = array(
							'tokenId'         => $token_id,
							'tokenName'       => $input_title,
							'tokenType'       => $input_type,
							'tokenIdentifier' => $trigger_meta,
						);
					}
				}
				$tokens = array_merge( $tokens, $fields );

			}
		}//end if

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
	 * Parse the tokens.
	 *
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

		$to_match = array( 'WPROLE', 'UMFORM', 'UMFORM_FORM_TITLE' );

		if ( $pieces ) {

			if ( array_intersect( $to_match, $pieces ) ) {

				$piece = $pieces[1];
				$meta  = $pieces[2];

				if ( $trigger_data ) {
					// Initialize the return value to empty string.
					$value = '';
					foreach ( $trigger_data as $trigger ) {

						// Form title.
						if ( 'UMUSERREGISTER' === $pieces[1] && 'UMFORM_FORM_TITLE' === $pieces[2] ) {
							return $trigger['meta']['UMFORM_readable'];
						}

						// For forms.
						if ( ( 'UMFORM' === $pieces[1] || 'ANONUMFORM' === $pieces[1] ) && 'UMFORM' === $pieces[2] ) {

							if ( isset( $trigger['meta'][ $pieces[2] . '_readable' ] ) ) {
								return $trigger['meta'][ $pieces[2] . '_readable' ];
							}

							if ( isset( $trigger['meta'][ $pieces[1] . '_readable' ] ) ) {
								return $trigger['meta'][ $pieces[1] . '_readable' ];
							}
						}

						// For roles.
						if ( 'WPROLE' === $piece && isset( $trigger['meta'][ $piece ] ) ) {

							$role = $trigger['meta'][ $piece ];
							foreach ( wp_roles()->roles as $role_name => $role_info ) {
								if ( $role === $role_name ) {
									$value = $role_info['name'];
								}
							}
						} elseif ( key_exists( $piece, $trigger['meta'] ) ) {

							$token_info = explode( '|', $meta );
							$form_id    = $token_info[0];
							$meta_key   = $token_info[1];
							$match      = "{$meta_key}-{$form_id}";

							if ( automator_filter_has_var( $match, INPUT_POST ) ) {
								$value = sanitize_text_field( automator_filter_input( $match, INPUT_POST ) );
							} else {
								if ( automator_filter_has_var( $meta_key, INPUT_POST ) && ! is_array( automator_filter_input( $meta_key, INPUT_POST ) ) ) {
									$value = sanitize_text_field( automator_filter_input( $meta_key, INPUT_POST ) );
									// Apply a fix to the issue of multiple fields returning blank.
									if ( isset( $_POST[ $meta_key ] ) && is_array( $_POST[ $meta_key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
										$value = sanitize_text_field( implode( ', ', automator_filter_input_array( $meta_key, INPUT_POST ) ) );
									}
								} elseif ( automator_filter_has_var( $meta_key, INPUT_POST ) && is_array( automator_filter_input( $meta_key, INPUT_POST ) ) ) {
									$value = sanitize_text_field( join( ', ', automator_filter_input_array( $meta_key, INPUT_POST ) ) );
								} elseif ( automator_filter_has_var( "{$meta_key}_select", INPUT_POST ) ) {
									if ( is_array( automator_filter_input( "{$meta_key}_select", INPUT_POST ) ) ) {
										$value = sanitize_text_field( join( ', ', automator_filter_input_array( "{$meta_key}_select", INPUT_POST ) ) );
									} else {
										$value = sanitize_text_field( automator_filter_input( "{$meta_key}_select", INPUT_POST ) );
									}
								} else {
									$m_k = str_replace( '_select', '', $meta_key );
									if ( automator_filter_has_var( $m_k, INPUT_POST ) ) {
										if ( is_array( automator_filter_input( $m_k, INPUT_POST ) ) ) {
											$value = sanitize_text_field( join( ', ', automator_filter_input_array( $m_k, INPUT_POST ) ) );
										} else {
											$value = sanitize_text_field( automator_filter_input( $m_k, INPUT_POST ) );
										}
									}
								}
							}//end if
						}//end if
					}//end foreach
				}//end if
			}//end if
		}//end if

		return $value;
	}
}
