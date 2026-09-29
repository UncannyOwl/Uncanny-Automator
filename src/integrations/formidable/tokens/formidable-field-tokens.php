<?php

namespace Uncanny_Automator\Integrations\Formidable;

/**
 * Builds, parses and resolves the field tokens of a Formidable form.
 *
 * Formidable stores a repeater's rows as entries of a child form, so the values
 * of a field inside a repeater never appear on the parent entry. A token for
 * such a field therefore carries the section it belongs to, and resolving it
 * means reading the child entries rather than the parent.
 *
 * Every method here takes and returns plain data. Fetching the form's fields and
 * the entry's metas belongs to the caller, which keeps this logic independent of
 * Formidable and of the request that created the entry — so it is exercised
 * without the plugin installed.
 *
 * @package Uncanny_Automator
 */
class Formidable_Field_Tokens {

	/**
	 * Sub-fields a Formidable name field is made of.
	 *
	 * @var string[]
	 */
	const NAME_PARTS = array( 'first', 'middle', 'last' );

	/**
	 * Field types that shape the form rather than collect a value.
	 *
	 * @var string[]
	 */
	const STRUCTURAL_TYPES = array( 'end_divider', 'captcha', 'break', 'html', 'form', 'summary', 'submit' );

	/**
	 * Marks the repeater section a field belongs to inside a token.
	 *
	 * @var string
	 */
	const SECTION_SEPARATOR = '-repeater-';

	/**
	 * Field type opening a section, repeating when the field repeats.
	 *
	 * @var string
	 */
	const SECTION_OPEN = 'divider';

	/**
	 * Field type closing a section.
	 *
	 * @var string
	 */
	const SECTION_CLOSE = 'end_divider';

	/**
	 * Field type holding a name split into parts.
	 *
	 * @var string
	 */
	const NAME_TYPE = 'name';

	/**
	 * Build the token list of a form.
	 *
	 * Fields arrive in the order Formidable renders them, which places the
	 * fields of a repeater between the section that opens it and the one that
	 * closes it. Each descriptor carries `id`, `type`, `label`, `is_repeating`
	 * and, for a name field, the `name_parts` that have a label.
	 *
	 * Every token declares the trigger meta as its `tokenIdentifier`, which is
	 * the `meta_key` Formidable's field values have always been stored under.
	 *
	 * @param string $form_id      Form the tokens belong to.
	 * @param array  $fields       Ordered field descriptors.
	 * @param string $trigger_meta Meta code the tokens are stored under.
	 *
	 * @return array
	 */
	public static function build_tokens( $form_id, array $fields, $trigger_meta ) {

		$tokens     = array();
		$section_id = 0;

		foreach ( $fields as $field ) {

			// Closes the open section before the structural types drop out below.
			if ( self::SECTION_CLOSE === $field['type'] ) {
				$section_id = 0;
				continue;
			}

			if ( in_array( $field['type'], self::STRUCTURAL_TYPES, true ) ) {
				continue;
			}

			// A repeating section owns every field until the section closes.
			if ( ! empty( $field['is_repeating'] ) ) {

				$section_id = (int) $field['id'];

				// The section's own value is the list of entry ids its rows were
				// saved as, not anything the visitor typed. Under the bare
				// section label it reads as the rows themselves.
				$tokens[] = self::token(
					$form_id,
					(string) $field['id'],
					sprintf(
						/* translators: %s is the label of a repeating section */
						esc_html_x( '%s (row IDs)', 'Formidable', 'uncanny-automator' ),
						$field['label']
					),
					$field['type'],
					$trigger_meta
				);

				continue;
			}

			$prefix = $section_id > 0 ? $section_id . self::SECTION_SEPARATOR : '';

			// A name field is offered as its parts, never as a whole.
			if ( self::NAME_TYPE === $field['type'] ) {
				foreach ( (array) $field['name_parts'] as $part => $label ) {
					$tokens[] = self::token( $form_id, $prefix . $field['id'] . '-' . $part, $label, 'text', $trigger_meta );
				}
				continue;
			}

			$tokens[] = self::token( $form_id, $prefix . $field['id'], $field['label'], $field['type'], $trigger_meta );
		}

		return $tokens;
	}

	/**
	 * Split a token key into the section, field and name part it addresses.
	 *
	 * @param string $key Token key, the part of a token id after the form id.
	 *
	 * @return array|null Null when the key addresses no field.
	 */
	public static function parse_token_key( $key ) {

		$key        = (string) $key;
		$section_id = 0;

		$separator = strpos( $key, self::SECTION_SEPARATOR );

		if ( false !== $separator ) {
			$section = substr( $key, 0, $separator );
			$key     = substr( $key, $separator + strlen( self::SECTION_SEPARATOR ) );

			if ( ! ctype_digit( $section ) ) {
				return null;
			}

			$section_id = (int) $section;
		}

		$name_part = '';
		$dash      = strrpos( $key, '-' );

		if ( false !== $dash ) {
			$part = substr( $key, $dash + 1 );

			if ( in_array( $part, self::NAME_PARTS, true ) ) {
				$name_part = $part;
				$key       = substr( $key, 0, $dash );
			}
		}

		if ( ! ctype_digit( $key ) ) {
			return null;
		}

		return array(
			'section_id' => $section_id,
			'field_id'   => (int) $key,
			'name_part'  => $name_part,
		);
	}

	/**
	 * Resolve a parsed token key against the metas of an entry.
	 *
	 * A token addressing a section reads every row of that repeater and joins
	 * the values it finds; any other token reads the parent entry.
	 *
	 * @param array|null $parsed       Output of parse_token_key().
	 * @param array      $parent_metas Parent entry metas, keyed by field id.
	 * @param array      $child_metas  Child entry metas in row order, each keyed by field id.
	 *
	 * @return string
	 */
	public static function resolve_value( $parsed, array $parent_metas, array $child_metas ) {

		if ( ! is_array( $parsed ) ) {
			return '';
		}

		$rows = $parsed['section_id'] > 0 ? array_values( $child_metas ) : array( $parent_metas );

		$values = array();

		foreach ( $rows as $row ) {

			if ( ! isset( $row[ $parsed['field_id'] ] ) ) {
				continue;
			}

			$value = self::flatten( $row[ $parsed['field_id'] ], $parsed['name_part'] );

			if ( '' !== $value ) {
				$values[] = $value;
			}
		}

		return implode( ', ', $values );
	}

	/**
	 * Reduce a stored meta value to the string a token stands for.
	 *
	 * @param mixed  $value     Stored meta value.
	 * @param string $name_part Name part the token addresses, empty for the whole value.
	 *
	 * @return string
	 */
	private static function flatten( $value, $name_part ) {

		if ( '' !== $name_part ) {
			return is_array( $value ) && isset( $value[ $name_part ] ) ? (string) $value[ $name_part ] : '';
		}

		if ( ! is_array( $value ) ) {
			return (string) $value;
		}

		// A name field reads as one name, in the order its parts are spoken.
		$parts = array();

		foreach ( self::NAME_PARTS as $part ) {
			if ( ! empty( $value[ $part ] ) ) {
				$parts[] = $value[ $part ];
			}
		}

		if ( ! empty( $parts ) ) {
			return implode( ' ', $parts );
		}

		return implode( ', ', $value );
	}

	/**
	 * Assemble one token.
	 *
	 * @param string $form_id      Form the token belongs to.
	 * @param string $key          Token key addressing the field.
	 * @param string $name         Label shown in the recipe builder.
	 * @param string $type         Token type.
	 * @param string $trigger_meta Meta code the token is stored under.
	 *
	 * @return array
	 */
	private static function token( $form_id, $key, $name, $type, $trigger_meta ) {
		return array(
			'tokenId'         => $form_id . '|' . $key,
			'tokenName'       => $name,
			'tokenType'       => $type,
			'tokenIdentifier' => $trigger_meta,
		);
	}
}
