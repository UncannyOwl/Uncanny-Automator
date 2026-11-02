<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Recipe_Logs\Wire_Serializer;

/**
 * Maps the legacy `set_log_properties()` entries to the recipe-log wire's
 * closed property-type enum. The FE coerces unknown types to `text` too,
 * but doing it here keeps the wire honest. Every entry passes through
 * Log_Property_Redactor, so no reader of the log sees a credential header.
 */
final class Log_Properties_Mapper {

	/**
	 * Hides the credential headers of a "Request headers" property.
	 *
	 * @since 8.0.0
	 *
	 * @var Log_Property_Redactor
	 */
	private $redactor;

	/**
	 * Wires up the mapper, defaulting the redactor to its production implementation.
	 *
	 * @since 8.0.0
	 *
	 * @param Log_Property_Redactor|null $redactor Credential-header redactor.
	 */
	public function __construct( ?Log_Property_Redactor $redactor = null ) {
		$this->redactor = $redactor ?? new Log_Property_Redactor();
	}

	/**
	 * Normalize a `properties` meta value into wire entries.
	 *
	 * @param mixed $properties The decoded meta value.
	 * @return array<int,array<string,mixed>> Wire property entries, empty when not an array.
	 */
	public function map( $properties ): array {
		if ( ! is_array( $properties ) ) {
			return array();
		}
		$out = array();
		foreach ( $properties as $entry ) {
			$entry = (array) $entry;
			$type  = Log_Property_Types::coerce( (string) ( $entry['type'] ?? Log_Property_Types::TEXT ) );
			$wire  = array(
				'type'  => $type,
				'label' => (string) ( $entry['label'] ?? '' ),
				'value' => $this->stringify_value( $entry['value'] ?? '' ),
			);
			if ( isset( $entry['attributes'] ) && is_array( $entry['attributes'] ) && ! empty( $entry['attributes'] ) ) {
				$wire['attributes'] = $entry['attributes'];
			}
			$out[] = $this->redactor->redact( $wire );
		}
		return $out;
	}

	/**
	 * Force a property value to a string, unwrapping the {raw,parsed} envelope and a one-item list.
	 *
	 * Pro's webhook triggers store each request detail as a one-item list holding the JSON text
	 * (`(array) wp_json_encode( ... )`); 7.x showed that text, so the log ships the item itself.
	 *
	 * @param mixed $value Raw value.
	 * @return string The stringified value.
	 */
	private function stringify_value( $value ): string {
		if ( is_array( $value ) && array_key_exists( 'parsed', $value ) ) {
			return (string) $value['parsed'];
		}
		if ( is_array( $value ) && array( 0 ) === array_keys( $value ) && is_scalar( $value[0] ) ) {
			return (string) $value[0];
		}
		if ( is_scalar( $value ) ) {
			return (string) $value;
		}
		return (string) wp_json_encode( $value );
	}
}
