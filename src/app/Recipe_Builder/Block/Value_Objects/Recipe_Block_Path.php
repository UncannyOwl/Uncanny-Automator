<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Recipe_Builder\Block\Value_Objects;

/**
 * Recipe-instance block path.
 *
 * Carries a catalog-declared `path_code` plus the items routed through that
 * path on a specific recipe-instance block. Distinct from the catalog
 * `Block_Path` VO (which describes a path TYPE in the block catalog with
 * `name` / `direction` / `primary_color` / `unsupported_entities`).
 *
 * Items are heterogeneous (Action | Recipe_Block); the typed
 * union is enforced by the consuming hydrator + projection layer rather
 * than this VO so the path stays storage-shape-agnostic and recurses
 * cleanly.
 *
 * Path codes are uppercase by wire contract — frontend Zod requires it.
 * The constructor throws on empty / non-uppercase input rather than
 * silently coercing so misuse surfaces loud.
 */
final class Recipe_Block_Path {

	/**
	 * Catalog-declared path code, uppercase (e.g. `FILTER-CONDITIONS-MET`).
	 *
	 * @var string
	 */
	private $path_code;

	/**
	 * Heterogeneous items routed through this path. Reindexed to zero-based.
	 *
	 * @var array<int, mixed>
	 */
	private $items;

	/**
	 * Take a path code and the items routed through it, rejecting a malformed code.
	 *
	 * @param string             $path_code Uppercase catalog path code; throws on invalid input.
	 * @param array<int, mixed>  $items     Items routed through this path (any kind; reindexed).
	 *
	 * @throws \InvalidArgumentException When `path_code` is empty or contains lowercase characters.
	 */
	public function __construct( string $path_code, array $items ) {
		if ( '' === $path_code ) {
			throw new \InvalidArgumentException( 'Recipe_Block_Path: path_code must not be empty' );
		}
		if ( strtoupper( $path_code ) !== $path_code ) {
			throw new \InvalidArgumentException(
				'Recipe_Block_Path: path_code must be uppercase, got: ' . esc_html( $path_code )
			);
		}

		$this->path_code = $path_code;
		$this->items     = array_values( $items );
	}

	/**
	 * The catalog path code these items are routed under.
	 *
	 * @return string Uppercase path code.
	 */
	public function get_path_code(): string {
		return $this->path_code;
	}

	/**
	 * The items routed down this path, in presentation order.
	 *
	 * @return array<int, mixed> Action | Recipe_Block, zero-indexed.
	 */
	public function get_items(): array {
		return $this->items;
	}

	/**
	 * Whether this path has nothing routed down it.
	 *
	 * @return bool True when this path holds no items.
	 */
	public function is_empty(): bool {
		return empty( $this->items );
	}
}
