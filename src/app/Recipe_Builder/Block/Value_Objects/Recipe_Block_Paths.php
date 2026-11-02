<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Recipe_Builder\Block\Value_Objects;

/**
 * Ordered, type-narrowed collection of `Recipe_Block_Path` VOs.
 *
 * Order matters: the wire contract requires `paths[]` to match the
 * catalog's declared order at the same index, so the hydrator preserves
 * catalog sequence and the projection emits in this list's order.
 *
 * Constructor throws on any non-`Recipe_Block_Path` member rather than
 * silently filtering — caller misuse is louder that way.
 */
final class Recipe_Block_Paths {

	/**
	 * Ordered list of path VOs.
	 *
	 * @var array<int, Recipe_Block_Path>
	 */
	private $paths;

	/**
	 * Take the block's paths in catalog order, rejecting anything that is not a path.
	 *
	 * @param array<int, Recipe_Block_Path> $paths Ordered path VOs; reindexed to zero-based.
	 *
	 * @throws \InvalidArgumentException When any member is not a `Recipe_Block_Path`.
	 */
	public function __construct( array $paths ) {
		foreach ( $paths as $path ) {
			if ( ! $path instanceof Recipe_Block_Path ) {
				throw new \InvalidArgumentException(
					'Recipe_Block_Paths members must be Recipe_Block_Path instances'
				);
			}
		}
		$this->paths = array_values( $paths );
	}

	/**
	 * How many paths this block declares.
	 *
	 * @return int
	 */
	public function count(): int {
		return count( $this->paths );
	}

	/**
	 * Every path, in the catalog order the wire contract requires.
	 *
	 * @return array<int, Recipe_Block_Path>
	 */
	public function all(): array {
		return $this->paths;
	}

	/**
	 * Lookup the first path with the given `path_code`.
	 *
	 * @param string $path_code Catalog path code (uppercase).
	 * @return Recipe_Block_Path|null
	 */
	public function get_by_code( string $path_code ) {
		foreach ( $this->paths as $path ) {
			if ( $path->get_path_code() === $path_code ) {
				return $path;
			}
		}
		return null;
	}
}
