<?php

namespace Uncanny_Automator\Integrations\Brevo;

use Exception;

/**
 * Class Brevo_Contact_Attributes_Helper
 *
 * Handles transposed-repeater field generation and value processing for the
 * Brevo create-or-update contact action. Bridges Brevo's raw attribute objects
 * (returned by Brevo_Api_Caller::fetch_raw_contact_attributes) and Automator's
 * repeater field shape, plus validates submitted values back to the payload
 * shape that Brevo's contacts endpoint expects.
 *
 * Modeled after Asana_Custom_Fields_Helper.
 *
 * @package Uncanny_Automator
 *
 * @link https://developers.brevo.com/reference/createcontact
 */
class Brevo_Contact_Attributes_Helper {

	/**
	 * Errors accumulated during value processing.
	 *
	 * @var array
	 */
	public $errors = array();

	/**
	 * @var Brevo_App_Helpers
	 */
	private $helpers;

	/**
	 * @var Brevo_Api_Caller
	 */
	private $api;

	/**
	 * Raw writable Brevo attribute objects, loaded on demand.
	 *
	 * @var array|null
	 */
	private $raw_attributes = null;

	/**
	 * @param Brevo_App_Helpers $helpers
	 * @param Brevo_Api_Caller  $api
	 */
	public function __construct( $helpers, $api ) {
		$this->helpers = $helpers;
		$this->api     = $api;
	}

	////////////////////////////////////////////////////////////
	// Value processing (called from process_action)
	////////////////////////////////////////////////////////////

	/**
	 * Process the submitted transposed-repeater payload into the attributes
	 * object that Brevo's create-contact endpoint expects.
	 *
	 * @param array $fields The raw repeater submission (json_decoded).
	 *
	 * @return array Map of attribute_name => value, ready for Brevo's API.
	 */
	public function process_repeater_fields( $fields ) {

		if ( empty( $fields ) ) {
			return array();
		}

		// Strip _readable / _custom siblings; resolve automator_custom_value; drop empties.
		$clean = $this->prepare_field_data( $fields );
		if ( empty( $clean ) ) {
			return array();
		}

		// Try cached attribute config first; refresh if any submitted attribute is unknown.
		$this->raw_attributes = $this->api->fetch_raw_contact_attributes( false );
		if ( $this->requires_refresh( $clean ) ) {
			$this->raw_attributes = $this->api->fetch_raw_contact_attributes( true );
		}

		return $this->process_fields( $clean );
	}

	/**
	 * @return bool True if errors were accumulated.
	 */
	public function has_errors() {
		return ! empty( $this->errors );
	}

	/**
	 * @return string Joined error message.
	 */
	public function get_error_message() {
		return implode( ', ', $this->errors );
	}

	/**
	 * Strip metadata siblings, resolve automator_custom_value, drop empty (non-DELETE) values.
	 *
	 * @param array $fields
	 *
	 * @return array
	 */
	private function prepare_field_data( $fields ) {

		$data = array();

		// Transposed repeater submits a single row of sibling attribute fields.
		$row = array_shift( $fields );
		if ( empty( $row ) || ! is_array( $row ) ) {
			return $data;
		}

		foreach ( $row as $key => $value ) {

			if ( empty( $key ) ) {
				continue;
			}

			// Skip _readable / _custom siblings.
			if ( '_readable' === substr( $key, -9 ) || '_custom' === substr( $key, -7 ) ) {
				continue;
			}

			// Resolve automator_custom_value.
			if ( $this->is_automator_custom_value( $value ) ) {
				$value = $row[ $key . '_custom' ] ?? '';
			}

			// Drop empties unless they're an explicit [DELETE] sentinel.
			if ( $this->is_effectively_empty( $value ) && ! $this->helpers->is_delete_value( $value ) ) {
				continue;
			}

			$data[ $key ] = $value;
		}

		return $data;
	}

	/**
	 * @param mixed $value
	 *
	 * @return bool
	 */
	private function is_automator_custom_value( $value ) {
		return is_array( $value )
			? in_array( 'automator_custom_value', $value, true )
			: 'automator_custom_value' === $value;
	}

	/**
	 * @param mixed $value
	 *
	 * @return bool
	 */
	private function is_effectively_empty( $value ) {
		if ( is_array( $value ) ) {
			return empty(
				array_filter(
					$value,
					function ( $v ) {
						return '' !== $v && null !== $v;
					}
				)
			);
		}
		return '' === $value || null === $value;
	}

	/**
	 * Check whether any submitted attribute is not in the cached config.
	 *
	 * @param array $fields
	 *
	 * @return bool
	 */
	private function requires_refresh( $fields ) {
		foreach ( array_keys( $fields ) as $name ) {
			if ( null === $this->find_attribute_config( $name ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Walk submitted fields and produce the Brevo-shaped attribute payload.
	 *
	 * @param array $fields Map of attribute_name => raw user value.
	 *
	 * @return array
	 */
	private function process_fields( $fields ) {

		$data = array();

		foreach ( $fields as $name => $value ) {

			$config = $this->find_attribute_config( $name );
			if ( null === $config ) {
				// Unknown attribute after a refresh — record error and skip.
				$this->add_error( $name, 'unknown', $name );
				continue;
			}

			$processed = $this->process_value_by_type( $name, $value, $config );
			if ( false === $processed ) {
				$this->add_error( $name, $config['type'] ?? 'text', $name );
				continue;
			}

			$data[ $name ] = $processed;
		}

		return $data;
	}

	/**
	 * @param string $name
	 *
	 * @return array|null
	 */
	private function find_attribute_config( $name ) {
		if ( ! is_array( $this->raw_attributes ) ) {
			return null;
		}
		foreach ( $this->raw_attributes as $attribute ) {
			if ( ( $attribute['name'] ?? '' ) === $name ) {
				return $attribute;
			}
		}
		return null;
	}

	/**
	 * @param string $name   Brevo attribute name.
	 * @param mixed  $value  Raw user value.
	 * @param array  $config Brevo attribute config.
	 *
	 * @return mixed Processed value, or false on invalid input.
	 */
	private function process_value_by_type( $name, $value, $config ) {

		// [DELETE] sentinel collapses to the type-appropriate empty value.
		if ( $this->helpers->is_delete_value( $value ) ) {
			return $this->process_delete_value( $config['type'] ?? 'text' );
		}

		// SMS is the only attribute name treated specially: strip non-numerics.
		// Brevo's SMS attribute name is stable across account languages.
		if ( 'SMS' === $name ) {
			return preg_replace( '/[^0-9]/', '', sanitize_text_field( (string) $value ) );
		}

		$type = $config['type'] ?? 'text';
		switch ( $type ) {
			case 'date':
				return $this->process_date( $value );
			case 'float':
				return $this->process_number( $value );
			case 'id':
				// id type is only on transactional attributes per Brevo's API constraints;
				// values are semantically integer identifiers (order ID, transaction ID).
				return $this->process_id( $value );
			case 'boolean':
				return $this->process_boolean( $value );
			case 'category':
				return $this->process_enum( $value, $config );
			case 'multiple-choice':
				return $this->process_multi_enum( $value, $config );
			case 'text':
			default:
				return sanitize_text_field( (string) $value );
		}
	}

	/**
	 * @param string $type Brevo attribute type.
	 *
	 * @return mixed Empty value Brevo accepts as "clear this attribute".
	 */
	private function process_delete_value( $type ) {
		// Multi-select clears via empty array; everything else clears via empty string.
		return 'multiple-choice' === $type ? array() : '';
	}

	/**
	 * @param mixed $value
	 *
	 * @return string|false ISO date (Y-m-d) or false on invalid input.
	 */
	private function process_date( $value ) {
		$date = date_create( (string) $value );
		return $date ? date_format( $date, 'Y-m-d' ) : false;
	}

	/**
	 * @param mixed $value
	 *
	 * @return float|false
	 */
	private function process_number( $value ) {
		$num = filter_var( $value, FILTER_VALIDATE_FLOAT );
		return false === $num ? false : $num;
	}

	/**
	 * @param mixed $value
	 *
	 * @return int|false
	 */
	private function process_id( $value ) {
		$num = filter_var( $value, FILTER_VALIDATE_INT );
		return false === $num ? false : $num;
	}

	/**
	 * @param mixed $value
	 *
	 * @return bool|false False on invalid input.
	 */
	private function process_boolean( $value ) {
		$v = strtolower( sanitize_text_field( (string) $value ) );
		if ( in_array( $v, array( '1', 'true', 'yes' ), true ) ) {
			return true;
		}
		if ( in_array( $v, array( '0', 'false', 'no' ), true ) ) {
			return false;
		}
		return false;
	}

	/**
	 * Match a single-select value back to a Brevo enumeration entry.
	 *
	 * Returns valueStr (the canonical string form added in Brevo's May 2026
	 * schema) when available; otherwise the legacy numeric value. Brevo's
	 * create-contact endpoint accepts either form for category attributes.
	 *
	 * @param mixed $value
	 * @param array $config
	 *
	 * @return string|int|false Brevo's option value, or false if unmatched.
	 */
	private function process_enum( $value, $config ) {
		$options = $config['enumeration'] ?? array();
		$value   = sanitize_text_field( (string) $value );

		// Match by valueStr first (what the dropdown now submits since the schema change).
		foreach ( $options as $opt ) {
			if ( isset( $opt['valueStr'] ) && (string) $opt['valueStr'] === $value ) {
				return $opt['valueStr'];
			}
		}
		// Fall back to matching the numeric value (older accounts / pre-May-2026 schema).
		foreach ( $options as $opt ) {
			if ( (string) ( $opt['value'] ?? '' ) === $value ) {
				return $opt['valueStr'] ?? $opt['value'];
			}
		}
		// Defensive: match by label (case-insensitive) for token-resolved labels.
		$value_lower = strtolower( $value );
		foreach ( $options as $opt ) {
			if ( strtolower( (string) ( $opt['label'] ?? '' ) ) === $value_lower ) {
				return $opt['valueStr'] ?? $opt['value'];
			}
		}
		return false;
	}

	/**
	 * Match an array (or comma-separated string) back to Brevo's multi-choice options.
	 *
	 * Brevo's multiCategoryOptions is a flat array of label strings — the label
	 * IS the value submitted back to the API. Match case-insensitively but
	 * return the option's canonical casing so Brevo accepts it.
	 *
	 * @param mixed $value
	 * @param array $config
	 *
	 * @return array|false Array of matched option strings; false on validation failure.
	 */
	private function process_multi_enum( $value, $config ) {

		$values  = is_array( $value )
			? $value
			: array_map( 'trim', explode( ',', (string) $value ) );
		$options = array_map( 'strval', $config['multiCategoryOptions'] ?? array() );

		$result = array();
		foreach ( $values as $v ) {
			$v = sanitize_text_field( (string) $v );
			if ( '' === $v ) {
				continue;
			}
			$matched = false;
			foreach ( $options as $opt ) {
				if ( strcasecmp( $opt, $v ) === 0 ) {
					$result[] = $opt;
					$matched  = true;
					break;
				}
			}
			if ( ! $matched ) {
				return false;
			}
		}

		return $result;
	}

	/**
	 * @param string $value
	 * @param string $type
	 * @param string $name
	 *
	 * @return void
	 */
	private function add_error( $value, $type, $name ) {
		$this->errors[] = sprintf(
			// translators: %1$s: attribute type, %2$s: attribute name
			esc_html_x( 'Invalid %1$s value for attribute %2$s', 'Brevo', 'uncanny-automator' ),
			esc_html( $type ),
			esc_html( $name )
		);
	}

	////////////////////////////////////////////////////////////
	// Transposed repeater field generation (called from
	// Brevo_App_Helpers::remote_data_get_contact_attributes)
	////////////////////////////////////////////////////////////

	/**
	 * Build the Automator-shaped field array for the transposed repeater.
	 *
	 * @param array             $raw_attributes Output of Brevo_Api_Caller::fetch_raw_contact_attributes().
	 * @param Brevo_App_Helpers $helpers
	 *
	 * @return array
	 */
	public static function generate_repeater_fields( $raw_attributes, $helpers ) {

		$fields = array();

		if ( empty( $raw_attributes ) || ! is_array( $raw_attributes ) ) {
			return $fields;
		}

		foreach ( $raw_attributes as $attribute ) {
			$field = self::build_field_config( $attribute, $helpers );
			if ( null !== $field ) {
				$fields[] = $field;
			}
		}

		return $fields;
	}

	/**
	 * @param array             $attribute Single Brevo attribute object.
	 * @param Brevo_App_Helpers $helpers
	 *
	 * @return array|null
	 */
	private static function build_field_config( $attribute, $helpers ) {

		$name = $attribute['name'] ?? '';
		if ( empty( $name ) ) {
			return null;
		}

		// Transactional attributes don't fit this action's "set value per contact"
		// flow — they're a collection of per-transaction records keyed by an
		// id-type primary key, and Brevo displays them in a separate Transactions
		// tab on the contact profile. TODO: a dedicated "Record a contact
		// transaction" action will surface these via the same helper + opposite
		// filter.
		if ( 'transactional' === ( $attribute['category'] ?? '' ) ) {
			return null;
		}

		$automator_type = self::convert_brevo_type_to_automator( $attribute );

		$config = array(
			'option_code'     => $name,
			'label'           => $name,
			'input_type'      => $automator_type,
			'supports_tokens' => true,
			'required'        => false,
		);

		if ( 'select' === $automator_type ) {
			$config = self::apply_select_config( $config, $attribute, $helpers );
		}

		return $config;
	}

	/**
	 * Map a Brevo attribute type to the Automator input_type. Per Asana
	 * convention, checkbox-style booleans and category enums both become
	 * `select` for clean UX.
	 *
	 * @param array $attribute
	 *
	 * @return string
	 */
	private static function convert_brevo_type_to_automator( $attribute ) {
		switch ( $attribute['type'] ?? '' ) {
			case 'boolean':
			case 'category':
			case 'multiple-choice':
				return 'select';
			case 'date':
				return 'date';
			case 'float':
			case 'id':
				// Float in repeaters has known display issues — use text + token support.
				return 'text';
			case 'text':
			default:
				return 'text';
		}
	}

	/**
	 * Populate options + DELETE/empty defaults on a select field.
	 *
	 * @param array             $config
	 * @param array             $attribute
	 * @param Brevo_App_Helpers $helpers
	 *
	 * @return array
	 */
	private static function apply_select_config( $config, $attribute, $helpers ) {

		$brevo_type  = $attribute['type'] ?? '';
		$is_multiple = 'multiple-choice' === $brevo_type;

		if ( 'boolean' === $brevo_type ) {
			$options = array(
				array(
					'value' => 'yes',
					'text'  => esc_html_x( 'Yes', 'Brevo', 'uncanny-automator' ),
				),
				array(
					'value' => 'no',
					'text'  => esc_html_x( 'No', 'Brevo', 'uncanny-automator' ),
				),
			);
		} elseif ( $is_multiple ) {
			// multiCategoryOptions is a FLAT ARRAY OF STRINGS — each string is both label and value.
			$options = array();
			foreach ( $attribute['multiCategoryOptions'] ?? array() as $opt ) {
				$label     = (string) $opt;
				$options[] = array(
					'value' => $label,
					'text'  => $label,
				);
			}
		} else {
			// enumeration is an array of { value: int, valueStr: string, label: string }.
			// Brevo's May 2026 schema made valueStr required; it's the canonical string
			// form (e.g. "en" for language enums) and is the safest value to round-trip
			// back into the API. Fall back to value for older API responses.
			$options = array();
			foreach ( $attribute['enumeration'] ?? array() as $opt ) {
				$value     = $opt['valueStr'] ?? (string) ( $opt['value'] ?? '' );
				$label     = $opt['label'] ?? $value;
				$options[] = array(
					'value' => $value,
					'text'  => $label,
				);
			}
		}

		// Single-select: prepend the "ignore" empty default.
		if ( ! $is_multiple ) {
			$options                 = $helpers->prepend_empty_option( $options );
			$config['default_value'] = '';
		}

		// Multi-select: allow custom values + multi mode.
		if ( $is_multiple ) {
			$config['supports_custom_value']    = true;
			$config['supports_multiple_values'] = true;
			$config['placeholder']              = esc_html_x( 'Select options', 'Brevo', 'uncanny-automator' );
		}

		// Append [DELETE] sentinel — applies to both single and multi.
		$config['options'] = $helpers->append_delete_option( $options );

		return $config;
	}
}
