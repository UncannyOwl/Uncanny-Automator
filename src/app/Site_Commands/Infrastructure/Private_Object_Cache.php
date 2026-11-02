<?php
/**
 * An object cache that belongs to one command.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Site_Commands\Infrastructure;

/**
 * Stands in for the site's object cache while a read-only command runs, so
 * the caches WordPress fills as it reads stay with the command and are
 * discarded with it. It calls the site's cache only to read WordPress's
 * three update checks, which a site with a persistent object cache keeps
 * nowhere else; the Plugins screen reads the same copies. Otherwise a miss
 * is read from the database, which is always correct.
 *
 * It behaves as WordPress's own WP_Object_Cache does: keys are kept per
 * site on multisite, except in WordPress's global groups, and objects are
 * copied in and out. Methods it does not know do nothing.
 */
final class Private_Object_Cache {

	/** Site transients read from the site's cache: WordPress's update checks. */
	private const UPDATE_CHECKS = array( 'update_core', 'update_plugins', 'update_themes' );

	/** The groups WordPress shares between sites, as wp_start_object_cache() registers them. */
	private const GLOBAL_GROUPS = array( 'blog-details', 'blog-id-cache', 'blog-lookup', 'blog_meta', 'global-posts', 'image_editor', 'networks', 'network-queries', 'sites', 'site-details', 'site-options', 'site-queries', 'site-transient', 'theme_files', 'translation_files', 'rss', 'users', 'user-queries', 'user_meta', 'useremail', 'userlogins', 'userslugs' );

	/**
	 * Values by group and key.
	 *
	 * @var array<string, array<string, mixed>>
	 */
	private array $cache = array();

	/**
	 * Groups shared between sites.
	 *
	 * @var array<string, true>
	 */
	private array $global_groups;

	private bool $multisite;

	private string $blog_prefix;

	/**
	 * The site's cache, kept so that commands can name its type and read
	 * WordPress's update checks from it.
	 *
	 * @var mixed
	 */
	private $site_cache;

	/**
	 * Hits and misses, as WordPress counts them.
	 *
	 * @var int
	 */
	public $cache_hits = 0;

	/**
	 * Misses.
	 *
	 * @var int
	 */
	public $cache_misses = 0;

	/**
	 * Start empty.
	 *
	 * @param bool  $multisite  Whether the site is a multisite network.
	 * @param int   $blog_id    The current site.
	 * @param mixed $site_cache The site's cache, for naming its type and reading update checks.
	 */
	public function __construct( bool $multisite, int $blog_id, $site_cache = null ) {
		$this->multisite     = $multisite;
		$this->blog_prefix   = $multisite ? $blog_id . ':' : '';
		$this->global_groups = array_fill_keys( self::GLOBAL_GROUPS, true );
		$this->site_cache    = $site_cache;
	}

	/**
	 * The site's own cache behind a private one, or the object itself.
	 *
	 * @param mixed $cache An object cache.
	 * @return mixed
	 */
	public static function site_cache( $cache ) {
		return $cache instanceof self ? $cache->site_cache : $cache;
	}

	/**
	 * Read a value.
	 *
	 * @param int|string $key   Key.
	 * @param string     $group Group.
	 * @param bool       $force Unused; there is nothing behind this cache.
	 * @param bool|null  $found Set to whether the value was found.
	 * @return mixed
	 */
	public function get( $key, $group = 'default', $force = false, &$found = null ) {
		$slot = $this->slot( $key, $group );
		if ( null !== $slot && isset( $this->cache[ $slot[0] ] ) && array_key_exists( $slot[1], $this->cache[ $slot[0] ] ) ) {
			$found = true;
			++$this->cache_hits;
			$value = $this->cache[ $slot[0] ][ $slot[1] ];

			return is_object( $value ) ? clone $value : $value;
		}
		if ( 'site-transient' === $group && in_array( $key, self::UPDATE_CHECKS, true ) && is_object( $this->site_cache ) && method_exists( $this->site_cache, 'get' ) ) {
			$value = $this->site_cache->get( $key, $group, false, $found );

			return is_object( $value ) ? clone $value : $value;
		}
		$found = false;
		++$this->cache_misses;

		return false;
	}

	/**
	 * Read several values.
	 *
	 * @param array  $keys  Keys.
	 * @param string $group Group.
	 * @param bool   $force Unused.
	 * @return array
	 */
	public function get_multiple( $keys, $group = 'default', $force = false ) {
		$values = array();
		foreach ( (array) $keys as $key ) {
			$values[ $key ] = $this->get( $key, $group );
		}

		return $values;
	}

	/**
	 * Store a value.
	 *
	 * @param int|string $key    Key.
	 * @param mixed      $data   Value.
	 * @param string     $group  Group.
	 * @param int        $expire Unused; the cache lasts one command.
	 * @return bool
	 */
	public function set( $key, $data, $group = 'default', $expire = 0 ) {
		$slot = $this->slot( $key, $group );
		if ( null === $slot ) {
			return false;
		}
		$this->cache[ $slot[0] ][ $slot[1] ] = is_object( $data ) ? clone $data : $data;

		return true;
	}

	/**
	 * Store several values.
	 *
	 * @param array  $data   Values by key.
	 * @param string $group  Group.
	 * @param int    $expire Unused.
	 * @return array<int|string, bool>
	 */
	public function set_multiple( $data, $group = '', $expire = 0 ) {
		$results = array();
		foreach ( (array) $data as $key => $value ) {
			$results[ $key ] = $this->set( $key, $value, $group );
		}

		return $results;
	}

	/**
	 * Store a value unless one exists, or while cache additions are suspended.
	 *
	 * @param int|string $key    Key.
	 * @param mixed      $data   Value.
	 * @param string     $group  Group.
	 * @param int        $expire Unused.
	 * @return bool
	 */
	public function add( $key, $data, $group = 'default', $expire = 0 ) {
		if ( function_exists( 'wp_suspend_cache_addition' ) && wp_suspend_cache_addition() ) {
			return false;
		}
		$this->get( $key, $group, false, $found );

		return $found ? false : $this->set( $key, $data, $group );
	}

	/**
	 * Store several values unless they exist.
	 *
	 * @param array  $data   Values by key.
	 * @param string $group  Group.
	 * @param int    $expire Unused.
	 * @return array<int|string, bool>
	 */
	public function add_multiple( $data, $group = '', $expire = 0 ) {
		$results = array();
		foreach ( (array) $data as $key => $value ) {
			$results[ $key ] = $this->add( $key, $value, $group );
		}

		return $results;
	}

	/**
	 * Store a value only if one exists.
	 *
	 * @param int|string $key    Key.
	 * @param mixed      $data   Value.
	 * @param string     $group  Group.
	 * @param int        $expire Unused.
	 * @return bool
	 */
	public function replace( $key, $data, $group = 'default', $expire = 0 ) {
		$this->get( $key, $group, false, $found );

		return $found ? $this->set( $key, $data, $group ) : false;
	}

	/**
	 * Add to a stored number, never going below zero.
	 *
	 * @param int|string $key    Key.
	 * @param int        $offset Amount.
	 * @param string     $group  Group.
	 * @return int|false The new value, or false when there is none.
	 */
	public function incr( $key, $offset = 1, $group = 'default' ) {
		$value = $this->get( $key, $group, false, $found );
		if ( ! $found ) {
			return false;
		}
		$value = max( 0, ( is_numeric( $value ) ? (int) $value : 0 ) + (int) $offset );
		$this->set( $key, $value, $group );

		return $value;
	}

	/**
	 * Subtract from a stored number, never going below zero.
	 *
	 * @param int|string $key    Key.
	 * @param int        $offset Amount.
	 * @param string     $group  Group.
	 * @return int|false The new value, or false when there is none.
	 */
	public function decr( $key, $offset = 1, $group = 'default' ) {
		return $this->incr( $key, - (int) $offset, $group );
	}

	/**
	 * Remove a value.
	 *
	 * @param int|string $key        Key.
	 * @param string     $group      Group.
	 * @param bool       $deprecated Unused.
	 * @return bool Whether it existed.
	 */
	public function delete( $key, $group = 'default', $deprecated = false ) {
		$slot = $this->slot( $key, $group );
		if ( null === $slot || ! isset( $this->cache[ $slot[0] ] ) || ! array_key_exists( $slot[1], $this->cache[ $slot[0] ] ) ) {
			return false;
		}
		unset( $this->cache[ $slot[0] ][ $slot[1] ] );

		return true;
	}

	/**
	 * Remove several values.
	 *
	 * @param array  $keys  Keys.
	 * @param string $group Group.
	 * @return array<int|string, bool>
	 */
	public function delete_multiple( $keys, $group = '' ) {
		$results = array();
		foreach ( (array) $keys as $key ) {
			$results[ $key ] = $this->delete( $key, $group );
		}

		return $results;
	}

	/**
	 * Remove everything.
	 *
	 * @return bool
	 */
	public function flush() {
		$this->cache = array();

		return true;
	}

	/**
	 * Remove everything.
	 *
	 * @return bool
	 */
	public function flush_runtime() {
		return $this->flush();
	}

	/**
	 * Remove one group.
	 *
	 * @param string $group Group.
	 * @return bool
	 */
	public function flush_group( $group ) {
		unset( $this->cache[ self::group( $group ) ] );

		return true;
	}

	/**
	 * Remove the values of the current site, keeping shared groups, as
	 * WordPress's deprecated reset() does.
	 *
	 * @return void
	 */
	public function reset() {
		foreach ( array_keys( $this->cache ) as $group ) {
			if ( ! isset( $this->global_groups[ $group ] ) ) {
				unset( $this->cache[ $group ] );
			}
		}
	}

	/**
	 * Mark groups as shared between sites.
	 *
	 * @param string|string[] $groups Groups.
	 * @return void
	 */
	public function add_global_groups( $groups ) {
		$this->global_groups = array_merge( $this->global_groups, array_fill_keys( (array) $groups, true ) );
	}

	/**
	 * Nothing here persists, so there is nothing to mark.
	 *
	 * @param string|string[] $groups Groups.
	 * @return void
	 */
	public function add_non_persistent_groups( $groups ) {
	}

	/**
	 * Keep later keys for another site.
	 *
	 * @param int $blog_id Site.
	 * @return void
	 */
	public function switch_to_blog( $blog_id ) {
		$this->blog_prefix = $this->multisite ? (int) $blog_id . ':' : '';
	}

	/**
	 * Nothing to close.
	 *
	 * @return bool
	 */
	public function close() {
		return true;
	}

	/**
	 * A method this cache does not know does nothing; it never reaches the
	 * site's cache.
	 *
	 * @param string $name      Method.
	 * @param array  $arguments Arguments.
	 * @return false
	 */
	public function __call( $name, $arguments ) {
		return false;
	}

	/**
	 * Where a key is kept: its group, and the key with the site's prefix
	 * unless the group is shared. Null for a key WordPress would refuse.
	 *
	 * @param mixed  $key   Key.
	 * @param string $group Group.
	 * @return array{0: string, 1: string}|null
	 */
	private function slot( $key, $group ): ?array {
		if ( ! is_int( $key ) && ( ! is_string( $key ) || '' === trim( $key ) ) ) {
			return null;
		}
		$group = self::group( $group );

		return array( $group, ( $this->multisite && ! isset( $this->global_groups[ $group ] ) ? $this->blog_prefix : '' ) . $key );
	}

	/**
	 * A group name, with WordPress's default for an empty one.
	 *
	 * @param mixed $group Group.
	 * @return string
	 */
	private static function group( $group ): string {
		return '' === (string) $group ? 'default' : (string) $group;
	}
}
