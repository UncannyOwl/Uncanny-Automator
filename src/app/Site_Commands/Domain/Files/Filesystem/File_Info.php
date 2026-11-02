<?php
/**
 * What stat knows about one path.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Site_Commands\Domain\Files\Filesystem;

/**
 * What `stat` knows about one path. Immutable.
 */
final class File_Info {

	/**
	 * The path.
	 *
	 * @var string
	 */
	private string $path;

	/**
	 * One of the File_Type constants.
	 *
	 * @var string
	 */
	private string $type;

	/**
	 * Size in bytes.
	 *
	 * @var int
	 */
	private int $size;

	/**
	 * Modification time.
	 *
	 * @var int
	 */
	private int $mtime;

	/**
	 * Access time.
	 *
	 * @var int
	 */
	private int $atime;

	/**
	 * Change time.
	 *
	 * @var int
	 */
	private int $ctime;

	/**
	 * The full st_mode.
	 *
	 * @var int
	 */
	private int $mode;

	/**
	 * Hard link count.
	 *
	 * @var int
	 */
	private int $nlink;

	/**
	 * Owner user ID.
	 *
	 * @var int
	 */
	private int $uid;

	/**
	 * Owner group ID.
	 *
	 * @var int
	 */
	private int $gid;

	/**
	 * Owner user name, when known.
	 *
	 * @var string|null
	 */
	private ?string $owner;

	/**
	 * Owner group name, when known.
	 *
	 * @var string|null
	 */
	private ?string $group;

	/**
	 * 512-byte blocks allocated.
	 *
	 * @var int
	 */
	private int $blocks;

	/**
	 * Inode number.
	 *
	 * @var int
	 */
	private int $inode;

	/**
	 * Device number.
	 *
	 * @var int
	 */
	private int $device;

	/**
	 * A symbolic link's target.
	 *
	 * @var string|null
	 */
	private ?string $link_target;

	/**
	 * Hold the stat fields.
	 *
	 * @param string      $path        The path.
	 * @param string      $type        One of the File_Type constants.
	 * @param int         $size        Size in bytes.
	 * @param int         $mtime       Modification time.
	 * @param int         $atime       Access time.
	 * @param int         $ctime       Change time.
	 * @param int         $mode        The full st_mode.
	 * @param int         $nlink       Hard link count.
	 * @param int         $uid         Owner user ID.
	 * @param int         $gid         Owner group ID.
	 * @param string|null $owner       Owner user name, when known.
	 * @param string|null $group       Owner group name, when known.
	 * @param int         $blocks      512-byte blocks allocated.
	 * @param int         $inode       Inode number.
	 * @param int         $device      Device number.
	 * @param string|null $link_target A symbolic link's target.
	 */
	public function __construct(
		string $path,
		string $type,
		int $size,
		int $mtime,
		int $atime,
		int $ctime,
		int $mode,
		int $nlink,
		int $uid,
		int $gid,
		?string $owner,
		?string $group,
		int $blocks,
		int $inode,
		int $device,
		?string $link_target = null
	) {
		$this->path        = $path;
		$this->type        = $type;
		$this->size        = $size;
		$this->mtime       = $mtime;
		$this->atime       = $atime;
		$this->ctime       = $ctime;
		$this->mode        = $mode;
		$this->nlink       = $nlink;
		$this->uid         = $uid;
		$this->gid         = $gid;
		$this->owner       = $owner;
		$this->group       = $group;
		$this->blocks      = $blocks;
		$this->inode       = $inode;
		$this->device      = $device;
		$this->link_target = $link_target;
	}

	/**
	 * The path.
	 *
	 * @return string
	 */
	public function path(): string {
		return $this->path;
	}

	/**
	 * One of the File_Type constants.
	 *
	 * @return string
	 */
	public function type(): string {
		return $this->type;
	}

	/**
	 * Size in bytes.
	 *
	 * @return int
	 */
	public function size(): int {
		return $this->size;
	}

	/**
	 * Modification time.
	 *
	 * @return int
	 */
	public function mtime(): int {
		return $this->mtime;
	}

	/**
	 * Access time.
	 *
	 * @return int
	 */
	public function atime(): int {
		return $this->atime;
	}

	/**
	 * Change time.
	 *
	 * @return int
	 */
	public function ctime(): int {
		return $this->ctime;
	}

	/**
	 * The full st_mode.
	 *
	 * @return int
	 */
	public function mode(): int {
		return $this->mode;
	}

	/**
	 * Hard link count.
	 *
	 * @return int
	 */
	public function nlink(): int {
		return $this->nlink;
	}

	/**
	 * Owner user ID.
	 *
	 * @return int
	 */
	public function uid(): int {
		return $this->uid;
	}

	/**
	 * Owner group ID.
	 *
	 * @return int
	 */
	public function gid(): int {
		return $this->gid;
	}

	/**
	 * Owner user name, when known.
	 *
	 * @return string|null
	 */
	public function owner(): ?string {
		return $this->owner;
	}

	/**
	 * Owner group name, when known.
	 *
	 * @return string|null
	 */
	public function group(): ?string {
		return $this->group;
	}

	/**
	 * 512-byte blocks allocated.
	 *
	 * @return int
	 */
	public function blocks(): int {
		return $this->blocks;
	}

	/**
	 * Inode number.
	 *
	 * @return int
	 */
	public function inode(): int {
		return $this->inode;
	}

	/**
	 * Device number.
	 *
	 * @return int
	 */
	public function device(): int {
		return $this->device;
	}

	/**
	 * A symbolic link's target.
	 *
	 * @return string|null
	 */
	public function link_target(): ?string {
		return $this->link_target;
	}

	/**
	 * Whether it is a directory.
	 *
	 * @return bool
	 */
	public function is_directory(): bool {
		return File_Type::DIRECTORY === $this->type;
	}

	/**
	 * Whether it is a regular file.
	 *
	 * @return bool
	 */
	public function is_file(): bool {
		return File_Type::FILE === $this->type;
	}

	/**
	 * Whether it is a symbolic link.
	 *
	 * @return bool
	 */
	public function is_link(): bool {
		return File_Type::LINK === $this->type;
	}

	/**
	 * The nine permission bits.
	 *
	 * @return int
	 */
	public function permissions(): int {
		return $this->mode & 0777;
	}

	/**
	 * The `-rw-r--r--` form `ls -l` and `stat` print.
	 *
	 * @return string
	 */
	public function permission_string(): string {
		$bits   = $this->mode;
		$string = File_Type::mode_char( $this->type );
		$groups = array(
			array( 0400, 0200, 0100, 04000, 's', 'S' ),
			array( 0040, 0020, 0010, 02000, 's', 'S' ),
			array( 0004, 0002, 0001, 01000, 't', 'T' ),
		);
		foreach ( $groups as list( $r, $w, $x, $special, $with_exec, $without_exec ) ) {
			$string .= ( $bits & $r ) ? 'r' : '-';
			$string .= ( $bits & $w ) ? 'w' : '-';
			if ( $bits & $special ) {
				$string .= ( $bits & $x ) ? $with_exec : $without_exec;
			} else {
				$string .= ( $bits & $x ) ? 'x' : '-';
			}
		}

		return $string;
	}

	/**
	 * The owner name, or the user ID when the name is unknown.
	 *
	 * @return string
	 */
	public function owner_name(): string {
		return $this->owner ?? (string) $this->uid;
	}

	/**
	 * The group name, or the group ID when the name is unknown.
	 *
	 * @return string
	 */
	public function group_name(): string {
		return $this->group ?? (string) $this->gid;
	}
}
