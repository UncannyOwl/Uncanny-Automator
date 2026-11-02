<?php
/**
 * Raw post meta rows and the core meta API calls that change them.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Infrastructure\Database\Interfaces;

/**
 * Reads throw Post_Meta_Store_Unavailable when the database does not answer, so a failed
 * query is never mistaken for a key without rows.
 */
interface Post_Meta_Store {

	/** Run the changes in one transaction while the post row is locked. */
	public function with_post_lock( int $post_id, callable $edit );

	/**
	 * Every row's ID, key and size, without values, in meta_id order.
	 *
	 * @return array<int, array{meta_id: int, meta_key: string, bytes: int}>
	 */
	public function key_index( int $post_id ): array;

	/**
	 * Rows whose key matches one of the keys under the column collation, in meta_id order.
	 * Callers compare keys byte for byte; the database may also match other case or accents.
	 *
	 * @param string[] $keys Requested keys.
	 * @param bool     $lock Lock the rows for this transaction.
	 * @return array<int, array{meta_id: int, meta_key: string, meta_value: string|null}>
	 */
	public function rows( int $post_id, array $keys, bool $lock = false ): array;

	/**
	 * Each matching row's ID, key and size, without values, under the column collation.
	 *
	 * @param string[] $keys Requested keys.
	 * @param bool     $lock Lock the rows for this transaction.
	 * @return array<int, array{meta_id: int, meta_key: string, bytes: int}>
	 */
	public function key_sizes( int $post_id, array $keys, bool $lock = false ): array;

	/**
	 * The first rows of one key spelled exactly so, without loading values larger than $max_bytes.
	 *
	 * @param int $max_bytes Largest value to load.
	 * @param int $limit     Rows to read.
	 * @return array<int, array{meta_id: int, meta_key: string, meta_value: string|null, bytes: int, omitted: bool}>
	 */
	public function preview_rows( int $post_id, string $key, int $max_bytes, int $limit ): array;

	/**
	 * Add one row through the core meta API.
	 *
	 * @param string|array $value Native value.
	 * @return int|false New meta ID.
	 */
	public function add( int $post_id, string $key, $value );

	/**
	 * Replace one row's value through the core meta API.
	 *
	 * @param string|array $value Native value.
	 */
	public function update( int $meta_id, $value ): bool;

	/** Delete one row through the core meta API. */
	public function delete( int $meta_id ): bool;

	/**
	 * Tell WordPress the post changed, as saving it in the editor does, so page
	 * caches, sitemaps and its last-modified date follow the new meta.
	 */
	public function touch_post( int $post_id ): bool;
}
