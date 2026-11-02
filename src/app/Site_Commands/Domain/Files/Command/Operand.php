<?php
/**
 * One positional argument of a file command.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Site_Commands\Domain\Files\Command;

/**
 * A positional argument: a pattern, a path, or a list of paths.
 */
final class Operand {

	/**
	 * The argument name.
	 *
	 * @var string
	 */
	private string $name;

	/**
	 * The text the model reads.
	 *
	 * @var string
	 */
	private string $description;

	/**
	 * True for a list of strings.
	 *
	 * @var bool
	 */
	private bool $many;

	/**
	 * True when the call must supply it.
	 *
	 * @var bool
	 */
	private bool $required;

	/**
	 * The value when it is not given.
	 *
	 * @var mixed
	 */
	private $default;

	/**
	 * The most items a list accepts.
	 *
	 * @var int
	 */
	private int $max_items;

	/**
	 * The longest string, or list item, accepted.
	 *
	 * @var int
	 */
	private int $max_length;

	/**
	 * Describe one operand.
	 *
	 * @param string $name        The argument name.
	 * @param string $description The text the model reads.
	 * @param bool   $many        True for a list of strings.
	 * @param bool   $required    True when the call must supply it.
	 * @param mixed  $default     The value when it is not given.
	 * @param int    $max_items   The most items a list accepts.
	 * @param int    $max_length  The longest string accepted.
	 */
	public function __construct( string $name, string $description, bool $many = false, bool $required = false, $default = null, int $max_items = 20, int $max_length = 4096 ) {
		$this->name        = $name;
		$this->description = $description;
		$this->many        = $many;
		$this->required    = $required;
		$this->default     = $default;
		$this->max_items   = $max_items;
		$this->max_length  = $max_length;
	}

	/**
	 * One path.
	 *
	 * @param string $description The text the model reads.
	 * @param bool   $required    True when the call must supply it.
	 * @param mixed  $default     The value when it is not given.
	 * @return self
	 */
	public static function path( string $description, bool $required = false, $default = null ): self {
		return new self( 'path', $description, false, $required, $default );
	}

	/**
	 * A list of paths.
	 *
	 * @param string $description The text the model reads.
	 * @param bool   $required    True when the call must supply it.
	 * @param int    $max_items   The most items accepted.
	 * @return self
	 */
	public static function paths( string $description, bool $required = false, int $max_items = 20 ): self {
		return new self( 'paths', $description, true, $required, array(), $max_items );
	}

	/**
	 * A required search pattern of at most 1024 bytes.
	 *
	 * @param string $description The text the model reads.
	 * @return self
	 */
	public static function pattern( string $description ): self {
		return new self( 'pattern', $description, false, true, null, 20, 1024 );
	}

	/**
	 * The argument name.
	 *
	 * @return string
	 */
	public function name(): string {
		return $this->name;
	}

	/**
	 * The text the model reads.
	 *
	 * @return string
	 */
	public function description(): string {
		return $this->description;
	}

	/**
	 * True for a list of strings.
	 *
	 * @return bool
	 */
	public function many(): bool {
		return $this->many;
	}

	/**
	 * True when the call must supply it.
	 *
	 * @return bool
	 */
	public function required(): bool {
		return $this->required;
	}

	/**
	 * The value when it is not given.
	 *
	 * @return mixed
	 */
	public function default() {
		return $this->default;
	}

	/**
	 * The most items a list accepts.
	 *
	 * @return int
	 */
	public function max_items(): int {
		return $this->max_items;
	}

	/**
	 * The longest string, or list item, accepted.
	 *
	 * @return int
	 */
	public function max_length(): int {
		return $this->max_length;
	}
}
