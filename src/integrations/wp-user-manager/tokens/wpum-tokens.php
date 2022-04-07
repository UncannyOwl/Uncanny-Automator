<?php

namespace Uncanny_Automator;

/**
 * Class Wpum_Tokens
 * @package Uncanny_Automator
 */
class Wpum_Tokens {

	/**
	 * Wpum_Tokens constructor.
	 */
	public function __construct() {

		add_filter( 'automator_maybe_trigger_wpusermanager_wpumregform_tokens', [
			$this,
			'wpum_possible_tokens',
		], 20, 2 );

		add_filter( 'automator_maybe_trigger_wpusermanager_wpumuserpphoto_tokens', [
			$this,
			'wpum_fields_possible_tokens',
		], 20, 2 );

		add_filter( 'automator_maybe_trigger_wpusermanager_wpumuserpphotor_tokens', [
			$this,
			'wpum_fields_possible_tokens',
		], 20, 2 );

		add_filter( 'automator_maybe_trigger_wpusermanager_wpumusercover_tokens', [
			$this,
			'wpum_fields_possible_tokens',
		], 20, 2 );

		add_filter( 'automator_maybe_trigger_wpusermanager_wpumuserpcoverr_tokens', [
			$this,
			'wpum_fields_possible_tokens',
		], 20, 2 );

		add_filter( 'automator_maybe_trigger_wpusermanager_wpumuserdescription_tokens', [
			$this,
			'wpum_fields_possible_tokens',
		], 20, 2 );

		add_filter( 'automator_maybe_parse_token', [ $this, 'parse_wpum_tokens' ], 20, 6 );
	}

	/**
	 * @param array $tokens
	 * @param array $args
	 *
	 * @return array
	 */
	public function wpum_possible_tokens( $tokens = array(), $args = array() ) {
		if ( ! automator_do_identify_tokens() ) {
			return $tokens;
		}
		$form_id      = absint( $args['value'] );
		$trigger_meta = $args['meta'];

		if ( empty( $form_id ) ) {
			return $tokens;
		}

		$form = new \WPUM_Registration_Form( $form_id );

		if ( ! $form->exists() ) {
			return $tokens;
		}

		if ( $form->exists() ) {
			$fields        = array();
			$stored_fields = $form->get_fields();

			if ( is_array( $stored_fields ) && ! empty( $stored_fields ) ) {
				foreach ( $stored_fields as $field ) {
					$stored_field = new \WPUM_Field( $field );
					if ( $stored_field->exists() ) {
						$fields[] = [
							'tokenId'         => $stored_field->get_primary_id(),
							'tokenName'       => $stored_field->get_name(),
							'tokenType'       => $stored_field->get_type(),
							'tokenIdentifier' => $trigger_meta,
						];
					}
				}
			}
		}

		$tokens = array_merge( $tokens, $fields );

		return $tokens;
	}

	/**
	 * @param array $tokens
	 * @param array $args
	 *
	 * @return array
	 */
	public function wpum_profile_possible_tokens( $tokens = array(), $args = array() ) {
		if ( ! automator_do_identify_tokens() ) {
			return $tokens;
		}
		$trigger_meta = $args['meta'];

		$account_fields = WPUM()->fields->get_fields(
			[
				'group_id' => 1,
				'orderby'  => 'field_order',
				'order'    => 'ASC',
			]
		);

		foreach ( $account_fields as $field ) {

			$field = new \WPUM_Field( $field );

			if ( $field->exists() && $field->get_meta( 'editing' ) == 'public' &&
			     $field->get_primary_id() !== 'user_password' ) {

				// Skip the avatar field if disabled.
				if ( $field->get_primary_id() == 'user_avatar' && ! wpum_get_option( 'custom_avatars' ) ) {
					continue;
				}
				$fields[] = [
					'tokenId'         => $field->get_primary_id(),
					'tokenName'       => $field->get_name(),
					'tokenType'       => $field->get_type(),
					'tokenIdentifier' => $trigger_meta,
				];
			}
		}

		$tokens = array_merge( $tokens, $fields );

		return $tokens;
	}

	/**
	 * @param array $tokens
	 * @param array $args
	 *
	 * @return array
	 */
	public function wpum_fields_possible_tokens( $tokens = array(), $args = array() ) {
		if ( ! automator_do_identify_tokens() ) {
			return $tokens;
		}
		$trigger_meta = $args['meta'];

		$all_fields = WPUM()->fields->get_fields(
			[
				'group_id' => false,
				'orderby'  => 'field_order',
				'order'    => 'ASC',
			]
		);

		foreach ( $all_fields as $field ) {

			$field = new \WPUM_Field( $field );

			if ( $field->exists() && $field->get_meta( 'editing' ) == 'public' &&
			     $field->get_primary_id() !== 'user_password' ) {

				// Skip the avatar field if disabled.
				if ( $field->get_primary_id() == 'user_avatar' && ! wpum_get_option( 'custom_avatars' ) ) {
					continue;
				}
				$fields[] = [
					'tokenId'         => $field->get_primary_id(),
					'tokenName'       => $field->get_name(),
					'tokenType'       => $field->get_type(),
					'tokenIdentifier' => $trigger_meta,
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
	 * @param $user_id
	 * @param $replace_args
	 *
	 * @return mixed
	 */
	public function parse_wpum_tokens( $value, $pieces, $recipe_id, $trigger_data, $user_id, $replace_args ) {
		if ( $pieces ) {
			if ( in_array( 'WPUMREGFORM', $pieces ) || in_array( 'WPUMUSERREGISTERED', $pieces )
			     || in_array( 'WPUMUSERDESCRIPTION', $pieces ) || in_array( 'WPUMDESCRIPTIONUPDATED', $pieces )
			) {
				global $wpdb;
				$trigger_id     = $pieces[0];
				$trigger_meta   = $pieces[2];
				$trigger_log_id = isset( $replace_args['trigger_log_id'] ) ? absint( $replace_args['trigger_log_id'] ) : 0;
				$entry          = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT meta_value
FROM {$wpdb->prefix}uap_trigger_log_meta
WHERE meta_key = %s
  AND automator_trigger_log_id = %d
  AND automator_trigger_id = %d
LIMIT 0,1",
						$trigger_meta,
						$trigger_log_id,
						$trigger_id
					)
				);

				$value = maybe_unserialize( $entry );
			} elseif ( in_array( 'WPUMUSERPPHOTO', $pieces ) || in_array( 'WPUMPPUPDATED', $pieces )
			           || in_array( 'WPUMUSERPPHOTOR', $pieces ) || in_array( 'WPUMPPREMOVED', $pieces )
			           || in_array( 'WPUMUSERCOVER', $pieces ) || in_array( 'WPUMCPUPDATED', $pieces )
			           || in_array( 'WPUMUSERPCOVERR', $pieces ) || in_array( 'WPUMPCREMOVED', $pieces )
			) {
				if ( $pieces[2] == 'WPUMPPUPDATED' || $pieces[2] == 'WPUMPPREMOVED' || $pieces[2] == 'WPUMCPUPDATED' ||
				     $pieces[2] == 'WPUMPCREMOVED' ) {
					global $wpdb;
					$trigger_id     = $pieces[0];
					$trigger_meta   = $pieces[2];
					$trigger_log_id = isset( $replace_args['trigger_log_id'] ) ? absint( $replace_args['trigger_log_id'] ) : 0;
					$entry          = $wpdb->get_var(
						$wpdb->prepare(
							"SELECT meta_value
FROM {$wpdb->prefix}uap_trigger_log_meta
WHERE meta_key = %s
  AND automator_trigger_log_id = %d
  AND automator_trigger_id = %d
LIMIT 0,1",
							$trigger_meta,
							$trigger_log_id,
							$trigger_id
						)
					);

					$value = maybe_unserialize( $entry );
				} else {
					$user  = get_user_by( 'id', $user_id );
					$field = $pieces[2];

					$entry = $this->get_field_value( $user, $field );
					$value = maybe_unserialize( $entry );
				}

			}
		}

		return $value;
	}

	/**
	 * @param $user
	 * @param $field
	 *
	 * @return mixed
	 */
	function get_field_value( $user, $field ) {
		switch ( $field ) {
			case 'user_firstname':
				$value = $user->user_firstname;
				break;
			case 'user_lastname':
				$value = $user->user_lastname;
				break;
			case 'user_email':
				$value = $user->user_email;
				break;
			case 'user_nickname':
				$value = get_user_meta( $user->ID, 'nickname', true );
				break;
			case 'user_website':
				$value = $user->user_url;
				break;
			case 'user_description':
				$value = get_user_meta( $user->ID, 'description', true );
				break;
			case 'user_displayname':
				$value = $user->display_name;
				break;
			case 'user_avatar':
				$value = carbon_get_user_meta( $user->ID, 'current_user_avatar' );
				break;
			case 'user_cover':
				$value = carbon_get_user_meta( $user->ID, 'user_cover' );
				break;
			default:
				global $wpdb;
				$field_id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}wpum_fields WHERE type = %s LIMIT 0,1", $field ) );
				$value    = get_user_meta( $user->ID, WPUM()->field_meta->get_meta( $field_id, 'user_meta_key' ),
					true );
		}

		return $value;
	}

}
