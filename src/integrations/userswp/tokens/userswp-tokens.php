<?php

namespace Uncanny_Automator\Integrations\Userswp;

use WP_User;

/**
 * Class Userswp_Tokens
 *
 * The token groups the UsersWP triggers share: the fixed user set every
 * trigger carries, and one token per readable profile field on "A user
 * registers" and "A user updates their profile".
 *
 * Field tokens are read through `uwp_get_usermeta()`, never from a hook's
 * posted form, which can carry the password.
 *
 * @package Uncanny_Automator\Integrations\Userswp
 */
class Userswp_Tokens {

	/**
	 * @var Userswp_Helpers
	 */
	private $helpers;

	/**
	 * @param Userswp_Helpers $helpers
	 */
	public function __construct( $helpers ) {
		$this->helpers = $helpers;
	}

	/**
	 * Trigger token definition.
	 *
	 * @param string $id
	 * @param string $name
	 * @param string $type
	 *
	 * @return array
	 */
	public function token_def( $id, $name, $type ) {
		return array(
			'tokenId'   => $id,
			'tokenName' => $name,
			'tokenType' => $type,
		);
	}

	/**
	 * Map every token id in a definition list to an empty string.
	 *
	 * @param array $definitions
	 *
	 * @return array
	 */
	public function empty_keyset( $definitions ) {

		$keyset = array();

		foreach ( $definitions as $definition ) {
			$keyset[ $definition['tokenId'] ] = '';
		}

		return $keyset;
	}

	/* -------------------------------------------------------------------------
	 * The user set.
	 * ---------------------------------------------------------------------- */

	/**
	 * The user token set every UsersWP trigger carries.
	 *
	 * @return array
	 */
	public function user_token_definitions() {
		return array(
			$this->token_def( 'UWP_USER_ID', esc_html_x( 'User ID', 'UsersWP', 'uncanny-automator' ), 'int' ),
			$this->token_def( 'UWP_USERNAME', esc_html_x( 'Username', 'UsersWP', 'uncanny-automator' ), 'text' ),
			$this->token_def( 'UWP_EMAIL', esc_html_x( 'Email', 'UsersWP', 'uncanny-automator' ), 'email' ),
			$this->token_def( 'UWP_FIRST_NAME', esc_html_x( 'First name', 'UsersWP', 'uncanny-automator' ), 'text' ),
			$this->token_def( 'UWP_LAST_NAME', esc_html_x( 'Last name', 'UsersWP', 'uncanny-automator' ), 'text' ),
			$this->token_def( 'UWP_DISPLAY_NAME', esc_html_x( 'Display name', 'UsersWP', 'uncanny-automator' ), 'text' ),
			$this->token_def( 'UWP_USER_URL', esc_html_x( 'Website', 'UsersWP', 'uncanny-automator' ), 'url' ),
			$this->token_def( 'UWP_BIO', esc_html_x( 'Bio', 'UsersWP', 'uncanny-automator' ), 'text' ),
			$this->token_def( 'UWP_AVATAR', esc_html_x( 'Profile photo', 'UsersWP', 'uncanny-automator' ), 'text' ),
			$this->token_def( 'UWP_BANNER', esc_html_x( 'Profile banner', 'UsersWP', 'uncanny-automator' ), 'text' ),
			$this->token_def( 'UWP_REGISTER_FORM_ID', esc_html_x( 'Registration form ID', 'UsersWP', 'uncanny-automator' ), 'int' ),
		);
	}

	/**
	 * The user token values for a live user.
	 *
	 * @param int $user_id
	 *
	 * @return array
	 */
	public function user_token_values( $user_id ) {

		$user_id = absint( $user_id );
		$user    = get_userdata( $user_id );

		if ( ! $user instanceof WP_User ) {
			return $this->empty_keyset( $this->user_token_definitions() );
		}

		return array(
			'UWP_USER_ID'          => $user_id,
			'UWP_USERNAME'         => (string) $user->user_login,
			'UWP_EMAIL'            => (string) $user->user_email,
			'UWP_FIRST_NAME'       => $this->profile_value( $user_id, 'first_name', $user->first_name ),
			'UWP_LAST_NAME'        => $this->profile_value( $user_id, 'last_name', $user->last_name ),
			'UWP_DISPLAY_NAME'     => $this->profile_value( $user_id, 'display_name', $user->display_name ),
			'UWP_USER_URL'         => $this->profile_value( $user_id, 'user_url', $user->user_url ),
			'UWP_BIO'              => $this->profile_value( $user_id, 'bio', $user->description ),
			'UWP_AVATAR'           => $this->helpers->scalarise( $this->helpers->get_meta( $user_id, 'avatar_thumb' ) ),
			'UWP_BANNER'           => $this->helpers->scalarise( $this->helpers->get_meta( $user_id, 'banner_thumb' ) ),
			'UWP_REGISTER_FORM_ID' => absint( get_user_meta( $user_id, '_uwp_register_form_id', true ) ),
		);
	}

	/**
	 * A profile value as UsersWP shows it, else the WP user record.
	 *
	 * An account created by another plugin has only the WP record, and the
	 * profile the user sees is rendered from the UsersWP side, so prefer that
	 * and fall back rather than report an empty token.
	 *
	 * @param int    $user_id
	 * @param string $key
	 * @param mixed  $fallback
	 *
	 * @return string
	 */
	private function profile_value( $user_id, $key, $fallback ) {

		$value = $this->helpers->scalarise( $this->helpers->get_meta( $user_id, $key ) );

		return '' !== $value ? $value : $this->helpers->scalarise( $fallback );
	}

	/* -------------------------------------------------------------------------
	 * One token per readable profile field.
	 * ---------------------------------------------------------------------- */

	/**
	 * One token per field of the readable list, id `UWP_FIELD_{key}`.
	 *
	 * @return array
	 */
	public function field_token_definitions() {

		$definitions = array();

		foreach ( $this->helpers->readable_field_options() as $option ) {

			$id = $this->field_token_id( $option['value'] );

			if ( isset( $definitions[ $id ] ) ) {
				continue;
			}

			$definitions[ $id ] = $this->token_def(
				$id,
				sprintf(
					/* translators: %s: The profile field label */
					esc_html_x( 'Profile field: %s', 'UsersWP', 'uncanny-automator' ),
					$option['text']
				),
				$this->field_token_type( $option['value'] )
			);
		}

		return array_values( $definitions );
	}

	/**
	 * A field token's type: a date is formatted for display, so it is text.
	 *
	 * @param string $key
	 *
	 * @return string
	 */
	private function field_token_type( $key ) {

		$field = $this->helpers->field_definition( $key );
		$type  = is_object( $field ) ? (string) $field->field_type : '';

		if ( 'email' === $key || 'email' === $type ) {
			return 'email';
		}

		if ( 'user_url' === $key || 'url' === $type ) {
			return 'url';
		}

		return 'text';
	}

	/**
	 * The field tokens' values, the full keyset every time (R23).
	 *
	 * @param int $user_id
	 *
	 * @return array
	 */
	public function field_token_values( $user_id ) {

		$user_id = absint( $user_id );
		$values  = $this->empty_keyset( $this->field_token_definitions() );

		if ( 0 === $user_id || ! get_userdata( $user_id ) instanceof WP_User ) {
			return $values;
		}

		foreach ( $this->helpers->readable_field_options() as $option ) {

			$key = $option['value'];
			$id  = $this->field_token_id( $key );

			if ( '' !== $values[ $id ] ) {
				continue;
			}

			$values[ $id ] = $this->format_field_value( $key, $this->helpers->get_meta( $user_id, $key ) );
		}

		return $values;
	}

	/**
	 * A field value as UsersWP's profile shows it: a date in the field's own
	 * date format (else the site's), a multi-select joined with ", ".
	 *
	 * @param string $key
	 * @param mixed  $value
	 *
	 * @return string
	 */
	public function format_field_value( $key, $value ) {

		if ( '0000-00-00' === $value || false === $value || null === $value ) {
			return '';
		}

		$field = $this->helpers->field_definition( $key );

		if ( is_object( $field ) && 'datepicker' === (string) $field->field_type ) {
			return $this->format_date( $field, $value );
		}

		return $this->helpers->scalarise( $value );
	}

	/**
	 * A datepicker value, which `uwp_get_usermeta()` reads back as a Unix
	 * timestamp.
	 *
	 * @param object $field
	 * @param mixed  $value
	 *
	 * @return string
	 */
	private function format_date( $field, $value ) {

		if ( is_string( $value ) && '' !== $value && ! is_numeric( $value ) ) {
			$value = strtotime( $value );
		}

		if ( ! is_numeric( $value ) || 0 >= (int) $value ) {
			return '';
		}

		$format = $this->helpers->field_date_format( $field );

		if ( '' === $format ) {
			$format = (string) get_option( 'date_format', 'Y-m-d' );
		}

		return gmdate( $format, (int) $value );
	}

	/**
	 * Token id for a field key.
	 *
	 * @param string $key
	 *
	 * @return string
	 */
	public function field_token_id( $key ) {
		return 'UWP_FIELD_' . sanitize_key( (string) $key );
	}
}
