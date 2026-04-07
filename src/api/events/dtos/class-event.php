<?php
declare(strict_types=1);

namespace Uncanny_Automator\Api\Events\Dtos;

/**
 * Generic event payload with a normalized array shape.
 */
final class Event implements Event_Dto {

	/**
	 * @var array<string,mixed>
	 */
	private array $data;

	/**
	 * @param array<string,mixed> $data Event payload.
	 */
	public function __construct( array $data = array() ) {
		$this->data = $this->normalize_array( $data );
	}

	/**
	 * @param string $key Event payload key.
	 * @param mixed  $default Default value when the key is missing.
	 *
	 * @return mixed
	 */
	public function get( string $key, $default = null ) {
		return array_key_exists( $key, $this->data ) ? $this->data[ $key ] : $default;
	}

	/**
	 * @param string $key Event payload key.
	 *
	 * @return bool
	 */
	public function has( string $key ): bool {
		return array_key_exists( $key, $this->data );
	}

	/**
	 * @return array<string,mixed>
	 */
	public function to_array(): array {
		return $this->data;
	}

	/**
	 * @param array<string,mixed> $data Raw event payload.
	 *
	 * @return array<string,mixed>
	 */
	private function normalize_array( array $data ): array {
		$normalized = array();

		foreach ( $data as $key => $value ) {
			$normalized[ $key ] = $this->normalize_value( $value );
		}

		return $normalized;
	}

	/**
	 * @param mixed $value Raw event payload value.
	 *
	 * @return mixed
	 */
	private function normalize_value( $value ) {
		if ( $value instanceof Event_Dto ) {
			return $value->to_array();
		}

		if ( is_object( $value ) && method_exists( $value, 'to_array' ) ) {
			$value = $value->to_array();
		}

		if ( is_array( $value ) ) {
			return $this->normalize_array( $value );
		}

		return $value;
	}
}
