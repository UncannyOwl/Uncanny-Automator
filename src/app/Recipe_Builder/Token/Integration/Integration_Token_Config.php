<?php
declare(strict_types=1);
namespace Uncanny_Automator\App\Recipe_Builder\Token\Integration;

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
	 * Field directory for configurable tokens (setup-modal field rows).
	 *
	 * @var array
	 */
	private $fields = array();

	/**
	 * ID template for configurable tokens ('' when not configurable).
	 *
	 * @var string
	 */
	private $id_template = '';

	/**
	 * Name template for configurable tokens ('' when not configurable).
	 *
	 * @var string
	 */
	private $name_template = '';

	/**
	 * Wire tag rows for the token ([] when none).
	 *
	 * @var array
	 */
	private $tags = array();

	/**
	 * Get the code of the token.
	 *
	 * @return string
	 */
	public function get_code(): string {
		return $this->code;
	}

	/**
	 * Get the field directory for configurable tokens.
	 *
	 * @return array
	 */
	public function get_fields(): array {
		return $this->fields;
	}

	/**
	 * Get the ID template for configurable tokens.
	 *
	 * @return string
	 */
	public function get_id_template(): string {
		return $this->id_template;
	}

	/**
	 * Get the name template for configurable tokens.
	 *
	 * @return string
	 */
	public function get_name_template(): string {
		return $this->name_template;
	}

	/**
	 * Get the wire tag rows for the token.
	 *
	 * @return array
	 */
	public function get_tags(): array {
		return $this->tags;
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
	 * Set the field directory for configurable tokens.
	 *
	 * @param array $fields Setup-modal field rows.
	 *
	 * @return self
	 */
	public function fields( array $fields ): self {
		$this->fields = $fields;
		return $this;
	}

	/**
	 * Set the ID template for configurable tokens.
	 *
	 * @param string $id_template ID template.
	 *
	 * @return self
	 */
	public function id_template( string $id_template ): self {
		$this->id_template = $id_template;
		return $this;
	}

	/**
	 * Set the name template for configurable tokens.
	 *
	 * @param string $name_template Name template.
	 *
	 * @return self
	 */
	public function name_template( string $name_template ): self {
		$this->name_template = $name_template;
		return $this;
	}

	/**
	 * Set the wire tag rows for the token.
	 *
	 * @param array $tags Wire tag rows.
	 *
	 * @return self
	 */
	public function tags( array $tags ): self {
		$this->tags = $tags;
		return $this;
	}

	/**
	 * Create from array. Accepts the registry's camelCase spellings as
	 * fallbacks for the config-block keys.
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
			->requires_user_data( $data['requires_user_data'] ?? false )
			->fields( is_array( $data['fields'] ?? null ) ? $data['fields'] : array() )
			->id_template( (string) ( $data['id_template'] ?? $data['idTemplate'] ?? '' ) )
			->name_template( (string) ( $data['name_template'] ?? $data['nameTemplate'] ?? '' ) )
			->tags( is_array( $data['tags'] ?? null ) ? $data['tags'] : array() );

		return $config;
	}
}
