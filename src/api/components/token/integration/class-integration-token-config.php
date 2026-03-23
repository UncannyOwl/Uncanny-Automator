<?php
declare(strict_types=1);
namespace Uncanny_Automator\Api\Components\Token\Integration;

/**
 * Integration Token Configuration.
 *
 * Data transfer object for integration token configuration with fluent interface.
 * Contains no validation logic - serves as a bridge between raw data
 * and validated domain objects.
 *
 * This is for integration-level tokens (tokens provided by integrations).
 *
 * @since 7.0.0
 */
class Integration_Token_Config {

	/**
	 * The code of the token.
	 *
	 * @var string
	 */
	private $code;

	/**
	 * The name of the token.
	 *
	 * @var string
	 */
	private $name;

	/**
	 * The data type of the token.
	 *
	 * @var string
	 */
	private $data_type;

	/**
	 * Whether the token requires user data.
	 *
	 * @var bool
	 */
	private $requires_user_data = false;

	/**
	 * Get the code of the token.
	 *
	 * @return string
	 */
	public function get_code(): string {
		return $this->code;
	}

	/**
	 * Get the name of the token.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return $this->name;
	}

	/**
	 * Get the data type of the token.
	 *
	 * @return string
	 */
	public function get_data_type(): string {
		return $this->data_type;
	}

	/**
	 * Get whether the token requires user data.
	 *
	 * @return bool
	 */
	public function get_requires_user_data(): bool {
		return $this->requires_user_data;
	}

	/**
	 * Set the code of the token.
	 *
	 * @param string $code The code of the token.
	 *
	 * @return self
	 */
	public function code( string $code ): self {
		$this->code = $code;
		return $this;
	}

	/**
	 * Set the name of the token.
	 *
	 * @param string $name The name of the token.
	 *
	 * @return self
	 */
	public function name( string $name ): self {
		$this->name = $name;
		return $this;
	}

	/**
	 * Set the data type of the token.
	 *
	 * @param string $data_type The data type of the token.
	 *
	 * @return self
	 */
	public function data_type( string $data_type ): self {
		$this->data_type = $data_type;
		return $this;
	}

	/**
	 * Set whether the token requires user data.
	 *
	 * @param bool $requires_user_data Whether the token requires user data.
	 *
	 * @return self
	 */
	public function requires_user_data( bool $requires_user_data ): self {
		$this->requires_user_data = $requires_user_data;
		return $this;
	}

	/**
	 * Create from array.
	 *
	 * @param array $data Array data.
	 *
	 * @return self
	 */
	public static function from_array( array $data ): self {
		$config = ( new self() )
			->code( $data['code'] ?? '' )
			->name( $data['name'] ?? '' )
			->data_type( $data['data_type'] ?? 'text' )
			->requires_user_data( $data['requires_user_data'] ?? false );

		return $config;
	}
}
