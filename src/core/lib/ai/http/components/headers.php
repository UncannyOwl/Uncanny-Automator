<?php
declare(strict_types=1);

namespace Uncanny_Automator\Core\Lib\AI\Http\Component;

/**
 * Immutable HTTP headers value object.
 *
 * Manages HTTP headers for AI API requests with validation.
 * Provides convenience methods for common headers.
 *
 * @since 5.6
 */
final class Headers {

	/**
	 * @var array<string,string>
	 */
	private $headers;

	/**
	 * Initialize with headers validation.
	 *
	 * @param array<string,string> $headers HTTP headers
	 *
	 * @throws \InvalidArgumentException If headers invalid
	 */
	public function __construct( array $headers = array() ) {
		foreach ( $headers as $name => $value ) {
			if ( '' === trim( $name ) || '' === trim( $value ) ) {
				throw new \InvalidArgumentException( 'Header names and values must be non-empty strings.' );
			}
		}
		$this->headers = $headers;
	}

	/**
	 * Add header to collection.
	 *
	 * @param string $name  Header name
	 * @param string $value Header value
	 *
	 * @return self New instance with added header
	 */
	public function with( string $name, string $value ): self {
		$new                   = clone $this;
		$new->headers[ $name ] = $value;
		return $new;
	}

	/**
	 * Add authorization header.
	 *
	 * @param string $token    Auth token
	 * @param string $type     Auth type (default: Bearer)
	 * @param string $auth_key Header name (default: Authorization)
	 *
	 * @return self New instance with authorization header
	 */
	public function authorization( string $token, string $type = 'Bearer', string $auth_key = 'Authorization' ): self {
		return $this->with( $auth_key, "{$type} {$token}" );
	}

	/**
	 * Set Content-Type header.
	 *
	 * @param string $type Content type
	 *
	 * @return self New instance with content type set
	 */
	public function content_type( string $type ): self {
		return $this->with( 'Content-Type', $type );
	}

	/**
	 * Get headers as array.
	 *
	 * @return array<string,string> Headers array
	 */
	public function to_array(): array {
		return $this->headers;
	}
}
