<?php
declare(strict_types=1);
namespace Uncanny_Automator\Api\Components\Scope_Tag;

/**
 * Scope Tag Configuration.
 *
 * Data transfer object for scope tag configuration with fluent interface.
 * Contains no validation logic - serves as a bridge between raw data
 * and validated domain objects.
 *
 * @since 7.0.0
 */
class Scope_Tag_Config {

	/**
	 * The data array.
	 *
	 * @var array
	 */
	private array $data = array();

	/**
	 * The tag type.
	 *
	 * @var string
	 */
	private $type;

	/**
	 * The tag scenario ID.
	 *
	 * @var string
	 */
	private $scenario_id;

	/**
	 * The tag label.
	 *
	 * @var string
	 */
	private $label;

	/**
	 * The tag icon.
	 *
	 * @var string
	 */
	private $icon;

	/**
	 * The tag color.
	 *
	 * @var string
	 */
	private $color;

	/**
	 * The tag helper text.
	 *
	 * @var string
	 */
	private $helper;

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
	 * Set tag type.
	 *
	 * @param string $type Tag type (e.g., 'license', 'dependency', 'availability', 'third-party').
	 *
	 * @return self
	 */
	public function type( string $type ): self {
		$this->type = $type;
		return $this;
	}

	/**
	 * Set tag scenario ID.
	 *
	 * @param string $scenario_id Tag scenario ID.
	 *
	 * @return self
	 */
	public function scenario_id( string $scenario_id ): self {
		$this->scenario_id = $scenario_id;
		return $this;
	}

	/**
	 * Set tag label.
	 *
	 * @param string $label Tag label.
	 *
	 * @return self
	 */
	public function label( string $label ): self {
		$this->label = $label;
		return $this;
	}

	/**
	 * Set tag icon.
	 *
	 * @param string $icon Tag icon.
	 *
	 * @return self
	 */
	public function icon( string $icon ): self {
		$this->icon = $icon;
		return $this;
	}

	/**
	 * Set tag color.
	 *
	 * @param string $color Tag color ('neutral', 'info', 'warning', 'error', 'success').
	 *
	 * @return self
	 */
	public function color( string $color ): self {
		$this->color = $color;
		return $this;
	}

	/**
	 * Set tag helper text.
	 *
	 * @param string $helper Tag helper text.
	 *
	 * @return self
	 */
	public function helper( string $helper ): self {
		$this->helper = $helper;
		return $this;
	}

	/**
	 * Get tag type.
	 *
	 * @return string|null
	 */
	public function get_type(): ?string {
		return $this->type;
	}

	/**
	 * Get tag scenario ID.
	 *
	 * @return string|null
	 */
	public function get_scenario_id(): ?string {
		return $this->scenario_id;
	}

	/**
	 * Get tag label.
	 *
	 * @return string|null
	 */
	public function get_label(): ?string {
		return $this->label;
	}

	/**
	 * Get tag icon.
	 *
	 * @return string|null
	 */
	public function get_icon(): ?string {
		return $this->icon;
	}

	/**
	 * Get tag color.
	 *
	 * @return string|null
	 */
	public function get_color(): ?string {
		return $this->color;
	}

	/**
	 * Get tag helper text.
	 *
	 * @return string|null
	 */
	public function get_helper(): ?string {
		return $this->helper;
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
			->type( $data['type'] ?? '' )
			->scenario_id( $data['scenario_id'] ?? '' )
			->label( $data['label'] ?? '' );

		// Optional fields.
		if ( isset( $data['icon'] ) ) {
			$config->icon( $data['icon'] );
		}

		if ( isset( $data['color'] ) ) {
			$config->color( $data['color'] );
		}

		if ( isset( $data['helper'] ) ) {
			$config->helper( $data['helper'] );
		}

		return $config;
	}
}
