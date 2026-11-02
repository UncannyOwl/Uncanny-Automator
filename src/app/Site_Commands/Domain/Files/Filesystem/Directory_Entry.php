<?php
/**
 * One entry of a directory listing.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Site_Commands\Domain\Files\Filesystem;

/**
 * One entry of a directory listing, with its lstat information.
 */
final class Directory_Entry {

	/**
	 * The base name.
	 *
	 * @var string
	 */
	private string $name;

	/**
	 * The absolute path.
	 *
	 * @var string
	 */
	private string $path;

	/**
	 * Its lstat information.
	 *
	 * @var File_Info
	 */
	private File_Info $info;

	/**
	 * Hold one entry.
	 *
	 * @param string    $name The base name.
	 * @param string    $path The absolute path.
	 * @param File_Info $info Its lstat information.
	 */
	public function __construct( string $name, string $path, File_Info $info ) {
		$this->name = $name;
		$this->path = $path;
		$this->info = $info;
	}

	/**
	 * The base name.
	 *
	 * @return string
	 */
	public function name(): string {
		return $this->name;
	}

	/**
	 * The absolute path.
	 *
	 * @return string
	 */
	public function path(): string {
		return $this->path;
	}

	/**
	 * Its lstat information.
	 *
	 * @return File_Info
	 */
	public function info(): File_Info {
		return $this->info;
	}
}
