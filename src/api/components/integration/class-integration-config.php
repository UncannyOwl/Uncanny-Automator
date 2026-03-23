<?php
declare(strict_types=1);
namespace Uncanny_Automator\Api\Components\Integration;

/**
 * Integration Configuration.
 *
 * Data transfer object for integration configuration with fluent interface.
 * Contains no validation logic - serves as a bridge between raw data
 * and validated domain objects.
 *
 * @since 7.0.0
 */
class Integration_Config {

	/**
	 * The data array.
	 *
	 * @var array
	 */
	private array $data = array();

	/**
	 * The integration code.
	 *
	 * @var string
	 */
	private $code;

	/**
	 * The integration name.
	 *
	 * @var string
	 */
	private $name;

	/**
	 * The required tier.
	 *
	 * @var string
	 */
	private $required_tier;

	/**
	 * The integration type.
	 *
	 * @var string
	 */
	private $type;

	/**
	 * The integration details.
	 *
	 * @var array
	 */
	private $details = array();

	/**
	 * The integration items.
	 *
	 * @var array
	 */
	private $items = array();

	/**
	 * The integration tokens.
	 *
	 * @var array
	 */
	private $tokens = array();

	/**
	 * The connected status.
	 *
	 * @var bool|null
	 */
	private ?bool $connected = null;

	/**
	 * Set a configuration value (generic approach).
	 *
	 * @param string $key Configuration key.
	 * @param mixed  $value Configuration value.
	 *
	 * @return self
	 */
	public function set( string $key, $value ): self {
		$this->data[ $key ] = $value;
		return $this;
	}

	/**
	 * Get a configuration value (generic approach).
	 *
	 * @param string $key Configuration key.
	 * @param mixed  $default_value Default value if key not found.
	 *
	 * @return mixed
	 */
	public function get( string $key, $default_value = null ) {
		return $this->data[ $key ] ?? $default_value;
	}

	/**
	 * Set integration code.
	 *
	 * @param string $code Integration code (e.g., 'WOO', 'LEARNDASH').
	 *
	 * @return self
	 */
	public function code( string $code ): self {
		$this->code = $code;
		return $this;
	}

	/**
	 * Set integration name.
	 *
	 * @param string $name Integration name (e.g., 'WooCommerce', 'LearnDash').
	 *
	 * @return self
	 */
	public function name( string $name ): self {
		$this->name = $name;
		return $this;
	}

	/**
	 * Set required tier.
	 *
	 * @param string $required_tier Required tier ('lite', 'pro-basic', 'pro-plus', 'pro-elite').
	 *
	 * @return self
	 */
	public function required_tier( string $required_tier ): self {
		$this->required_tier = $required_tier;
		return $this;
	}

	/**
	 * Set integration type.
	 *
	 * @param string $type Integration type ('plugin', 'app', 'built-in').
	 *
	 * @return self
	 */
	public function type( string $type ): self {
		$this->type = $type;
		return $this;
	}

	/**
	 * Set integration details.
	 *
	 * @param array $details Integration details array.
	 *
	 * @return self
	 */
	public function details( array $details ): self {
		$this->details = $details;
		return $this;
	}

	/**
	 * Set integration items.
	 *
	 * @param array $items Integration items array.
	 *
	 * @return self
	 */
	public function items( array $items ): self {
		$this->items = $items;
		return $this;
	}

	/**
	 * Set integration tokens.
	 *
	 * @param array $tokens Integration tokens array.
	 *
	 * @return self
	 */
	public function tokens( array $tokens ): self {
		$this->tokens = $tokens;
		return $this;
	}

	/**
	 * Set connected status (for app integrations).
	 *
	 * @param bool $connected Connected status.
	 *
	 * @return self
	 */
	public function connected( bool $connected ): self {
		$this->connected = $connected;
		return $this;
	}

	/**
	 * Get integration code.
	 *
	 * @return string|null
	 */
	public function get_code(): ?string {
		return $this->code;
	}

	/**
	 * Get integration name.
	 *
	 * @return string|null
	 */
	public function get_name(): ?string {
		return $this->name;
	}

	/**
	 * Get required tier.
	 *
	 * @return string|null
	 */
	public function get_required_tier(): ?string {
		return $this->required_tier;
	}

	/**
	 * Get integration type.
	 *
	 * @return string|null
	 */
	public function get_type(): ?string {
		return $this->type;
	}

	/**
	 * Get integration details.
	 *
	 * @return array
	 */
	public function get_details(): array {
		return $this->details;
	}

	/**
	 * Get integration items.
	 *
	 * @return array
	 */
	public function get_items(): array {
		return $this->items;
	}

	/**
	 * Get integration tokens.
	 *
	 * @return array
	 */
	public function get_tokens(): array {
		return $this->tokens;
	}

	/**
	 * Get connected status.
	 *
	 * @return bool|null
	 */
	public function get_connected(): ?bool {
		return $this->connected;
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
			->required_tier( $data['required_tier'] ?? 'lite' )
			->type( $data['type'] ?? 'plugin' )
			->details( $data['details'] ?? array() )
			->items( $data['items'] ?? array() )
			->tokens( $data['tokens'] ?? array() );

		// Only set connected if provided ( app or third-party that utilize settings )
		if ( isset( $data['connected'] ) ) {
			$config->connected( (bool) $data['connected'] );
		}

		return $config;
	}
}
