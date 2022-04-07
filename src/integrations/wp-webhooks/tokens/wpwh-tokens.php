<?php

namespace Uncanny_Automator;

/**
 * Class Wpwh_Tokens
 *
 * @package Uncanny_Automator
 */
class Wpwh_Tokens {

	/**
	 * Integration code
	 *
	 * @var string
	 */
	public static $integration = 'WPWEBHOOKS';

	public function __construct() {

		add_filter( 'automator_maybe_trigger_wpwebhooks_wpwhtrigger_tokens', array( $this, 'wpwh_possible_tokens' ), 20, 2 );
		add_filter( 'automator_maybe_parse_token', array( $this, 'wpwh_token' ), 20, 6 );
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
			if ( class_exists( 'WP_Webhooks_Pro' ) ) {
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
	function wpwh_possible_tokens( $tokens = array(), $args = array() ) {
		if ( ! automator_do_identify_tokens() ) {
			return $tokens;
		}
		if ( 'WPWHTRIGGER' === $args['meta'] ) {
			$trigger_meta = $args['meta'];
			$triggers     = WPWHPRO()->webhook->get_triggers();

			if ( ! empty( $triggers ) ) {
				$returns_code = array();
				if ( ! empty( $args['value'] ) ) {
					switch ( $args['value'] ) {
						case 'create_user':
						case 'login_user':
						case 'update_user':
							$possible_tokens = array(
								'ID',
								'user_login',
								'user_pass',
								'user_nicename',
								'user_email',
								'user_url',
								'user_registered',
								'user_activation_key',
								'user_status',
								'display_name',
							);
							foreach ( $possible_tokens as $_token ) {
								$fields[] = array(
									'tokenId'         => 'data|' . $_token,
									'tokenName'       => $_token,
									'tokenType'       => 'text',
									'tokenIdentifier' => $trigger_meta,
								);
							}
							$tokens = array_merge( $tokens, $fields );
							break;
						case 'deleted_user':
							$possible_tokens = array(
								'user_id',
								'reassign',
							);
							foreach ( $possible_tokens as $_token ) {
								$fields[] = array(
									'tokenId'         => $_token,
									'tokenName'       => $_token,
									'tokenType'       => 'text',
									'tokenIdentifier' => $trigger_meta,
								);
							}
							$tokens = array_merge( $tokens, $fields );
							break;
						case 'post_create':
						case 'post_update':
						case 'post_delete':
						case 'post_trash':
							$possible_tokens = array(
								'ID',
								'post_author',
								'post_date',
								'post_date_gmt',
								'post_content',
								'post_title',
								'post_excerpt',
								'post_status',
								'comment_status',
								'ping_status',
								'post_password',
								'post_name',
								'to_ping',
								'pinged',
								'post_modified',
								'post_modified_gmt',
								'post_content_filtered',
								'post_parent',
								'guid',
								'menu_order',
								'post_type',
								'post_mime_type',
								'comment_count',
								'filter',
							);
							foreach ( $possible_tokens as $_token ) {
								$fields[] = array(
									'tokenId'         => 'post|' . $_token,
									'tokenName'       => $_token,
									'tokenType'       => 'text',
									'tokenIdentifier' => $trigger_meta,
								);
							}
							$tokens = array_merge( $tokens, $fields );
							break;
					}
				}
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
	 *
	 * @return string|null
	 */
	public function wpwh_token( $value, $pieces, $recipe_id, $trigger_data, $user_id, $replace_args ) {
		if ( $pieces ) {
			if ( in_array( 'WPWHTRIGGER', $pieces ) ) {
				global $wpdb;
				$token_info   = explode( '|', $pieces[2] );
				$request_data = $this->get_form_data_from_trigger_meta( 'WPWHTRIGGER_request_body', $replace_args['trigger_id'], $replace_args['trigger_log_id'] );
				if ( count( $token_info ) > 1 ) {
					$value = isset( $request_data[ $token_info[0] ][ $token_info[1] ] ) ? $request_data[ $token_info[0] ][ $token_info[1] ] : '';
				} else {
					$value = isset( $request_data[ $token_info[0] ] ) ? $request_data[ $token_info[0] ] : '';
				}
			}
		}

		return $value;
	}

	/**
	 * @param $meta_key
	 * @param $trigger_id
	 *
	 * @return mixed|string
	 */
	public function get_form_data_from_trigger_meta( $meta_key, $trigger_id, $trigger_log_id ) {
		global $wpdb;
		if ( empty( $meta_key ) || empty( $trigger_id ) ) {
			return '';
		}

		$meta_value = $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->prefix}uap_trigger_log_meta WHERE meta_key = %s AND automator_trigger_id = %d AND automator_trigger_log_id = %d ORDER BY ID DESC LIMIT 0,1", $meta_key, $trigger_id, $trigger_log_id ) );
		if ( ! empty( $meta_value ) ) {
			return maybe_unserialize( $meta_value );
		}

		return '';
	}
}
