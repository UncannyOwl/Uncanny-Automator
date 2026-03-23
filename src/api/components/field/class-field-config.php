<?php
// phpcs:disable WordPress.Security.EscapeOutput, WordPress.WP.I18n
declare(strict_types=1);

namespace Uncanny_Automator\Api\Components\Field;

/**
 * Field Configuration.
 *
 * Data transfer object for field configuration with fluent interface.
 * Contains no validation logic - serves as a bridge between raw data
 * and validated domain objects.
 *
 * @since 7.0
 */
class Field_Config {

	/**
	 * Field code.
	 *
	 * @var string
	 */
	private string $code = '';

	/**
	 * Field type.
	 *
	 * @var string
	 */
	private string $type = 'text';

	/**
	 * Field value.
	 *
	 * @var mixed
	 */
	private $value = '';

	/**
	 * Readable value.
	 *
	 * @var string
	 */
	private string $readable = '';

	/**
	 * Custom value.
	 *
	 * @var string
	 */
	private string $custom = '';

	/**
	 * Miscellaneous data.
	 *
	 * @var array
	 */
	private array $miscellaneous = array();

	/**
	 * Private constructor. Use create() or from_array() to instantiate.
	 *
	 * @return void
	 */
	private function __construct() {
	}

	/**
	 * Create new config instance.
	 *
	 * @return self
	 */
	public static function create(): self {
		return new self();
	}

	/**
	 * Create from array.
	 *
	 * @param string $code Field code.
	 * @param array  $data Field data array.
	 *
	 * @return self
	 */
	public static function from_array( string $code, array $data ): self {
		$config                = new self();
		$config->code          = $code;
		$config->type          = $data['type'] ?? 'text';
		$config->value         = $data['value'] ?? '';
		$config->readable      = $data['readable'] ?? '';
		$config->custom        = $data['custom'] ?? '';
		$config->miscellaneous = $data['miscellaneous'] ?? array();
		return $config;
	}

	/**
	 * Set field code.
	 *
	 * @param string $code Field code.
	 *
	 * @return self
	 */
	public function code( string $code ): self {
		$this->code = $code;
		return $this;
	}

	/**
	 * Set field type.
	 *
	 * @param string $type Field type.
	 *
	 * @return self
	 */
	public function type( string $type ): self {
		$this->type = $type;
		return $this;
	}

	/**
	 * Set field value.
	 *
	 * @param mixed $value Field value.
	 *
	 * @return self
	 */
	public function value( $value ): self {
		$this->value = $value;
		return $this;
	}

	/**
	 * Set readable value.
	 *
	 * @param string $readable Readable value.
	 *
	 * @return self
	 */
	public function readable( string $readable ): self {
		$this->readable = $readable;
		return $this;
	}

	/**
	 * Set custom value.
	 *
	 * @param string $custom Custom value.
	 *
	 * @return self
	 */
	public function custom( string $custom ): self {
		$this->custom = $custom;
		return $this;
	}

	/**
	 * Set miscellaneous data.
	 *
	 * @param array $miscellaneous Miscellaneous data.
	 *
	 * @return self
	 */
	public function miscellaneous( array $miscellaneous ): self {
		$this->miscellaneous = $miscellaneous;
		return $this;
	}

	/**
	 * Get field code.
	 *
	 * @return string
	 */
	public function get_code(): string {
		return $this->code;
	}

	/**
	 * Get field type.
	 *
	 * @return string
	 */
	public function get_type(): string {
		return $this->type;
	}

	/**
	 * Get field value.
	 *
	 * @return mixed
	 */
	public function get_value() {
		return $this->value;
	}

	/**
	 * Get readable value.
	 *
	 * @return string
	 */
	public function get_readable(): string {
		return $this->readable;
	}

	/**
	 * Get custom value.
	 *
	 * @return string
	 */
	public function get_custom(): string {
		return $this->custom;
	}

	/**
	 * Get miscellaneous data.
	 *
	 * @return array
	 */
	public function get_miscellaneous(): array {
		return $this->miscellaneous;
	}
}
