<?php
/**
 * A filesystem condition a file command reports.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Site_Commands\Domain\Files\Exception;

/**
 * A filesystem condition a command reports the way the shell would.
 *
 * The reason codes mirror errno names so the adapter can map real failures
 * and the in-memory fake can raise the same ones.
 */
final class Filesystem_Exception extends Coder_Exception {

	/** No such file or directory. */
	public const ENOENT = 'ENOENT';

	/** Permission denied. */
	public const EACCES = 'EACCES';

	/** Is a directory. */
	public const EISDIR = 'EISDIR';

	/** Not a directory. */
	public const ENOTDIR = 'ENOTDIR';

	/** The strerror text for each reason. */
	private const MESSAGES = array(
		self::ENOENT  => 'No such file or directory',
		self::EACCES  => 'Permission denied',
		self::EISDIR  => 'Is a directory',
		self::ENOTDIR => 'Not a directory',
	);

	/**
	 * The path that failed.
	 *
	 * @var string
	 */
	private string $path;

	/**
	 * One of the errno constants.
	 *
	 * @var string
	 */
	private string $reason;

	/**
	 * Record the condition.
	 *
	 * @param string $path   The path that failed.
	 * @param string $reason One of the errno constants.
	 */
	private function __construct( string $path, string $reason ) {
		$this->path   = $path;
		$this->reason = $reason;
		parent::__construct( self::MESSAGES[ $reason ] ?? $reason );
	}

	/**
	 * ENOENT.
	 *
	 * @param string $path The path that failed.
	 * @return self
	 */
	public static function not_found( string $path ): self {
		return new self( $path, self::ENOENT );
	}

	/**
	 * EACCES.
	 *
	 * @param string $path The path that failed.
	 * @return self
	 */
	public static function permission_denied( string $path ): self {
		return new self( $path, self::EACCES );
	}

	/**
	 * EISDIR.
	 *
	 * @param string $path The path that failed.
	 * @return self
	 */
	public static function is_a_directory( string $path ): self {
		return new self( $path, self::EISDIR );
	}

	/**
	 * ENOTDIR.
	 *
	 * @param string $path The path that failed.
	 * @return self
	 */
	public static function not_a_directory( string $path ): self {
		return new self( $path, self::ENOTDIR );
	}

	/**
	 * The path that failed.
	 *
	 * @return string
	 */
	public function path(): string {
		return $this->path;
	}

	/**
	 * One of the errno constants.
	 *
	 * @return string
	 */
	public function reason(): string {
		return $this->reason;
	}

	/**
	 * The strerror text GNU tools print after "command: path: ".
	 *
	 * @return string
	 */
	public function describe(): string {
		return $this->getMessage();
	}
}
