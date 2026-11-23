<?php

namespace Uncanny_Automator\Integrations\Userswp;

use DateTime;

/**
 * Trait Userswp_Field_Rules
 *
 * What a value must look like for each UsersWP field type: a value the
 * plugin's own account form can submit, converted to what that form passes to
 * `uwp_update_usermeta()`.
 *
 * @package Uncanny_Automator\Integrations\Userswp
 */
trait Userswp_Field_Rules {

	/**
	 * Field types of the standard columns that have no form-builder row.
	 *
	 * @var array<string,string>
	 */
	private $standard_field_types = array(
		'user_url' => 'url',
		'bio'      => 'textarea',
	);

	/**
	 * Validate a value for a profile field and convert it for the write.
	 *
	 * Returns `array( 'value' => mixed, 'error' => string, 'cleared' => bool )`;
	 * `error` is '' when the value is valid, and `cleared` is true for
	 * `[DELETE]`. The caller skips a blank value: it keeps the field.
	 *
	 * @param string $key
	 * @param mixed  $value
	 * @param int    $user_id The user whose form gives the field's rules.
	 *
	 * @return array
	 */
	public function normalise_for_write( $key, $value, $user_id = 0 ) {

		$field = $this->field_definition( $key, $user_id );
		$type  = is_object( $field ) ? (string) $field->field_type : ( $this->standard_field_types[ $key ] ?? 'text' );
		$value = is_scalar( $value ) ? (string) $value : '';

		// Case-sensitive, as every other "[DELETE]" value in Automator.
		if ( '[DELETE]' === trim( $value ) ) {
			return $this->cleared_value( $field, $type );
		}

		return $this->normalise_by_type( $type, $field, $value );
	}

	/**
	 * The empty value UsersWP's account form saves for a field: its hidden 0
	 * for a checkbox, '' for every other type. A required field refuses it, as
	 * UsersWP's validator refuses an empty required field.
	 *
	 * @param object|null $field
	 * @param string      $type
	 *
	 * @return array
	 */
	private function cleared_value( $field, $type ) {

		if ( is_object( $field ) && '1' === (string) $field->is_required ) {
			return $this->rule_error( esc_html_x( 'The field is required, so it cannot be cleared.', 'UsersWP', 'uncanny-automator' ) );
		}

		$result            = $this->rule_value( 'checkbox' === $type ? '0' : '' );
		$result['cleared'] = true;

		return $result;
	}

	/**
	 * The option values of a select, radio or multi-select field.
	 *
	 * @param object|null $field
	 *
	 * @return string[]
	 */
	public function field_option_values( $field ) {

		if ( ! is_object( $field ) || ! function_exists( 'uwp_string_values_to_options' ) ) {
			return array();
		}

		$values = array();

		foreach ( (array) uwp_string_values_to_options( (string) $field->option_values ) as $option ) {
			// Optgroup markers carry a null value.
			if ( is_array( $option ) && isset( $option['value'] ) ) {
				$values[] = (string) $option['value'];
			}
		}

		return $values;
	}

	/**
	 * The PHP date format a datepicker field shows its dates in, or '' when
	 * the field sets none. A long, old-style jQuery UI format is translated as
	 * UsersWP translates it.
	 *
	 * @param object|null $field
	 *
	 * @return string
	 */
	public function field_date_format( $field ) {

		if ( ! is_object( $field ) || empty( $field->extra_fields ) ) {
			return '';
		}

		$extra  = automator_safe_unserialize( $field->extra_fields );
		$format = is_array( $extra ) && isset( $extra['date_format'] ) ? trim( (string) $extra['date_format'] ) : '';

		if ( '' === $format || strlen( str_replace( ' ', '', $format ) ) <= 5 ) {
			return $format;
		}

		return str_replace(
			array( 'dd', 'd', 'DD', 'mm', 'm', 'MM', 'yy' ),
			array( 'd', 'j', 'l', 'm', 'n', 'F', 'Y' ),
			$format
		);
	}

	/**
	 * @param string      $type
	 * @param object|null $field
	 * @param string      $value
	 *
	 * @return array
	 */
	private function normalise_by_type( $type, $field, $value ) {

		// The account form posts an unticked checkbox as 0.
		if ( 'checkbox' === $type && '' === trim( $value ) ) {
			return $this->rule_value( '0' );
		}

		if ( '' === trim( $value ) ) {
			return $this->rule_value( '' );
		}

		switch ( $type ) {
			case 'select':
			case 'radio':
				return $this->normalise_select( $field, $value );
			case 'multiselect':
				return $this->normalise_options( $field, $value );
			case 'checkbox':
				return $this->normalise_checkbox( $value );
			case 'datepicker':
				return $this->normalise_date( $field, $value );
			case 'time':
				return $this->normalise_time( $value );
			case 'email':
				return $this->normalise_email( $value );
			case 'url':
				return $this->normalise_url( $value );
			case 'textarea':
				return $this->rule_value( sanitize_textarea_field( $value ) );
			case 'editor':
				return $this->rule_value( wp_kses_post( strip_shortcodes( $value ) ) );
		}

		return $this->normalise_text( $field, $value );
	}

	/**
	 * Any text, except a text field whose Data Type is NUMBER (`INT`) or
	 * DECIMAL (`FLOAT`): the account form renders those as a number input, a
	 * whole number for `INT`.
	 *
	 * @param object|null $field
	 * @param string      $value
	 *
	 * @return array
	 */
	private function normalise_text( $field, $value ) {

		$data_type = is_object( $field ) && isset( $field->data_type ) ? strtoupper( (string) $field->data_type ) : '';
		$number    = trim( $value );

		if ( 'INT' === $data_type && 1 !== preg_match( '/^[-+]?\d+$/', $number ) ) {
			return $this->rule_error(
				sprintf(
					/* translators: %s: The value */
					esc_html_x( '"%s" is not a whole number.', 'UsersWP', 'uncanny-automator' ),
					sanitize_text_field( $value )
				)
			);
		}

		if ( 'FLOAT' === $data_type && ! is_numeric( $number ) ) {
			return $this->rule_error(
				sprintf(
					/* translators: %s: The value */
					esc_html_x( '"%s" is not a number.', 'UsersWP', 'uncanny-automator' ),
					sanitize_text_field( $value )
				)
			);
		}

		return $this->rule_value( in_array( $data_type, array( 'INT', 'FLOAT' ), true ) ? $number : sanitize_text_field( $value ) );
	}

	/**
	 * A select or radio value. UsersWP's predefined Country and Language
	 * selects carry no option values, because the account form builds their
	 * options itself, so theirs come from the same sources.
	 *
	 * @param object|null $field
	 * @param string      $value
	 *
	 * @return array
	 */
	private function normalise_select( $field, $value ) {

		$type_key = is_object( $field ) && isset( $field->field_type_key ) ? (string) $field->field_type_key : '';
		$key      = is_object( $field ) ? (string) $field->htmlvar_name : '';

		if ( in_array( $type_key, array( 'country', 'uwp_country' ), true ) ) {
			return $this->normalise_country( $value );
		}

		if ( 'uwp_language' === $key || 'uwp_language' === $type_key ) {
			return $this->normalise_language( $value );
		}

		return $this->normalise_option( $field, $value );
	}

	/**
	 * A country code, one of the keys of `uwp_get_country_data()`, which the
	 * account form's country input posts.
	 *
	 * @param string $value
	 *
	 * @return array
	 */
	private function normalise_country( $value ) {

		$code      = strtolower( sanitize_text_field( $value ) );
		$countries = function_exists( 'uwp_get_country_data' ) ? (array) uwp_get_country_data() : array();

		if ( '' === $code || ! array_key_exists( $code, $countries ) ) {
			return $this->rule_error(
				sprintf(
					/* translators: %s: The value */
					esc_html_x( '"%s" is not a country code UsersWP knows, such as us or gb.', 'UsersWP', 'uncanny-automator' ),
					sanitize_text_field( $value )
				)
			);
		}

		return $this->rule_value( $code );
	}

	/**
	 * `site-default` or an installed locale, the options of the account form's
	 * language select.
	 *
	 * @param string $value
	 *
	 * @return array
	 */
	private function normalise_language( $value ) {

		$locale  = sanitize_text_field( trim( $value ) );
		$allowed = array_merge( array( 'site-default' ), get_available_languages() );

		if ( ! in_array( $locale, $allowed, true ) ) {
			return $this->rule_error(
				sprintf(
					/* translators: 1: The value, 2: The accepted values */
					esc_html_x( '"%1$s" is not a language this site has. Use one of: %2$s.', 'UsersWP', 'uncanny-automator' ),
					$locale,
					implode( ', ', $allowed )
				)
			);
		}

		return $this->rule_value( $locale );
	}

	/**
	 * @param object $field
	 * @param string $value
	 *
	 * @return array
	 */
	private function normalise_option( $field, $value ) {

		$value = sanitize_text_field( $value );

		if ( ! in_array( $value, $this->field_option_values( $field ), true ) ) {
			return $this->not_an_option( $value );
		}

		return $this->rule_value( $value );
	}

	/**
	 * A comma-separated list of options, passed on as an array as the account
	 * form posts it.
	 *
	 * @param object $field
	 * @param string $value
	 *
	 * @return array
	 */
	private function normalise_options( $field, $value ) {

		$options = $this->field_option_values( $field );
		$picked  = array();

		foreach ( explode( ',', $value ) as $part ) {

			$part = sanitize_text_field( $part );

			if ( '' === $part ) {
				continue;
			}

			if ( ! in_array( $part, $options, true ) ) {
				return $this->not_an_option( $part );
			}

			$picked[] = $part;
		}

		return $this->rule_value( array_values( array_unique( $picked ) ) );
	}

	/**
	 * @param string $value
	 *
	 * @return array
	 */
	private function not_an_option( $value ) {
		return $this->rule_error(
			sprintf(
				/* translators: %s: The value */
				esc_html_x( '"%s" is not one of the options of this field.', 'UsersWP', 'uncanny-automator' ),
				$value
			)
		);
	}

	/**
	 * @param string $value
	 *
	 * @return array
	 */
	private function normalise_checkbox( $value ) {

		$value = trim( $value );

		if ( '1' !== $value && '0' !== $value ) {
			return $this->rule_error( esc_html_x( 'A checkbox takes 1 or 0.', 'UsersWP', 'uncanny-automator' ) );
		}

		return $this->rule_value( $value );
	}

	/**
	 * A date as the account form posts it: YYYY-MM-DD from its date picker, or
	 * the field's own format. Passed on as a timestamp, which UsersWP's update
	 * filter stores as YYYY-MM-DD.
	 *
	 * @param object $field
	 * @param string $value
	 *
	 * @return array
	 */
	private function normalise_date( $field, $value ) {

		$value = sanitize_text_field( $value );
		$date  = $this->parse_field_date( $field, $value );

		if ( '' === $date ) {
			$format = $this->field_date_format( $field );

			return $this->rule_error(
				sprintf(
					/* translators: 1: The value, 2: A date format */
					esc_html_x( '"%1$s" is not a date. Use YYYY-MM-DD or the date format of the field (%2$s).', 'UsersWP', 'uncanny-automator' ),
					$value,
					'' === $format ? 'Y-m-d' : $format
				)
			);
		}

		return $this->rule_value( (int) strtotime( $date ) );
	}

	/**
	 * A date in YYYY-MM-DD or a datepicker field's own format, as YYYY-MM-DD,
	 * the form UsersWP stores; '' when the value is neither.
	 *
	 * @param object|null $field
	 * @param string      $value
	 *
	 * @return string
	 */
	public function parse_field_date( $field, $value ) {

		$value  = trim( (string) $value );
		$format = $this->field_date_format( $field );
		$date   = $this->parse_date( 'Y-m-d', $value );

		if ( null === $date && '' !== $format ) {
			$date = $this->parse_date( $format, $value );
		}

		return null === $date ? '' : $date->format( 'Y-m-d' );
	}

	/**
	 * A date parsed strictly in one format, or null.
	 *
	 * @param string $format
	 * @param string $value
	 *
	 * @return DateTime|null
	 */
	private function parse_date( $format, $value ) {

		$date   = DateTime::createFromFormat( '!' . $format, $value );
		$errors = DateTime::getLastErrors();

		if ( false === $date ) {
			return null;
		}

		if ( is_array( $errors ) && ( ! empty( $errors['warning_count'] ) || ! empty( $errors['error_count'] ) ) ) {
			return null;
		}

		return $date;
	}

	/**
	 * @param string $value
	 *
	 * @return array
	 */
	private function normalise_time( $value ) {

		$value = trim( $value );

		if ( 1 !== preg_match( '/^([01]\d|2[0-3]):[0-5]\d$/', $value ) ) {
			return $this->rule_error(
				sprintf(
					/* translators: %s: The value */
					esc_html_x( '"%s" is not a time. Use HH:MM, 24-hour.', 'UsersWP', 'uncanny-automator' ),
					$value
				)
			);
		}

		return $this->rule_value( $value );
	}

	/**
	 * @param string $value
	 *
	 * @return array
	 */
	private function normalise_email( $value ) {

		$email = sanitize_email( $value );

		if ( ! is_email( $email ) ) {
			return $this->rule_error(
				sprintf(
					/* translators: %s: The value */
					esc_html_x( '"%s" is not an email address.', 'UsersWP', 'uncanny-automator' ),
					$value
				)
			);
		}

		return $this->rule_value( $email );
	}

	/**
	 * @param string $value
	 *
	 * @return array
	 */
	private function normalise_url( $value ) {

		$url = esc_url_raw( trim( $value ) );

		if ( '' === $url ) {
			return $this->rule_error(
				sprintf(
					/* translators: %s: The value */
					esc_html_x( '"%s" is not a URL.', 'UsersWP', 'uncanny-automator' ),
					$value
				)
			);
		}

		return $this->rule_value( $url );
	}

	/**
	 * @param mixed $value
	 *
	 * @return array
	 */
	private function rule_value( $value ) {
		return array(
			'value'   => $value,
			'error'   => '',
			'cleared' => false,
		);
	}

	/**
	 * @param string $error
	 *
	 * @return array
	 */
	private function rule_error( $error ) {
		return array(
			'value'   => null,
			'error'   => $error,
			'cleared' => false,
		);
	}
}
