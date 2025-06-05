<?php
declare(strict_types=1);

namespace Uncanny_Automator\Core\Lib\AI\Http\Component;

/**
 * Immutable HTTP request body component.
 *
 * Provides fluent API for building AI request payloads.
 * Includes convenience methods for common AI parameters.
 *
 * @since 5.6
 */
final class Body {

	/**
	 * @var array<string,mixed>
	 */
	private $data;

	/**
	 * Initialize with data array.
	 *
	 * @param array<string,mixed> $data Initial data
	 */
	public function __construct( array $data = array() ) {
		$this->data = $data;
	}

	/**
	 * Add field to body data.
	 *
	 * @param string $key   Field name
	 * @param mixed  $value Field value
	 *
	 * @return self New instance with added field
	 */
	public function with_field( string $key, $value ): self {
		$new               = clone $this;
		$new->data[ $key ] = $value;
		return $new;
	}

	/**
	 * Add field to body data (alias).
	 *
	 * @param mixed $key   Field name
	 * @param mixed $value Field value
	 *
	 * @return self New instance with added field
	 */
	public function with( $key, $value ): self {
		return $this->with_field( $key, $value );
	}

	/**
	 * Set AI model parameter.
	 *
	 * @param string $model Model identifier
	 *
	 * @return self New instance with model set
	 */
	public function with_model( string $model ): self {
		return $this->with_field( 'model', $model );
	}

	/**
	 * Set temperature parameter.
	 *
	 * @param float $temperature Randomness control (0.0-1.0)
	 *
	 * @return self New instance with temperature set
	 */
	public function with_temperature( float $temperature ): self {
		return $this->with_field( 'temperature', $temperature );
	}

	/**
	 * Set max tokens parameter (legacy).
	 *
	 * @param int $max_tokens Maximum tokens to generate
	 *
	 * @return self New instance with max_tokens set
	 */
	public function with_max_tokens( int $max_tokens ): self {
		// Max tokens is deprecated in favor of max_completion_tokens.
		return $this->with_field( 'max_tokens', $max_tokens );
	}

	/**
	 * Set max completion tokens parameter.
	 *
	 * @param int $max_completion_tokens Maximum completion tokens
	 *
	 * @return self New instance with max_completion_tokens set
	 */
	public function with_max_completion_tokens( int $max_completion_tokens ): self {
		return $this->with_field( 'max_completion_tokens', $max_completion_tokens );
	}

	/**
	 * Set messages parameter.
	 *
	 * @param array $messages Conversation messages
	 *
	 * @return self New instance with messages set
	 */
	public function with_messages( array $messages ): self {
		return $this->with_field( 'messages', $messages );
	}

	/**
	 * Get body data as array.
	 *
	 * @return array<string,mixed> Body data
	 */
	public function to_array(): array {
		return $this->data;
	}
}
