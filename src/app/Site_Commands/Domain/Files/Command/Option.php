<?php
/**
 * One option of a file command.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Site_Commands\Domain\Files\Command;

/**
 * One option of a command: the GNU long name, the short flag it documents,
 * its type and bounds.
 */
final class Option {

	/**
	 * The GNU long name.
	 *
	 * @var string
	 */
	private string $name;

	/**
	 * The short flag it documents, without the dash.
	 *
	 * @var string|null
	 */
	private ?string $short;

	/**
	 * One of the Option_Type constants.
	 *
	 * @var string
	 */
	private string $type;

	/**
	 * The text the model reads.
	 *
	 * @var string
	 */
	private string $description;

	/**
	 * The value when the option is not given.
	 *
	 * @var mixed
	 */
	private $default;

	/**
	 * The smallest integer accepted.
	 *
	 * @var int|null
	 */
	private ?int $min;

	/**
	 * The largest integer accepted, or the most list items.
	 *
	 * @var int|null
	 */
	private ?int $max;

	/**
	 * The accepted text values.
	 *
	 * @var string[]|null
	 */
	private ?array $enum_values;

	/**
	 * Describe one option.
	 *
	 * @param string        $name        The GNU long name.
	 * @param string|null   $short       The short flag it documents.
	 * @param string        $type        One of the Option_Type constants.
	 * @param string        $description The text the model reads.
	 * @param mixed         $default     The value when the option is not given.
	 * @param int|null      $min         The smallest integer accepted.
	 * @param int|null      $max         The largest integer accepted, or the most list items.
	 * @param string[]|null $enum        The accepted text values.
	 */
	public function __construct( string $name, ?string $short, string $type, string $description, $default = null, ?int $min = null, ?int $max = null, ?array $enum = null ) {
		$this->name        = $name;
		$this->short       = $short;
		$this->type        = $type;
		$this->description = $description;
		$this->default     = $default;
		$this->min         = $min;
		$this->max         = $max;
		$this->enum_values = $enum;
	}

	/**
	 * A true or false switch, false by default.
	 *
	 * @param string      $name        The GNU long name.
	 * @param string|null $short       The short flag it documents.
	 * @param string      $description The text the model reads.
	 * @return self
	 */
	public static function flag( string $name, ?string $short, string $description ): self {
		return new self( $name, $short, Option_Type::FLAG, $description, false );
	}

	/**
	 * A whole number within bounds.
	 *
	 * @param string      $name        The GNU long name.
	 * @param string|null $short       The short flag it documents.
	 * @param string      $description The text the model reads.
	 * @param int|null    $default     The value when the option is not given.
	 * @param int         $min         The smallest integer accepted.
	 * @param int         $max         The largest integer accepted.
	 * @return self
	 */
	public static function integer( string $name, ?string $short, string $description, ?int $default, int $min, int $max ): self {
		return new self( $name, $short, Option_Type::INTEGER, $description, $default, $min, $max );
	}

	/**
	 * One string, optionally from a fixed list.
	 *
	 * @param string        $name        The GNU long name.
	 * @param string|null   $short       The short flag it documents.
	 * @param string        $description The text the model reads.
	 * @param string|null   $default     The value when the option is not given.
	 * @param string[]|null $enum        The accepted values.
	 * @return self
	 */
	public static function text( string $name, ?string $short, string $description, ?string $default = null, ?array $enum = null ): self {
		return new self( $name, $short, Option_Type::TEXT, $description, $default, null, null, $enum );
	}

	/**
	 * A bounded list of strings.
	 *
	 * @param string      $name        The GNU long name.
	 * @param string|null $short       The short flag it documents.
	 * @param string      $description The text the model reads.
	 * @param int         $max_items   The most items accepted.
	 * @param string[]    $default     The value when the option is not given.
	 * @return self
	 */
	public static function list( string $name, ?string $short, string $description, int $max_items = 20, array $default = array() ): self {
		return new self( $name, $short, Option_Type::TEXT_LIST, $description, $default, null, $max_items );
	}

	/**
	 * The GNU long name.
	 *
	 * @return string
	 */
	public function name(): string {
		return $this->name;
	}

	/**
	 * The short flag it documents.
	 *
	 * @return string|null
	 */
	public function short(): ?string {
		return $this->short;
	}

	/**
	 * One of the Option_Type constants.
	 *
	 * @return string
	 */
	public function type(): string {
		return $this->type;
	}

	/**
	 * The text the model reads, without the short flag.
	 *
	 * @return string
	 */
	public function description(): string {
		return $this->description;
	}

	/**
	 * The value when the option is not given.
	 *
	 * @return mixed
	 */
	public function default() {
		return $this->default;
	}

	/**
	 * The smallest integer accepted.
	 *
	 * @return int|null
	 */
	public function min(): ?int {
		return $this->min;
	}

	/**
	 * The largest integer accepted, or the most list items.
	 *
	 * @return int|null
	 */
	public function max(): ?int {
		return $this->max;
	}

	/**
	 * The accepted text values.
	 *
	 * @return string[]|null
	 */
	public function enum_values(): ?array {
		return $this->enum_values;
	}
}
