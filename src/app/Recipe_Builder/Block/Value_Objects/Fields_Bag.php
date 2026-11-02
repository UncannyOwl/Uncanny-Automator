<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Recipe_Builder\Block\Value_Objects;

/**
 * Lightweight typed wrapper for the FieldsOutput map.
 *
 * Models a `{KEY: {value, readable, custom}}` collection as carried on
 * Recipe_Block, Recipe_Block_Modifier, Recipe_Item rows in the schema v3
 * cache and the wire shape. Frozen after construction; `with_field` returns
 * a new instance.
 *
 * Distinct from the rich per-field aggregate `Recipe_Builder/Field/Field.php`
 * which models a single field with full type / value / readable / custom /
 * miscellaneous configuration. `Field` is the design-time form-config object;
 * `Fields_Bag` is the read-side collection wrapper around storage rows.
 *
 * Defensively normalises four input shapes encountered in the wild:
 *   1. Canonical: `{KEY: {value, readable, custom}}`
 *   2. Partial:   `{KEY: {value}}` — readable + custom default to ''
 *   3. Scalar:    `{KEY: 'raw value'}` — wrapped into the canonical shape
 *   4. Flat:      `{KEY: 'val', KEY_readable: 'label', KEY_custom: 'free-form'}`
 *                 — the legacy postmeta storage shape; companions are folded
 *                 onto the parent canonical entry and the orphan keys are
 *                 dropped from the output. A `_readable` / `_custom` suffix
 *                 only counts as a companion when its stem is also a key in
 *                 the same map (otherwise it's treated as a real, oddly-named
 *                 field). For partial-canonical-plus-companion mixes, the
 *                 companion backfills missing readable/custom on the parent.
 *
 * `stdClass` payloads (from `json_decode(..., false)` callers) are coerced
 * via `(array) $value` at every level.
 */
final class Fields_Bag {

	/**
	 * Normalised `{KEY: {value, readable, custom}}` map.
	 *
	 * @var array<string, array{value: string, readable: string, custom: string}>
	 */
	private $fields;

	/**
	 * Build a bag, normalising whatever shape the caller supplied into canonical entries.
	 *
	 * @param array $raw Raw fields map — accepts canonical, partial, or scalar values.
	 */
	public function __construct( array $raw ) {
		$this->fields = self::normalise( $raw );
	}

	/**
	 * Static factory matching the constructor's contract.
	 *
	 * @param array $raw Raw fields map.
	 * @return self
	 */
	public static function from_array( array $raw ): self {
		return new self( $raw );
	}

	/**
	 * Whether the author configured any fields at all.
	 *
	 * @return bool True when the bag holds no fields.
	 */
	public function is_empty(): bool {
		return empty( $this->fields );
	}

	/**
	 * Look up one field's canonical entry, or null when the author never set it.
	 *
	 * @param string $key Field code (uppercase by convention).
	 * @return array{value: string, readable: string, custom: string}|null Canonical entry or null when absent.
	 */
	public function get( string $key ) {
		return $this->fields[ $key ] ?? null;
	}

	/**
	 * One field's value as a plain string, '' when the author never set it.
	 *
	 * @param string $key Field code.
	 * @return string
	 */
	public function scalar( string $key ): string {
		$entry = $this->get( $key );

		return null === $entry ? '' : (string) $entry['value'];
	}

	/**
	 * The legacy flat projection of a raw field map: `KEY => value`, then every other scalar,
	 * non-empty sub-key as `KEY_<sub>` (readable, custom, and any future sub-key), nothing dropped.
	 *
	 * Static over the raw map on purpose: the normalised bag models three slots, the legacy
	 * projection promises to keep them all.
	 *
	 * @param array<string, mixed> $raw Raw fields map (canonical, flat, or mixed).
	 * @return array<string, string>
	 */
	public static function to_legacy_flat( array $raw ): array {
		$flat = array();
		foreach ( $raw as $key => $entry ) {
			if ( $entry instanceof \stdClass ) {
				$entry = (array) $entry;
			}
			if ( ! is_array( $entry ) ) {
				$flat[ (string) $key ] = is_scalar( $entry ) ? (string) $entry : '';
				continue;
			}
			$flat[ (string) $key ] = isset( $entry['value'] ) && is_scalar( $entry['value'] ) ? (string) $entry['value'] : '';
			foreach ( $entry as $sub => $sub_value ) {
				if ( 'value' === $sub || ! is_scalar( $sub_value ) || '' === (string) $sub_value ) {
					continue;
				}
				$flat[ $key . '_' . $sub ] = (string) $sub_value;
			}
		}

		return $flat;
	}

	/**
	 * Return a new bag with `$key` added or overwritten. Original instance is unchanged.
	 *
	 * @param string $key      Field code.
	 * @param string $value    Field value.
	 * @param string $readable Optional readable label.
	 * @param string $custom   Optional custom-value passthrough.
	 * @return self
	 */
	public function with_field( string $key, string $value, string $readable = '', string $custom = '' ): self {
		$next         = $this->fields;
		$next[ $key ] = array(
			'value'    => $value,
			'readable' => $readable,
			'custom'   => $custom,
		);
		return new self( $next );
	}

	/**
	 * Serialize to the canonical FieldsOutput map.
	 *
	 * @return array<string, array{value: string, readable: string, custom: string}>
	 */
	public function to_array(): array {
		return $this->fields;
	}

	/**
	 * Companion-key suffixes that pair onto a parent canonical entry when
	 * their stem is also present in the raw map. See class docblock shape #4.
	 */
	private const COMPANION_SUFFIXES = array(
		'readable' => '_readable',
		'custom'   => '_custom',
	);

	/**
	 * Normalise an arbitrary raw input into the canonical map shape.
	 *
	 * @param mixed $raw Raw input — array, stdClass, or anything else.
	 * @return array<string, array{value: string, readable: string, custom: string}>
	 */
	private static function normalise( $raw ): array {
		if ( $raw instanceof \stdClass ) {
			$raw = (array) $raw;
		}
		if ( ! is_array( $raw ) ) {
			return array();
		}

		$companion_keys = self::collect_companion_keys( $raw );

		$normalised = array();
		foreach ( $raw as $key => $value ) {
			$key = (string) $key;
			if ( isset( $companion_keys[ $key ] ) ) {
				continue;
			}
			if ( $value instanceof \stdClass ) {
				$value = (array) $value;
			}
			if ( is_array( $value ) ) {
				$normalised[ $key ] = array(
					'value'    => isset( $value['value'] ) ? (string) $value['value'] : '',
					'readable' => isset( $value['readable'] ) ? (string) $value['readable'] : self::read_companion( $raw, $key . self::COMPANION_SUFFIXES['readable'] ),
					'custom'   => isset( $value['custom'] ) ? (string) $value['custom'] : self::read_companion( $raw, $key . self::COMPANION_SUFFIXES['custom'] ),
				);
				continue;
			}
			$normalised[ $key ] = array(
				'value'    => is_scalar( $value ) ? (string) $value : '',
				'readable' => self::read_companion( $raw, $key . self::COMPANION_SUFFIXES['readable'] ),
				'custom'   => self::read_companion( $raw, $key . self::COMPANION_SUFFIXES['custom'] ),
			);
		}

		return $normalised;
	}

	/**
	 * Identify `_readable` / `_custom` keys whose stem is also a key in the
	 * raw map. Such keys are companions to a parent entry and get folded
	 * onto it; standalone `_readable` / `_custom` keys (no sibling stem)
	 * stay in the output as real fields.
	 *
	 * @param array<string, mixed> $raw
	 * @return array<string, true>
	 */
	private static function collect_companion_keys( array $raw ): array {
		$companions = array();
		foreach ( $raw as $key => $_ ) {
			$key = (string) $key;
			foreach ( self::COMPANION_SUFFIXES as $suffix ) {
				$suffix_len = strlen( $suffix );
				if ( strlen( $key ) <= $suffix_len ) {
					continue;
				}
				if ( substr( $key, -$suffix_len ) !== $suffix ) {
					continue;
				}
				$stem = substr( $key, 0, -$suffix_len );
				if ( array_key_exists( $stem, $raw ) ) {
					$companions[ $key ] = true;
				}
			}
		}
		return $companions;
	}

	/**
	 * Resolve a companion value (`_readable` / `_custom`) from the raw map,
	 * tolerating the same shape variants the parent loop handles.
	 *
	 * @param array<string, mixed> $raw
	 * @param string $companion_key
	 * @return string
	 */
	private static function read_companion( array $raw, string $companion_key ): string {
		if ( ! array_key_exists( $companion_key, $raw ) ) {
			return '';
		}
		$value = $raw[ $companion_key ];
		if ( $value instanceof \stdClass ) {
			$value = (array) $value;
		}
		if ( is_array( $value ) ) {
			return isset( $value['value'] ) ? (string) $value['value'] : '';
		}
		return is_scalar( $value ) ? (string) $value : '';
	}
}
