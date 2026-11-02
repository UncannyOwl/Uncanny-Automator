<?php
/**
 * Keep a command's handler from writing to the site.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Command errors are plain terminal text for AI clients, never rendered as HTML.

namespace Uncanny_Automator\App\Site_Commands\Infrastructure;

use Uncanny_Automator\App\Site_Commands\Domain\Command_Error;
use Uncanny_Automator\App\Site_Commands\Ports\Read_Isolation;

/**
 * While the handler runs:
 *
 * - The object cache is a private one (Private_Object_Cache). The caches
 *   WordPress fills as it reads stay there and are discarded. Before
 *   anything runs, the object cache functions are read, without being
 *   called (Cache_Routing). When each is verified to work through
 *   $wp_object_cache, the site's cache is called only to read WordPress's
 *   three update checks. When some are not, isolation is best effort: the
 *   command runs, and limitation() says a read may refresh the site's cache.
 * - Nothing flushes the site's cache. A function that could is refused
 *   before the command runs, and the flush filters (Cache_Routing::FLUSH_FILTERS)
 *   answer no before any other callback, so a drop-in that asks them, such
 *   as Object Cache Pro, neither flushes nor logs a flush.
 * - Reads of the theme_roots site transient get the current theme folder
 *   list (Theme_Roots). WordPress then has no out-of-date copy to save, and
 *   no expired copy to delete, when it lists themes.
 *
 * All are restored when the handler ends, even if it fails. No setting is
 * changed and no other hook is removed.
 */
final class WordPress_Read_Isolation implements Read_Isolation {

	private Cache_Routing $routing;

	/**
	 * Check the site's cache functions, unless a test supplies a check.
	 *
	 * @param Cache_Routing|null $routing Cache function check.
	 */
	public function __construct( ?Cache_Routing $routing = null ) {
		$this->routing = $routing ?? new Cache_Routing();
	}

	/**
	 * Run the work, isolated.
	 *
	 * @param callable $work The command's handler, bound to its input.
	 * @return mixed What the work returned.
	 *
	 * @throws Command_Error When the cache functions cannot be read, or could flush the site's cache.
	 */
	public function run( callable $work ) {
		global $wp_object_cache;

		$problem = $this->routing->problem();
		if ( null !== $problem ) {
			throw new Command_Error( 'Site commands are not available on this site: ' . $problem );
		}

		$no_flush = static function () {
			return false;
		};
		foreach ( Cache_Routing::FLUSH_FILTERS as $filter ) {
			add_filter( $filter, $no_flush, PHP_INT_MIN );
		}

		$site_cache  = $wp_object_cache;
		$private     = new Private_Object_Cache( is_multisite(), get_current_blog_id(), $site_cache );
		$roots       = null;
		$theme_roots = static function () use ( &$roots ) {
			if ( null === $roots ) {
				$roots = Theme_Roots::current();
			}

			return $roots;
		};

		$wp_object_cache = $private; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restored below; the site's cache is only read, for update checks.
		add_filter( 'pre_site_transient_theme_roots', $theme_roots, PHP_INT_MAX );

		try {
			return $work();
		} finally {
			remove_filter( 'pre_site_transient_theme_roots', $theme_roots, PHP_INT_MAX );
			foreach ( Cache_Routing::FLUSH_FILTERS as $filter ) {
				remove_filter( $filter, $no_flush, PHP_INT_MIN );
			}
			$wp_object_cache = $site_cache; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restoring the site's cache.
		}
	}

	/**
	 * When some cache functions are not verified, a stats line saying that
	 * reads may refresh the site's cache; null when isolation is complete.
	 *
	 * @return string|null
	 */
	public function limitation(): ?string {
		$unverified = $this->routing->limitation();

		return null === $unverified ? null : 'Object cache: best effort. ' . $unverified . ', so reading may refresh the site\'s object cache. Content, settings and files are never changed.';
	}
}
