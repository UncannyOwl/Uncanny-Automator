<?php
/**
 * An operand as given, as an absolute path, and resolved.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Site_Commands\Domain\Files\Commands;

use Uncanny_Automator\App\Site_Commands\Domain\Files\Filesystem\Standard_Input;

/**
 * An operand three ways: as the caller wrote it (GNU prints this), as the
 * absolute path before symlink resolution (lstat looks at this), and fully
 * resolved (reads open this). The sandbox has verified that the resolved
 * path is inside a root.
 */
final class Resolved_Path {

	/**
	 * As the caller wrote it.
	 *
	 * @var string
	 */
	private string $given;

	/**
	 * Fully resolved.
	 *
	 * @var string
	 */
	private string $real;

	/**
	 * Absolute, before symlink resolution.
	 *
	 * @var string
	 */
	private string $physical;

	/**
	 * Hold the three forms.
	 *
	 * @param string $given    As the caller wrote it.
	 * @param string $real     Fully resolved.
	 * @param string $physical Absolute, before symlink resolution.
	 */
	public function __construct( string $given, string $real, string $physical ) {
		$this->given    = $given;
		$this->real     = $real;
		$this->physical = $physical;
	}

	/**
	 * As the caller wrote it.
	 *
	 * @return string
	 */
	public function given(): string {
		return $this->given;
	}

	/**
	 * Fully resolved.
	 *
	 * @return string
	 */
	public function real(): string {
		return $this->real;
	}

	/**
	 * Absolute, before symlink resolution.
	 *
	 * @return string
	 */
	public function physical(): string {
		return $this->physical;
	}

	/**
	 * Standard input in a pipeline. `given` is `-` when the caller named
	 * it and empty when the stage runner supplied it, which is the
	 * difference between `wc -l -` printing a name and `... | wc -l` not.
	 *
	 * @return bool
	 */
	public function is_standard_input(): bool {
		return Standard_Input::PATH === $this->real;
	}

	/**
	 * How GNU names standard input in prefixes and messages.
	 *
	 * @return string
	 */
	public function label(): string {
		return $this->is_standard_input() ? Standard_Input::DISPLAY : $this->given;
	}

	/**
	 * Join a child name the way GNU prints walked paths: `.` becomes
	 * `./name`, a trailing slash is not doubled.
	 *
	 * @param string $name A base name.
	 * @return self
	 */
	public function child( string $name ): self {
		$given = '/' === substr( $this->given, -1 ) ? $this->given . $name : $this->given . '/' . $name;

		return new self( $given, rtrim( $this->real, '/' ) . '/' . $name, rtrim( $this->physical, '/' ) . '/' . $name );
	}
}
