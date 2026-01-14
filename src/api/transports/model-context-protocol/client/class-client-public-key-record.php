<?php
/**
 * MCP public key record value object.
 *
 * @since 7.0.0
 */

declare(strict_types=1);

namespace Uncanny_Automator\Api\Transports\Model_Context_Protocol\Client;

/**
 * Class Client_Public_Key_Record
 *
 * Simple DTO that carries public key metadata.
 */
class Client_Public_Key_Record {

	/**
	 * Key version identifier.
	 *
	 * @var string
	 */
	private string $version;

	/**
	 * Base64 encoded public key.
	 *
	 * @var string
	 */
	private string $public_key_b64;

	/**
	 * Unix timestamp when the key was fetched.
	 *
	 * @var int
	 */
	private int $fetched_at;

	/**
	 * Record source label.
	 *
	 * @var string
	 */
	private string $source;

	/**
	 * Validation status.
	 *
	 * @var string
	 */
	private string $status;

	/**
	 * Constructor.
	 *
	 * @param string|null $version      Key version.
	 * @param string|null $public_key   Base64 public key.
	 * @param int|null    $fetched_at   Timestamp.
	 * @param string|null $source       Source label.
	 * @param string|null $status       Validation status.
	 */
	public function __construct( ?string $version = '', ?string $public_key = '', ?int $fetched_at = 0, ?string $source = '', ?string $status = 'failed' ) {
		$this->version        = (string) $version;
		$this->public_key_b64 = (string) $public_key;
		$this->fetched_at     = (int) $fetched_at;
		$this->source         = (string) $source;
		$this->status         = (string) $status;
	}

	/**
	 * Create an empty record instance.
	 *
	 * @return self
	 */
	public static function empty(): self {
		return new self();
	}

	/**
	 * Create a record from raw array data.
	 *
	 * @param array<string,mixed>|null $data Raw data.
	 * @return self
	 */
	public static function from_array( ?array $data ): self {
		if ( empty( $data ) ) {
			return self::empty();
		}

		return new self(
			self::array_key_cast_to( $data, 'version', 'strval' ),
			self::array_key_cast_to( $data, 'public_key_b64', 'strval' ),
			self::array_key_cast_to( $data, 'fetched_at', 'intval' ),
			self::array_key_cast_to( $data, 'source', 'strval' ),
			self::array_key_cast_to( $data, 'validation_status', 'strval' )
		);
	}

	/**
	 * Cast a value to a specific type.
	 *
	 * @param array    $array_data Array to cast.
	 * @param string   $key        Key to cast.
	 * @param callable $callback   Callback to cast the value.
	 * @return mixed
	 */
	public static function array_key_cast_to( array $array_data, string $key, callable $callback ) {
		$value = $array_data[ $key ] ?? null;
		return is_null( $value ) ? null : call_user_func( $callback, $value );
	}

	/**
	 * Export the record as an array.
	 *
	 * @return array<string,mixed>
	 */
	public function to_array(): array {
		return array(
			'version'           => $this->version,
			'public_key_b64'    => $this->public_key_b64,
			'fetched_at'        => $this->fetched_at,
			'source'            => $this->source,
			'validation_status' => $this->status,
		);
	}

	/**
	 * Check whether the record has a usable key.
	 *
	 * @return bool
	 */
	public function is_empty(): bool {
		return '' === $this->public_key_b64;
	}

	/**
	 * Check if the validation status is acceptable.
	 *
	 * @return bool
	 */
	public function is_status_ok(): bool {
		return in_array( $this->status, array( 'ok', 'cached' ), true );
	}

	/**
	 * Determine if the record is considered stale.
	 *
	 * @param int $ttl Cache lifetime in seconds.
	 * @param int $now Current timestamp.
	 * @return bool
	 */
	public function is_stale( int $ttl, int $now ): bool {
		if ( $this->is_empty() ) {
			return true;
		}

		if ( $this->fetched_at <= 0 ) {
			return true;
		}

		return ( $now - $this->fetched_at ) >= $ttl;
	}

	/**
	 * Return a copy with a different status.
	 *
	 * @param string $status New status.
	 * @return self
	 */
	public function with_status( string $status ): self {
		return new self( $this->version, $this->public_key_b64, $this->fetched_at, $this->source, $status );
	}

	/**
	 * Return a copy with a new fetch timestamp and source.
	 *
	 * @param int    $fetched_at Timestamp.
	 * @param string $source     Source label.
	 * @return self
	 */
	public function with_fetch_metadata( int $fetched_at, string $source ): self {
		return new self( $this->version, $this->public_key_b64, $fetched_at, $source, $this->status );
	}

	/**
	 * Get the Base64 encoded key.
	 *
	 * @return string
	 */
	public function get_base64(): string {
		return $this->public_key_b64;
	}

	/**
	 * Get current status.
	 *
	 * @return string
	 */
	public function get_status(): string {
		return $this->status;
	}

	/**
	 * Get version string.
	 *
	 * @return string
	 */
	public function get_version(): string {
		return $this->version;
	}
}
