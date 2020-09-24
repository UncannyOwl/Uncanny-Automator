<?php

namespace Uncanny_Automator;

/**
 * Class Bp_Tokens
 *
 * @package Uncanny_Automator
 */
class Bp_Tokens {


	/**
	 * Integration code
	 *
	 * @var string
	 */
	public static $integration = 'BP';

	public function __construct() {
		add_filter( 'automator_maybe_trigger_bp_tokens', [ $this, 'bp_possible_tokens' ], 20, 2 );
		add_filter( 'automator_maybe_parse_token', [ $this, 'parse_bp_token' ], 20, 6 );

	}

	/**
	 * Only load this integration and its triggers and actions if the related
	 * plugin is active
	 *
	 * @param $status
	 * @param $code
	 *
	 * @return bool
	 */
	public function plugin_active( $status, $code ) {

		if ( self::$integration === $code ) {
			if ( function_exists( 'buddypress' ) && isset( buddypress()->buddyboss ) && buddypress()->buddyboss ) {
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
	public function bp_possible_tokens( $tokens = [], $args = [] ) {
		$trigger_integration = $args['integration'];
		$trigger_meta        = $args['meta'];

		$fields = [
			[
				'tokenId'         => 'BPUSER',
				'tokenName'       => 'AVATAR URL',
				'tokenType'       => 'text',
				'tokenIdentifier' => 'BPUSERAVATAR',
			],
		];
		// Get BP xprofile fields from DB.
		global $wpdb;
		$fields_table    = $wpdb->prefix . "bp_xprofile_fields";
		$xprofile_fields = $wpdb->get_results( "SELECT * FROM {$fields_table} ORDER BY field_order ASC" );

		if ( ! empty( $xprofile_fields ) ) {
			foreach ( $xprofile_fields as $field ) {
				$fields[] = [
					'tokenId'         => 'BPUSER',
					'tokenName'       => $field->name,
					'tokenType'       => 'text',
					'tokenIdentifier' => 'BPXPROFILE:' . $field->id,
				];
			}
		}

		$tokens = array_merge( $tokens, $fields );

		return $tokens;
	}
	/**
	 * @param $value
	 * @param $pieces
	 * @param $recipe_id
	 * @param $trigger_data
	 *
	 * @return mixed
	 */
	public function parse_bp_token( $value, $pieces, $recipe_id, $trigger_data, $user_id, $replace_args ) {
		if ( $pieces ) {
			if ( in_array( 'BPUSERAVATAR', $pieces ) ) {
				// Get Group id from meta log
				if ( function_exists( 'get_avatar_url' ) ) {
					$value = get_avatar_url( $user_id );
				}
			} elseif ( in_array( 'BPXPROFILE', $pieces ) ) {

				if ( isset( $pieces[2] ) && ! empty( $pieces[2] ) ) {
					$value = $this->get_xprofile_data( $user_id, intval( $pieces[2] ) );
				}
			}
		}

		return $value;
	}

	/**
	 * @param $user_id
	 * @param $field_id
	 * @return mixed|string
	 */
	public function get_xprofile_data( $user_id, $field_id ) {
		global $wpdb;
		if ( empty( $field_id ) ) {
			return '';
		}

		$meta_value = $wpdb->get_var( $wpdb->prepare( "SELECT value FROM {$wpdb->prefix}bp_xprofile_data WHERE user_id = %d AND field_id = %s LIMIT 0,1", $user_id, $field_id ) );
		if ( ! empty( $meta_value ) ) {
			return maybe_unserialize( $meta_value );
		}

		return '';
	}
}