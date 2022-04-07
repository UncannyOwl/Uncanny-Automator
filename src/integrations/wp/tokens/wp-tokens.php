<?php

namespace Uncanny_Automator;

/**
 * Class WP_Anon_Tokens
 *
 * @package Uncanny_Automator
 */
class Wp_Tokens {

	/**
	 * Integration code
	 *
	 * @var string
	 */
	public static $integration = 'WP';

	/**
	 * Wp_Tokens constructor.
	 */
	public function __construct() {
		// Hide error for Automator Pro until Pro 3.1 is released.
		if ( PHP_MAJOR_VERSION >= 7 ) {
			set_error_handler(
				function ( $errno, $errstr, $file ) {
					return strpos( $file, '/tokens/wp-anon-tokens.php' ) !== false &&
					       strpos( $errstr, 'Declaration of' ) === 0;
				},
				E_WARNING
			);
		}

		add_filter( 'automator_maybe_trigger_wp_wppostcomments_tokens', array( $this, 'wp_possible_tokens' ), 20, 2 );
		add_filter( 'automator_maybe_parse_token', array( $this, 'parse_anonusercreated_token' ), 20, 6 );
		add_filter( 'automator_maybe_parse_token', array( $this, 'parse_wproles_token' ), 20, 6 );
	}

	/**
	 * @param array $tokens
	 * @param array $args
	 *
	 * @return array
	 */
	public function wp_possible_tokens( $tokens = array(), $args = array() ) {
		if ( ! automator_do_identify_tokens() ) {
			return $tokens;
		}
		$trigger_integration = $args['integration'];
		$trigger_meta        = $args['meta'];

		$fields = array(
			array(
				'tokenId'         => 'authorname',
				'tokenName'       => __( "Post's author name", 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => $trigger_meta,
			),
			array(
				'tokenId'         => 'authoremail',
				'tokenName'       => __( "Post's author email", 'uncanny_automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => $trigger_meta,
			),
		);

		$tokens = array_merge( $tokens, $fields );

		return $tokens;
	}

	/**
	 * @param     $value
	 * @param     $pieces
	 * @param     $recipe_id
	 * @param     $trigger_data
	 *
	 * @param int $user_id
	 * @param     $replace_args
	 *
	 * @return mixed
	 */
	public function parse_anonusercreated_token( $value, $pieces, $recipe_id, $trigger_data, $user_id = 0, $replace_args = array() ) {
		$piece = 'WPPOSTCOMMENTS';
		if ( $pieces ) {
			if ( in_array( $piece, $pieces ) ) {

				if ( $trigger_data ) {
					foreach ( $trigger_data as $trigger ) {
						global $wpdb;
						$meta_field = 'WPPOSTCOMMENTS';
						$trigger_id = $trigger['ID'];
						$meta_value = $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->prefix}uap_trigger_log_meta WHERE meta_key LIKE %s AND automator_trigger_id = %d ORDER BY ID DESC LIMIT 0,1", "%%$meta_field%%", $trigger_id ) );
						if ( ! empty( $meta_value ) ) {
							$post_id = maybe_unserialize( $meta_value );
						} else {
							$post_id = $trigger['meta']['WPPOSTCOMMENTS'];
						}

						$post = get_post( $post_id );
						if ( ! empty( $post ) ) {
							if ( 'authorname' === $pieces[2] ) {
								$value = get_the_author_meta( 'display_name', $post->post_author );
							}
							if ( 'authoremail' === $pieces[2] ) {
								$value = get_the_author_meta( 'email', $post->post_author );
							}
							if ( 'WPPOSTTYPES' === $pieces[2] ) {
								$value = get_post_type( $post );
							}
						}
					}
				}
			}
		}

		return $value;
	}

	/**
	 * @param     $value
	 * @param     $pieces
	 * @param     $recipe_id
	 * @param     $trigger_data
	 *
	 * @param int $user_id
	 * @param     $replace_args
	 *
	 * @return mixed
	 */
	public function parse_wproles_token( $value, $pieces, $recipe_id, $trigger_data, $user_id = 0, $replace_args = array() ) {
		$piece = 'WPROLE';
		if ( $pieces ) {
			if ( in_array( $piece, $pieces ) ) {

				if ( $trigger_data ) {
					foreach ( $trigger_data as $trigger ) {
						global $wpdb;
						$meta_field = $trigger['ID'] . ':' . $pieces[1] . ':' . $pieces[2];
						$trigger_id = $trigger['ID'];
						$meta_value = $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->prefix}uap_trigger_log_meta WHERE meta_key LIKE %s AND automator_trigger_id = %d ORDER BY ID DESC LIMIT 0,1", "%%$meta_field%%", $trigger_id ) );
						if ( ! empty( $meta_value ) ) {
							$value = maybe_unserialize( $meta_value );
						}
					}
				}
			}
		}

		return $value;
	}
}
