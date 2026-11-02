<?php
/**
 * One entry a directory walk yields.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Site_Commands\Domain\Files\Commands;

use Uncanny_Automator\App\Site_Commands\Domain\Files\Filesystem\File_Info;

/**
 * A walked path, its lstat information, and its depth below the start.
 */
final class Walk_Entry {

	/**
	 * The walked path.
	 *
	 * @var Resolved_Path
	 */
	private Resolved_Path $path;

	/**
	 * Its lstat information.
	 *
	 * @var File_Info
	 */
	private File_Info $info;

	/**
	 * Depth below the start; the start is 0.
	 *
	 * @var int
	 */
	private int $depth;

	/**
	 * Hold one entry.
	 *
	 * @param Resolved_Path $path  The walked path.
	 * @param File_Info     $info  Its lstat information.
	 * @param int           $depth Depth below the start.
	 */
	public function __construct( Resolved_Path $path, File_Info $info, int $depth ) {
		$this->path  = $path;
		$this->info  = $info;
		$this->depth = $depth;
	}

	/**
	 * The walked path.
	 *
	 * @return Resolved_Path
	 */
	public function path(): Resolved_Path {
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

	/**
	 * Depth below the start; the start is 0.
	 *
	 * @return int
	 */
	public function depth(): int {
		return $this->depth;
	}
}
