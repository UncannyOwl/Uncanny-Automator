<?php
/**
 * Read and change WordPress post meta without corrupting its stored form.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Recipe_Builder\Shared\Meta\Services;

use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Post_Meta_Store;
use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Post_Meta_Store_Unavailable;
use Uncanny_Automator\App\Infrastructure\Database\Stores\WP_Post_Meta_Store;
use Uncanny_Automator\App\Recipe_Builder\Shared\Meta\Utilities\Post_Meta_Credentials;
use Uncanny_Automator\App\Recipe_Builder\Shared\Meta\Utilities\Post_Meta_Patcher;
use Uncanny_Automator\App\Recipe_Builder\Shared\Meta\Utilities\Post_Meta_Value;

/**
 * The core meta API has traps this service closes before calling it:
 * - update_post_meta() rewrites every row of a key, so rows are targeted by meta_id.
 * - SQL matches keys ignoring case, accents and trailing spaces; PHP matches them
 *   exactly. A key that collides with a differently spelled stored key is refused.
 * - The write functions call maybe_unserialize() on the stored row, so rows that
 *   name PHP classes, or cannot be decoded, are never written or deleted.
 * - Serialized text would be serialized again, so arrays travel as JSON values.
 * - Top-level booleans, floats and null are stored lossily as text, so only exact
 *   text, integers and arrays are taken there.
 *
 * Reads follow the Custom Fields panel: anyone who can edit the post sees its
 * values, and protected values stay hidden unless WordPress lets the caller edit
 * that key. Keys named like credentials are never shown, fingerprinted or changed.
 * Refusals that do not depend on the hash come before it, so a write cannot be
 * used to test guesses at a hidden value.
 */
final class Post_Meta_Edit_Service {

	public const MAX_CHANGES  = 20;
	public const MAX_KEYS     = 50;
	public const MAX_PER_PAGE = 100;

	/** Bytes of JSON input accepted in one request. */
	private const MAX_INPUT_BYTES = 262144;

	/**
	 * Bounds of a key list: rows over load_bytes stay in the database, values over
	 * value_bytes are not shown, all values share budget, and each key shows at
	 * most entries rows.
	 */
	private const LIST_VIEW = array(
		'load_bytes' => 16384,
		'value_bytes' => 2048,
		'budget' => 65536,
		'entries' => 10,
	);

	/** Bounds of a key read, which loads every row so each key gets a hash. */
	private const KEY_VIEW = array(
		'load_bytes' => null,
		'value_bytes' => 262144,
		'budget' => 1048576,
		'entries' => 100,
	);

	/** Bounds of a stored value echoed after WordPress changed it. */
	private const RESULT_VIEW = array(
		'load_bytes' => null,
		'value_bytes' => 4096,
		'budget' => 4096,
		'entries' => 1,
	);

	/** Serialized rows larger than this are not decoded for writing; checking them could exhaust memory. */
	private const WRITE_DECODE_BYTES = 8388608;

	/** Rows and bytes of one key loaded whole, for a read or a change. */
	private const MAX_KEY_ROWS  = 1000;
	private const MAX_KEY_BYTES = 16777216;

	/** Bytes of all keys loaded whole by one read. */
	private const MAX_READ_BYTES = 33554432;

	private const OPERATIONS = array( 'set', 'patch', 'add', 'delete' );

	private Post_Meta_Store $store;
	private Post_Meta_Patcher $patcher;

	/** Whether the current apply() has started writing rows. */
	private bool $writing = false;

	/** Inject persistence for tests; production uses the current WordPress site. */
	public function __construct( ?Post_Meta_Store $store = null ) {
		global $wpdb;
		$this->store   = $store ?? new WP_Post_Meta_Store( $wpdb );
		$this->patcher = new Post_Meta_Patcher();
	}

	/** Require a signed-in user who can edit this post. */
	public function permissions_check( int $post_id ) {
		$post = $this->authorized( $post_id );
		return is_wp_error( $post ) ? $post : true;
	}

	/**
	 * List a post's keys, or read chosen keys in full.
	 *
	 * @param array $args keys, path, search, page, per_page.
	 */
	public function read( int $post_id, array $args = array() ) {
		$post = $this->authorized( $post_id );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		$base = array(
			'post_id' => (int) $post->ID,
			'post_type' => $post->post_type,
			'status' => $post->post_status,
		);
		if ( isset( $args['path'] ) && 1 !== count( (array) ( $args['keys'] ?? array() ) ) ) {
			return $this->error( 'post_meta_path_needs_one_key', 'A path reads inside one key. Send exactly one key in keys.', 400 );
		}
		try {
			if ( isset( $args['keys'] ) ) {
				$read = $this->read_keys( $post_id, $args );
				return is_wp_error( $read ) ? $read : $base + $read;
			}
			return $base + $this->list_keys( $post_id, $args );
		} catch ( Post_Meta_Store_Unavailable $error ) {
			return $this->error( 'post_meta_storage_unavailable', 'The database did not answer. Nothing was read; try again.', 503 );
		}
	}

	/**
	 * Apply ordered changes in one transaction; any refusal saves nothing.
	 *
	 * An error returned before the first row was written carries
	 * `nothing_saved` in its data: the request was refused and no meta hook ran.
	 *
	 * @param array $changes Each with op, key, expected_hash and the op's fields.
	 */
	public function apply( int $post_id, array $changes ) {
		$this->writing = false;
		$result        = $this->attempt( $post_id, $changes );

		return $this->writing ? $result : self::mark_nothing_saved( $result );
	}

	/**
	 * Validate and run the batch.
	 *
	 * @param array $changes Each with op, key, expected_hash and the op's fields.
	 */
	private function attempt( int $post_id, array $changes ) {
		$invalid = $this->invalid_changes( $changes );
		if ( null !== $invalid ) {
			return $invalid;
		}
		$post = $this->authorized( $post_id );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		try {
			$result = $this->store->with_post_lock( $post_id, $this->batch( $post_id, $changes ) );
		} catch ( Post_Meta_Store_Unavailable $error ) {
			// The lock rolls back before the exception leaves it.
			return $this->error( 'post_meta_storage_unavailable', 'The database did not answer. Nothing was saved; read the keys again before retrying.', 503 );
		}
		// Meta writes alone fire no post hooks, so page caches would keep serving the old page.
		if ( is_array( $result ) && 'saved' === ( $result['action'] ?? null ) && ! $this->store->touch_post( $post_id ) ) {
			$result['notice'] = trim( ( $result['notice'] ?? '' ) . ' The changes were saved, but WordPress did not mark the post as updated, so a page cache may still show the old page.' );
		}
		return $result;
	}

	/**
	 * The batch that runs under the post lock.
	 *
	 * @param array $changes Validated changes.
	 */
	private function batch( int $post_id, array $changes ): callable {
		return function () use ( $post_id, $changes ) {
				$post = $this->authorized( $post_id );
			if ( is_wp_error( $post ) ) {
				return $post;
			}
				$results = array();
			foreach ( $changes as $index => $change ) {
				$result = $this->apply_change( $post_id, $index, $change );
				if ( is_wp_error( $result ) ) {
					return $result;
				}
				$results[] = $result;
			}
				$saved  = array_filter(
					$results,
					static function ( array $result ): bool {
						return 'unchanged' !== $result['action'];
					}
				);
				$result = array(
					'post_id' => $post_id,
					'action' => $saved ? 'saved' : 'unchanged',
					'changes' => $results,
				);
				if ( in_array( 'changed_by_wordpress', array_column( $results, 'verification' ), true ) ) {
					$result['notice'] = 'WordPress stored a different value than requested for at least one key, usually through a registered sanitizer. Each change shows what was stored; read the key again before changing it.';
				}
				return $result;
		};
	}

	/** Route one validated change to its operation under the post lock. */
	private function apply_change( int $post_id, int $index, array $change ) {
		$key = $change['key'];
		// Every spelling the collation matches is loaded below, so all of them count.
		$sizes = $this->store->key_sizes( $post_id, array( $key ), true );
		if ( count( $sizes ) > self::MAX_KEY_ROWS || array_sum( array_column( $sizes, 'bytes' ) ) > self::MAX_KEY_BYTES ) {
			return $this->error( 'post_meta_too_large', sprintf( 'Key "%s" has more than %d rows or 16 MiB of values, too much to check safely in one change. It is left untouched.', $key, self::MAX_KEY_ROWS ), 413, $index );
		}
		$rows  = $this->store->rows( $post_id, array( $key ), true );
		$exact = $this->exact( $rows, $key );
		$other = array_values( array_unique( array_diff( array_column( $rows, 'meta_key' ), array( $key ) ) ) );
		if ( $other ) {
			return $this->error( 'post_meta_key_collision', sprintf( 'The database treats key "%s" as the same key as stored key "%s", ignoring letter case, accents or trailing spaces. Nothing was saved. Use the stored key exactly.', $key, $other[0] ), 409, $index );
		}
		// Refusals that do not depend on the hash come first, so a wrong hash never
		// confirms or rules out a guess at a value the caller may not read.
		$allowed = $this->capable( $this->capability( $change['op'], $exact ), $post_id, $key, $index );
		if ( is_wp_error( $allowed ) ) {
			return $allowed;
		}
		if ( Post_Meta_Credentials::is_credential_key( $key ) ) {
			return $this->credential_error( $key, $index );
		}
		if ( ! hash_equals( Post_Meta_Value::hash( $exact ), $change['expected_hash'] ) ) {
			return $this->error( 'post_meta_stale', sprintf( 'Key "%s" changed since it was read. Nothing was saved. Read it again before preparing this change.', $key ), 409, $index );
		}
		switch ( $change['op'] ) {
			case 'set':
				return $this->set( $post_id, $index, $change, $exact );
			case 'patch':
				return $this->patch( $post_id, $index, $change, $exact );
			case 'add':
				return $this->add( $post_id, $index, $change );
			default:
				return $this->delete( $post_id, $index, $change, $exact );
		}
	}

	/** Replace a whole value, keeping its stored form unless change_type is set. */
	private function set( int $post_id, int $index, array $change, array $exact ) {
		$row = $this->target_row( $exact, $change, $index );
		if ( is_wp_error( $row ) ) {
			return $row;
		}
		$native = Post_Meta_Value::top_level( $change['value'] );
		$shape  = Post_Meta_Value::shape( $native );
		if ( null === $row ) {
			if ( Post_Meta_Value::SERIALIZED_STRING === $shape ) {
				return $this->serialized_input( $index );
			}
			return $this->add_row( $post_id, $index, $change, $native );
		}
		$current = $this->writable( $row, $index );
		if ( is_wp_error( $current ) ) {
			return $current;
		}
		$kind = Post_Meta_Value::kind( $current['stored_as'] );
		if ( Post_Meta_Value::SERIALIZED_STRING === $shape && Post_Meta_Value::SERIALIZED_STRING !== $kind ) {
			return $this->serialized_input( $index );
		}
		if ( $shape !== $kind && true !== ( $change['change_type'] ?? false ) ) {
			return $this->error( 'post_meta_shape_mismatch', sprintf( 'Key "%s" is stored as %s, and this value would be stored as %s. Plugins that read this key may break. Nothing was saved. Send a value of the stored form, or change_type: true to change it deliberately.', $change['key'], $current['stored_as'], $shape ), 409, $index );
		}
		return $this->update_row( $post_id, $index, $change, $row, $native );
	}

	/** Change paths inside a stored array; everything else keeps its PHP types. */
	private function patch( int $post_id, int $index, array $change, array $exact ) {
		$row = $this->target_row( $exact, $change, $index );
		if ( is_wp_error( $row ) ) {
			return $row;
		}
		if ( null === $row ) {
			return $this->error( 'post_meta_not_found', sprintf( 'Key "%s" has no value to patch. Use set to create it.', $change['key'] ), 404, $index );
		}
		$current = $this->writable( $row, $index );
		if ( is_wp_error( $current ) ) {
			return $current;
		}
		if ( Post_Meta_Value::SERIALIZED_ARRAY !== $current['stored_as'] ) {
			return $this->error( 'post_meta_not_array', sprintf( 'Key "%s" is stored as %s. Only serialized arrays can be patched by path; use set for other values.', $change['key'], $current['stored_as'] ), 409, $index );
		}
		if ( ! empty( $current['references'] ) ) {
			return $this->error( 'post_meta_references', sprintf( 'Key "%s" holds PHP references, where changing one place changes another. It cannot be patched by path; use set to replace the whole value.', $change['key'] ), 409, $index );
		}
		$patched = $this->patcher->apply( $current['value'], $change['edits'] );
		if ( is_wp_error( $patched ) ) {
			$patched->add_data( array_merge( (array) $patched->get_error_data(), array( 'change_index' => $index ) ) );
			return $patched;
		}
		return $this->update_row( $post_id, $index, $change, $row, $patched );
	}

	/** Add another row, for keys that hold several values. */
	private function add( int $post_id, int $index, array $change ) {
		$native = Post_Meta_Value::top_level( $change['value'] );
		if ( Post_Meta_Value::SERIALIZED_STRING === Post_Meta_Value::shape( $native ) ) {
			return $this->serialized_input( $index );
		}
		return $this->add_row( $post_id, $index, $change, $native );
	}

	/** Delete one row by meta_id, or every row of the key that was read. */
	private function delete( int $post_id, int $index, array $change, array $exact ) {
		$rows = $exact;
		if ( isset( $change['meta_id'] ) ) {
			$row = $this->target_row( $exact, $change, $index );
			if ( is_wp_error( $row ) ) {
				return $row;
			}
			$rows = array( $row );
		}
		if ( array() === $rows ) {
			return $this->error( 'post_meta_not_found', sprintf( 'Key "%s" has no value to delete.', $change['key'] ), 404, $index );
		}
		foreach ( $rows as $row ) {
			$current = $this->writable( $row, $index );
			if ( is_wp_error( $current ) ) {
				return $current;
			}
		}
		$ids = array_column( $rows, 'meta_id' );
		foreach ( $ids as $meta_id ) {
			$this->writing = true;
			if ( ! $this->store->delete( $meta_id ) ) {
				return $this->not_saved( $change['key'], $index );
			}
		}
		$left = $this->exact( $this->store->rows( $post_id, array( $change['key'] ), true ), $change['key'] );
		if ( array_intersect( $ids, array_column( $left, 'meta_id' ) ) ) {
			return $this->not_saved( $change['key'], $index );
		}
		return array(
			'index' => $index,
			'op' => 'delete',
			'key' => $change['key'],
			'action' => 'deleted',
			'meta_ids' => $ids,
			'verification' => 'exact',
			'rows' => count( $left ),
			'hash' => Post_Meta_Value::hash( $left ),
		);
	}

	/**
	 * Add a row through core and confirm what it stored.
	 *
	 * @param string|array $native Native value.
	 */
	private function add_row( int $post_id, int $index, array $change, $native ) {
		$this->writing = true;
		$meta_id       = $this->store->add( $post_id, $change['key'], $native );
		if ( ! $meta_id ) {
			return $this->not_saved( $change['key'], $index );
		}
		return $this->verified( $post_id, $index, $change, 'added', (int) $meta_id, $native, null );
	}

	/**
	 * Update one row through core and confirm what it stored.
	 *
	 * @param string|array $native Native value.
	 */
	private function update_row( int $post_id, int $index, array $change, array $row, $native ) {
		if ( Post_Meta_Value::stored_form( $native ) === $row['meta_value'] ) {
			return $this->outcome( $index, $change, 'unchanged', $row, $this->current_rows( $post_id, $change['key'] ), 'exact' );
		}
		// False here means nothing changed: a filter refused the write, or a sanitizer
		// reduced the request to the stored value. The readback tells them apart.
		$this->writing = true;
		$this->store->update( $row['meta_id'], $native );
		return $this->verified( $post_id, $index, $change, 'updated', $row['meta_id'], $native, $row['meta_value'] );
	}

	/**
	 * Read the row back and compare it with the stored form core was asked to write.
	 *
	 * @param string|array $native Native value.
	 */
	private function verified( int $post_id, int $index, array $change, string $action, int $meta_id, $native, ?string $previous ) {
		$rows  = $this->current_rows( $post_id, $change['key'] );
		$saved = null;
		foreach ( $rows as $row ) {
			if ( $meta_id === $row['meta_id'] ) {
				$saved = $row;
			}
		}
		if ( null === $saved ) {
			return $this->not_saved( $change['key'], $index );
		}
		if ( 'updated' === $action && $saved['meta_value'] === $previous ) {
			// A registered sanitizer, such as trim, can map the request to the stored value.
			$sanitized = sanitize_meta( $change['key'], $native, 'post', (string) get_object_subtype( 'post', $post_id ) );
			if ( Post_Meta_Value::stored_form( $sanitized ) !== $previous ) {
				return $this->not_saved( $change['key'], $index );
			}
			return $this->outcome( $index, $change, 'unchanged', $saved, $rows, 'changed_by_wordpress' );
		}
		$exact = Post_Meta_Value::stored_form( $native ) === $saved['meta_value'];
		return $this->outcome( $index, $change, $action, $saved, $rows, $exact ? 'exact' : 'changed_by_wordpress' );
	}

	/** Describe one completed change with the key's new fingerprint. */
	private function outcome( int $index, array $change, string $action, array $row, array $rows, string $verification ): array {
		$result = array(
			'index' => $index,
			'op' => $change['op'],
			'key' => $change['key'],
			'action' => $action,
			'meta_id' => $row['meta_id'],
			'verification' => $verification,
			'stored_as' => Post_Meta_Value::decode( $row['meta_value'], self::WRITE_DECODE_BYTES )['stored_as'],
			'rows' => count( $rows ),
			'hash' => Post_Meta_Value::hash( $rows ),
		);
		if ( 'changed_by_wordpress' === $verification ) {
			$budget = self::RESULT_VIEW['budget'];
			$entry  = $this->describe_row( $row, null, self::RESULT_VIEW, $budget );
			if ( array_key_exists( 'value', $entry ) ) {
				$result['value'] = $entry['value'];
			} else {
				$result['value_unavailable'] = $entry['value_unavailable'];
			}
		}
		return $result;
	}

	/** The key's rows, re-read under the lock. */
	private function current_rows( int $post_id, string $key ): array {
		return $this->exact( $this->store->rows( $post_id, array( $key ), true ), $key );
	}

	/**
	 * Pick the row a change applies to: meta_id when given, else the key's only row.
	 *
	 * @return array|null|\WP_Error Null when the key has no rows.
	 */
	private function target_row( array $exact, array $change, int $index ) {
		if ( isset( $change['meta_id'] ) ) {
			foreach ( $exact as $row ) {
				if ( (int) $change['meta_id'] === $row['meta_id'] ) {
					return $row;
				}
			}
			return $this->error( 'post_meta_row_not_found', sprintf( 'Key "%s" has no row with meta_id %d. Read the key again.', $change['key'], (int) $change['meta_id'] ), 404, $index );
		}
		if ( count( $exact ) > 1 ) {
			return $this->error( 'post_meta_multiple_rows', sprintf( 'Key "%s" has %d rows. Send meta_id to choose one; WordPress would otherwise overwrite every row with the same value.', $change['key'], count( $exact ) ), 409, $index );
		}
		return $exact[0] ?? null;
	}

	/**
	 * Decode a row that is about to be written or deleted. Core's write functions
	 * decode it again with maybe_unserialize(), so only rows proven free of PHP
	 * objects reach them.
	 *
	 * @return array|\WP_Error
	 */
	private function writable( array $row, int $index ) {
		$current = Post_Meta_Value::decode( $row['meta_value'], self::WRITE_DECODE_BYTES );
		if ( false === ( $current['inspected'] ?? true ) ) {
			return $this->error( 'post_meta_too_large', sprintf( 'Key "%s" holds serialized data over 8 MiB, too large to check safely before WordPress decodes it. It is left untouched.', $row['meta_key'] ), 413, $index );
		}
		if ( Post_Meta_Value::PHP_OBJECT === $current['stored_as'] ) {
			return $this->error( 'post_meta_php_object', sprintf( 'Key "%s" stores PHP objects. Only the plugin that owns it can change it safely; this tool never decodes PHP objects.', $row['meta_key'] ), 409, $index );
		}
		if ( Post_Meta_Value::INVALID_SERIALIZED === $current['stored_as'] ) {
			return $this->error( 'post_meta_invalid_serialized', sprintf( 'Key "%s" holds damaged serialized data that WordPress cannot read. It is left untouched; repair it from a backup or with the plugin that owns it.', $row['meta_key'] ), 409, $index );
		}
		if ( 'too_complex' === ( $current['unavailable'] ?? null ) ) {
			return $this->error( 'post_meta_too_complex', sprintf( 'Key "%s" holds serialized data too large or self-referencing to check completely. It is left untouched.', $row['meta_key'] ), 409, $index );
		}
		return $current;
	}

	/**
	 * The capability a change needs, as core checks it for the same edit.
	 *
	 * @param array $exact The key's rows.
	 */
	private function capability( string $op, array $exact ): string {
		if ( 'delete' === $op ) {
			return 'delete_post_meta';
		}
		return 'add' === $op || ( 'set' === $op && array() === $exact ) ? 'add_post_meta' : 'edit_post_meta';
	}

	/** Native meta capabilities, including protected keys and registered auth callbacks. */
	private function capable( string $capability, int $post_id, string $key, int $index ) {
		if ( current_user_can( $capability, $post_id, $key ) ) {
			return true;
		}
		$message = is_protected_meta( $key, 'post' )
			? sprintf( 'Key "%s" starts with an underscore, so WordPress protects it. Only a plugin that registers the key with permission to edit it can allow changes. Use that plugin\'s own tools; for a featured image or page template, update the post\'s featured_media or template field through its REST route.', $key )
			: sprintf( 'You cannot change key "%s" on this post.', $key );
		return $this->error( 'post_meta_forbidden', $message, 403, $index );
	}

	/** List keys with their sizes and short values, paged. */
	private function list_keys( int $post_id, array $args ): array {
		$groups = array();
		foreach ( $this->store->key_index( $post_id ) as $row ) {
			$groups[ $row['meta_key'] ][] = $row;
		}
		$total  = count( $groups );
		$search = (string) ( $args['search'] ?? '' );
		if ( '' !== $search ) {
			$groups = array_filter(
				$groups,
				static function ( $key ) use ( $search ): bool {
					return false !== stripos( (string) $key, $search );
				},
				ARRAY_FILTER_USE_KEY
			);
		}
		$per_page = max( 1, min( self::MAX_PER_PAGE, (int) ( $args['per_page'] ?? 50 ) ) );
		$page     = max( 1, (int) ( $args['page'] ?? 1 ) );
		$budget   = self::LIST_VIEW['budget'];
		$keys     = array();
		foreach ( array_slice( $groups, ( $page - 1 ) * $per_page, $per_page, true ) as $key => $index ) {
			// Numeric keys such as "123" become integer array keys; restore them as text.
			$key    = (string) $key;
			$rows   = $this->store->preview_rows( $post_id, $key, self::LIST_VIEW['load_bytes'], self::LIST_VIEW['entries'] );
			$keys[] = $this->describe_key( $post_id, $key, $this->exact( $rows, $key ), count( $index ), self::LIST_VIEW, $budget );
		}
		$result  = array(
			'total_keys' => $total,
			'matching_keys' => count( $groups ),
			'shown_keys' => count( $keys ),
			'page' => $page,
			'per_page' => $per_page,
			'total_pages' => max( 1, (int) ceil( count( $groups ) / $per_page ) ),
			'keys' => $keys,
		);
		$notices = array();
		if ( $this->omitted( $keys, 'too_large' ) ) {
			$notices[] = 'Values over 2 KiB are not listed. Read those keys with keys, or with keys and path for one part of a large array.';
		}
		if ( in_array( null, array_column( $keys, 'hash' ), true ) ) {
			$notices[] = 'Keys with hash null have values this list does not load or show. Read a key with keys before changing it.';
		}
		if ( $notices ) {
			$result['notice'] = implode( ' ', $notices );
		}
		return $result;
	}

	/** Read chosen keys in full, or one path inside one key. */
	private function read_keys( int $post_id, array $args ) {
		$keys = array();
		foreach ( (array) $args['keys'] as $key ) {
			if ( ! is_string( $key ) || null !== $this->invalid_key( $key ) ) {
				return $this->error( 'post_meta_key_invalid', 'Each key must be 1 to 255 characters of visible text, without control or invisible characters or surrounding spaces.', 400 );
			}
			$keys[] = $key;
		}
		$keys = array_values( array_unique( $keys ) );
		if ( array() === $keys || count( $keys ) > self::MAX_KEYS ) {
			return $this->error( 'post_meta_key_invalid', 'Send between 1 and ' . self::MAX_KEYS . ' keys.', 400 );
		}
		$budget  = self::KEY_VIEW['budget'];
		$loaded  = 0;
		$read    = array();
		$whole   = array();
		$partial = array();
		foreach ( $keys as $key ) {
			// Loading reads every spelling the collation matches, so all of them count.
			$matched = $this->store->key_sizes( $post_id, array( $key ) );
			$size    = $this->exact( $matched, $key );
			$bytes   = array_sum( array_column( $matched, 'bytes' ) );
			if ( count( $matched ) <= self::MAX_KEY_ROWS && $bytes <= self::MAX_KEY_BYTES && $loaded + $bytes <= self::MAX_READ_BYTES ) {
				$loaded       += $bytes;
				$whole[ $key ] = $this->exact( $this->store->rows( $post_id, array( $key ) ), $key );
				$rows          = $whole[ $key ];
			} else {
				$partial[] = $key;
				$rows      = $this->exact( $this->store->preview_rows( $post_id, $key, self::LIST_VIEW['load_bytes'], self::KEY_VIEW['entries'] ), $key );
			}
			$described = $this->describe_key( $post_id, $key, $rows, count( $size ), self::KEY_VIEW, $budget );
			if ( ! isset( $whole[ $key ] ) ) {
				// A key not loaded whole cannot be changed here, so it gets no hash at all.
				$described['hash']     = null;
				$described['can_edit'] = false;
			}
			$read[] = $described;
		}
		$result = array( 'keys' => $read );
		if ( isset( $args['path'] ) ) {
			if ( ! isset( $whole[ $keys[0] ] ) ) {
				return $this->error( 'post_meta_too_large', sprintf( 'Key "%s" has more than %d rows or 16 MiB of values, too much to read whole.', $keys[0], self::MAX_KEY_ROWS ), 413 );
			}
			$found = $this->read_path( $post_id, $keys[0], $whole[ $keys[0] ], $args['path'] );
			if ( is_wp_error( $found ) ) {
				return $found;
			}
			$result['at_path'] = $found;
		} elseif ( $this->omitted( $read, 'too_large' ) ) {
			$result['notice'] = 'Values over 256 KiB are not returned whole. Read one part of a large array with path, or replace the value without reading it by sending its hash.';
		}
		if ( $partial ) {
			$result['notice'] = trim( ( $result['notice'] ?? '' ) . sprintf( ' Keys %s have more than %d rows or 16 MiB of values, or exceed 32 MiB together with the keys before them. They show their first rows with hash null and cannot be changed here; read them on their own.', wp_json_encode( $partial ), self::MAX_KEY_ROWS ) );
		}
		return $result;
	}

	/** One value inside a stored array. */
	private function read_path( int $post_id, string $key, array $rows, $path ) {
		if ( Post_Meta_Credentials::is_credential_key( $key ) ) {
			return $this->credential_error( $key );
		}
		if ( is_protected_meta( $key, 'post' ) && ! current_user_can( 'edit_post_meta', $post_id, $key ) ) {
			return $this->error( 'post_meta_protected', sprintf( 'Key "%s" is protected by WordPress, so its value is shown only to callers who may edit it.', $key ), 403 );
		}
		if ( 1 !== count( $rows ) ) {
			return $this->error( 'post_meta_path_needs_one_row', sprintf( 'A path reads inside a key with exactly one row; key "%s" has %d.', $key, count( $rows ) ), 409 );
		}
		$current = Post_Meta_Value::decode( $rows[0]['meta_value'], self::WRITE_DECODE_BYTES );
		if ( Post_Meta_Value::SERIALIZED_ARRAY !== $current['stored_as'] || ! array_key_exists( 'value', $current ) ) {
			return $this->error( 'post_meta_not_array', sprintf( 'Key "%s" is stored as %s, so it has no readable paths.', $key, $current['stored_as'] ), 409 );
		}
		$found = $this->patcher->get( $current['value'], $path );
		if ( is_wp_error( $found ) ) {
			return $found;
		}
		$encoded = wp_json_encode( $found[0] );
		// Part of an array can hold text JSON cannot carry even when the rest can.
		if ( null !== Post_Meta_Value::invalid_input( $found[0] ) || false === $encoded ) {
			return $this->error( 'post_meta_value_unavailable', 'The value at this path cannot be carried as JSON.', 409 );
		}
		if ( strlen( $encoded ) > self::KEY_VIEW['value_bytes'] ) {
			return $this->error( 'post_meta_value_too_large', 'The value at this path is over 256 KiB. Read a deeper path.', 413 );
		}
		return array(
			'key' => $key,
			'meta_id' => $rows[0]['meta_id'],
			'path' => $path,
			'value' => $found[0],
		);
	}

	/**
	 * One key's rows, fingerprint and permission, with values within the view's bounds.
	 * A key whose values are withheld or not loaded gets no hash: a fingerprint of
	 * a short secret can be reversed by guessing.
	 *
	 * @param int $budget Remaining bytes of values for this response.
	 */
	private function describe_key( int $post_id, string $key, array $rows, int $total, array $view, int &$budget ): array {
		$protected = is_protected_meta( $key, 'post' );
		$can_edit  = current_user_can( $total > 0 ? 'edit_post_meta' : 'add_post_meta', $post_id, $key );
		$private   = null;
		if ( Post_Meta_Credentials::is_credential_key( $key ) ) {
			$private = 'credential';
		} elseif ( $protected && ! $can_edit ) {
			$private = 'protected';
		}
		// A list stops at its entry limit; a key read checks every row for credentials.
		$checked  = null === $view['load_bytes'] ? $rows : array_slice( $rows, 0, $view['entries'] );
		$complete = count( $checked ) === $total;
		$entries  = array();
		$hidden   = null !== $private;
		foreach ( $checked as $row ) {
			$entry    = $this->describe_row( $row, $private, $view, $budget );
			$hidden   = $hidden || 'credential' === ( $entry['value_unavailable'] ?? null );
			$complete = $complete && empty( $row['omitted'] );
			if ( count( $entries ) < $view['entries'] ) {
				$entries[] = $entry;
			}
		}
		$result = array(
			'key' => $key,
			'exists' => $total > 0,
			'rows' => $total,
			'hash' => $hidden || ! $complete ? null : Post_Meta_Value::hash( $rows ),
			'protected' => $protected,
			'can_edit' => $can_edit && ! $hidden,
			'entries' => $entries,
		);
		if ( $total > count( $entries ) ) {
			$result['entries_truncated'] = true;
		}
		return $result;
	}

	/**
	 * One row's stored form, size and, when it fits and may be shown, its value.
	 *
	 * @param string|null $private Why every value of this key is withheld, if it is.
	 * @param int         $budget  Remaining bytes of values for this response.
	 */
	private function describe_row( array $row, ?string $private, array $view, int &$budget ): array {
		$entry = array( 'meta_id' => $row['meta_id'] );
		if ( null !== $private ) {
			$entry['value_unavailable'] = $private;
			return $entry;
		}
		$entry['bytes'] = $row['bytes'] ?? ( null === $row['meta_value'] ? 0 : strlen( $row['meta_value'] ) );
		if ( ! empty( $row['omitted'] ) ) {
			$entry['value_unavailable'] = 'too_large';
			return $entry;
		}
		// JSON of a decoded value is rarely under a quarter of its serialized size.
		$decoded            = Post_Meta_Value::decode( $row['meta_value'], 4 * $view['value_bytes'] );
		$entry['stored_as'] = $decoded['stored_as'];
		if ( ! empty( $decoded['references'] ) ) {
			$entry['references'] = true;
		}
		if ( false === ( $decoded['inspected'] ?? true ) ) {
			$entry['inspected'] = false;
		}
		$reason = $decoded['unavailable'] ?? null;
		if ( null === $reason && Post_Meta_Value::TEXT === $decoded['stored_as'] && $entry['bytes'] > $view['value_bytes'] ) {
			$reason = 'too_large';
		}
		if ( null !== $reason ) {
			$entry['value_unavailable'] = $reason;
			return $entry;
		}
		$encoded = wp_json_encode( $decoded['value'] );
		$size    = false === $encoded ? PHP_INT_MAX : strlen( $encoded );
		if ( $size > $view['value_bytes'] || $size > $budget ) {
			$entry['value_unavailable'] = 'too_large';
			return $entry;
		}
		$budget        -= $size;
		$entry['value'] = $decoded['value'];
		if ( Post_Meta_Value::TEXT === $decoded['stored_as'] && preg_match( '/^\s*[\[{]/', $decoded['value'] ) && is_array( json_decode( $decoded['value'], true ) ) ) {
			$entry['text_format'] = 'json';
		}
		return $entry;
	}

	/** Whether any listed row omitted its value for this reason. */
	private function omitted( array $keys, string $reason ): bool {
		foreach ( $keys as $key ) {
			if ( in_array( $reason, array_column( $key['entries'], 'value_unavailable' ), true ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Rows whose key equals this one byte for byte.
	 *
	 * @return array<int, array{meta_id: int, meta_key: string, meta_value: string|null}>
	 */
	private function exact( array $rows, string $key ): array {
		return array_values(
			array_filter(
				$rows,
				static function ( array $row ) use ( $key ): bool {
					return $key === $row['meta_key'];
				}
			)
		);
	}

	/** Validate the whole request before taking the lock. */
	private function invalid_changes( array $changes ): ?\WP_Error {
		$input = wp_json_encode( $changes );
		if ( false === $input || strlen( $input ) > self::MAX_INPUT_BYTES ) {
			return $this->error( 'post_meta_edit_too_large', 'Changes exceed the 256 KiB input limit.', 413 );
		}
		if ( array() === $changes || count( $changes ) > self::MAX_CHANGES || array_keys( $changes ) !== range( 0, count( $changes ) - 1 ) ) {
			return $this->error( 'post_meta_edit_invalid', 'Send between 1 and ' . self::MAX_CHANGES . ' changes.', 400 );
		}
		$seen = array();
		foreach ( $changes as $index => $change ) {
			$reason = $this->invalid_change( $change );
			if ( null !== $reason ) {
				return $this->error( 'post_meta_edit_invalid', $reason, 400, $index );
			}
			if ( in_array( $change['key'], $seen, true ) ) {
				return $this->error( 'post_meta_edit_invalid', 'Each key may appear once per request, because its hash changes after the first change. Send one change per key.', 400, $index );
			}
			if ( Post_Meta_Credentials::is_credential_key( $change['key'] ) ) {
				return $this->credential_error( $change['key'], $index );
			}
			$seen[] = $change['key'];
		}
		return null;
	}

	/**
	 * Why one change is malformed, or null.
	 *
	 * @param mixed $change Candidate change.
	 */
	private function invalid_change( $change ): ?string {
		if ( ! is_array( $change ) || ! in_array( $change['op'] ?? null, self::OPERATIONS, true ) ) {
			return 'Each change needs op: set, patch, add or delete.';
		}
		$fields = array(
			'set' => array( 'value', 'meta_id', 'change_type' ),
			'patch' => array( 'edits', 'meta_id' ),
			'add' => array( 'value' ),
			'delete' => array( 'meta_id' ),
		);
		if ( array_diff( array_keys( $change ), array_merge( array( 'op', 'key', 'expected_hash' ), $fields[ $change['op'] ] ) ) ) {
			return sprintf( '%s takes only key, expected_hash and %s.', $change['op'], implode( ', ', $fields[ $change['op'] ] ) );
		}
		if ( ! is_string( $change['key'] ?? null ) ) {
			return 'Each change needs a key.';
		}
		$reason = $this->invalid_key( $change['key'] );
		if ( null !== $reason ) {
			return $reason;
		}
		if ( ! is_string( $change['expected_hash'] ?? null ) || 1 !== preg_match( '/^[a-f0-9]{64}$/D', $change['expected_hash'] ) ) {
			return 'Each change needs expected_hash: the key\'s hash from a fresh read. A key that does not exist yet has hash ' . Post_Meta_Value::ABSENT_HASH . '.';
		}
		if ( isset( $change['meta_id'] ) && ( ! is_int( $change['meta_id'] ) || $change['meta_id'] < 1 ) ) {
			return 'meta_id must be a positive integer from a read of this key.';
		}
		if ( isset( $change['change_type'] ) && ! is_bool( $change['change_type'] ) ) {
			return 'change_type must be true or false.';
		}
		if ( 'patch' === $change['op'] ) {
			return is_array( $change['edits'] ?? null ) && array() !== $change['edits'] ? null : 'patch needs edits: a list of {path, value} or {path, unset: true}.';
		}
		if ( 'delete' === $change['op'] ) {
			return null;
		}
		if ( ! array_key_exists( 'value', $change ) || null === Post_Meta_Value::top_level( $change['value'] ) ) {
			return 'value must be text, an integer, an array or an object. WordPress stores other top-level values as text: true becomes "1", false and null become empty, and decimals may be rounded. Send the exact text instead, such as "1", "0" or "0.25".';
		}
		$reason = Post_Meta_Value::invalid_input( $change['value'] );
		return null === $reason ? null : sprintf( 'value cannot be stored (%s). Values may nest up to %d levels of valid UTF-8 text, numbers, booleans and null.', $reason, Post_Meta_Value::MAX_DEPTH );
	}

	/** Why a key cannot be stored and matched exactly, or null. */
	private function invalid_key( string $key ): ?string {
		if ( '' === $key || 1 !== preg_match( '//u', $key ) || mb_strlen( $key ) > 255 ) {
			return 'Each key must be 1 to 255 characters of valid UTF-8 text.';
		}
		// The database ignores trailing spaces, and invisible or special spaces make
		// keys that look, and may match, like another key.
		if ( 1 === preg_match( '/[\p{Cc}\p{Cf}\p{Zl}\p{Zp}]|(?!\x20)\p{Zs}/u', $key ) || trim( $key ) !== $key ) {
			return 'Keys cannot contain control characters, invisible characters or special spaces, or start or end with a space.';
		}
		return null;
	}

	/**
	 * Load an editable post whose meta this tool may change: a type shown in the
	 * REST API, as content editing requires.
	 *
	 * @return \WP_Post|\WP_Error
	 */
	private function authorized( int $post_id ) {
		if ( ! is_user_logged_in() ) {
			return $this->error( 'rest_forbidden', 'Sign in before reading or changing post meta.', 401 );
		}
		$post = get_post( $post_id );
		if ( ! $post instanceof \WP_Post || $post_id <= 0 ) {
			return $this->error( 'rest_post_invalid_id', 'Post not found.', 404 );
		}
		$type = get_post_type_object( $post->post_type );
		// Core's add_post_meta() moves revision meta to the parent post.
		if ( wp_is_post_revision( $post ) || ! $type || ! $type->show_in_rest || in_array( $post->post_type, $this->excluded_types(), true ) ) {
			return $this->error( 'post_meta_unsupported', 'This post type\'s meta is not available here: only post types shown in the REST API are, revisions follow their parent post, and Automator recipes use the recipe tools.', 400 );
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return $this->error( 'rest_forbidden', 'You cannot edit this post.', 403 );
		}
		return $post;
	}

	/** Internal post types whose meta belongs to another owner. */
	private function excluded_types(): array {
		return array( 'revision', 'customize_changeset', 'oembed_cache', 'user_request', AUTOMATOR_POST_TYPE_RECIPE, AUTOMATOR_POST_TYPE_TRIGGER, AUTOMATOR_POST_TYPE_ACTION, AUTOMATOR_POST_TYPE_CLOSURE, AUTOMATOR_POST_TYPE_LOOP, AUTOMATOR_POST_TYPE_LOOP_FILTER );
	}

	/** Credential-like keys, and values holding them, are never read or changed here. */
	private function credential_error( string $key, ?int $index = null ): \WP_Error {
		return $this->error( 'post_meta_credential_key', sprintf( 'Key "%s" is named like a credential. This tool does not read or change credentials.', $key ), 403, $index );
	}

	/** Serialized text would be serialized a second time. */
	private function serialized_input( int $index ): \WP_Error {
		return $this->error( 'post_meta_serialized_input', 'This text looks like PHP serialized data, which WordPress would store serialized a second time. Nothing was saved. Send arrays and objects as JSON values; WordPress serializes them.', 400, $index );
	}

	/** The database kept the previous value. */
	private function not_saved( string $key, int $index ): \WP_Error {
		return $this->error( 'post_meta_not_saved', sprintf( 'WordPress did not store the change to key "%s". A plugin filter may have refused it, or the database refused it. Nothing was saved.', $key ), 422, $index );
	}

	/**
	 * Flag an error that happened before anything was written.
	 *
	 * @param array|\WP_Error $result Batch result.
	 * @return array|\WP_Error
	 */
	private static function mark_nothing_saved( $result ) {
		if ( is_wp_error( $result ) ) {
			$code = $result->get_error_code();
			$data = $result->get_error_data( $code );
			// Set in place: add_data() would move the old data to additional_data.
			$result->error_data[ $code ] = array_merge( is_array( $data ) ? $data : array(), array( 'nothing_saved' => true ) );
		}

		return $result;
	}

	/** Keep native-compatible HTTP errors, with the change they refer to. */
	private function error( string $code, string $message, int $status, ?int $index = null ): \WP_Error {
		$data = array( 'status' => $status );
		if ( null !== $index ) {
			$data['change_index'] = $index;
		}
		return new \WP_Error( $code, $message, $data );
	}
}
