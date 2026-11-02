<?php
/**
 * Every path spelling encountered while following symbolic links.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Site_Commands\Domain\Files\Sandbox;

use Uncanny_Automator\App\Site_Commands\Domain\Files\Exception\Filesystem_Exception;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Filesystem\Read_Only_Filesystem;

/**
 * A bounded metadata-only trace. A null destination means the trace could
 * not be completed, so it cannot authorize a content read. Paths retain
 * the unresolved suffix at every link substitution, including link targets
 * containing other links and parent steps. No file contents are opened.
 */
final class Resolution_Trace {

	/** Bound chains, growing cycles, and unusually deep paths independently. */
	private const MAX_LINKS = 40;
	private const MAX_STEPS = 1024;
	private const MAX_BYTES = 8192;

	/** @var string[] Every spelling, from the requested path through its destination. */
	private array $paths;

	/** @var string|null The destination, or null when inspection was incomplete. */
	private ?string $destination;

	/**
	 * @param string[]    $paths       Path spellings.
	 * @param string|null $destination The resolved destination, when complete.
	 */
	private function __construct( array $paths, ?string $destination ) {
		$this->paths       = array_values( array_unique( $paths ) );
		$this->destination = $destination;
	}

	/**
	 * Follow one link at a time through the existing read-only metadata port.
	 * The caller's path is already normalized. Link targets are processed in
	 * order: an inner link must be followed before a subsequent `..` applies.
	 *
	 * @param string               $path       Normalized absolute path.
	 * @param Read_Only_Filesystem $filesystem The filesystem.
	 * @param string[]             $roots      Canonical readable roots, revalidated before they anchor a trace.
	 * @return self
	 */
	public static function inspect( string $path, Read_Only_Filesystem $filesystem, array $roots ): self {
		$paths = array( $path );
		if ( '/' !== substr( $path, 0, 1 ) || false !== strpos( $path, "\0" ) || strlen( $path ) > self::MAX_BYTES ) {
			return new self( $paths, null );
		}
		$validated = array();
		$start     = self::starting_point( $path, $filesystem, $roots, $validated );
		if ( null === $start ) {
			return new self( $paths, null );
		}
		list( $prefix, $pending ) = $start;

		$links = 0;
		$steps = 0;
		$seen  = array( $path => true );

		while ( array() !== $pending ) {
			if ( ++$steps > self::MAX_STEPS ) {
				return new self( $paths, null );
			}
			$part = array_shift( $pending );
			if ( '' === $part || '.' === $part ) {
				continue;
			}
			if ( '..' === $part ) {
				$prefix = rtrim( dirname( '' === $prefix ? '/' : $prefix ), '/' );
				continue;
			}
			$current = $prefix . '/' . $part;
			if ( self::is_trusted_ancestor( $current, $filesystem, $roots, $validated ) ) {
				$prefix = $current;
				continue;
			}
			try {
				$info = $filesystem->info( $current, false );
			} catch ( Filesystem_Exception $failure ) {
				return new self( $paths, null );
			}
			if ( ! $info->is_link() ) {
				$prefix = $current;
				if ( array() !== $pending && ! $info->is_directory() ) {
					return new self( $paths, null );
				}
				continue;
			}
			// The prefix has now resolved any inner links and parent steps.
			// Retain this spelling too: raw targets containing './' or '../'
			// may otherwise hide a multi-component path exclusion.
			$paths[] = $current . ( array() === $pending ? '' : '/' . implode( '/', $pending ) );
			$target  = $info->link_target();
			if ( null === $target || '' === $target || false !== strpos( $target, "\0" ) || ++$links > self::MAX_LINKS ) {
				return new self( $paths, null );
			}
			$next = ( '/' === $target[0] ? '' : $prefix . '/' ) . $target;
			if ( array() !== $pending ) {
				$next .= '/' . implode( '/', $pending );
			}
			if ( strlen( $next ) > self::MAX_BYTES || isset( $seen[ $next ] ) ) {
				return new self( $paths, null );
			}
			$paths[]       = $next;
			$seen[ $next ] = true;
			if ( '/' === $target[0] ) {
				$start = self::starting_point( $next, $filesystem, $roots, $validated );
				if ( null === $start ) {
					return new self( $paths, null );
				}
				list( $prefix, $pending ) = $start;
			} else {
				$pending = array_merge( explode( '/', $target ), $pending );
			}
		}
		$destination = '' === $prefix ? '/' : $prefix;
		$paths[]     = $destination;

		return new self( $paths, $destination );
	}

	/**
	 * Begin at the outermost matching root. Choosing a nested root instead
	 * would skip aliases inside its enclosing readable root. Unrecognized
	 * absolute spellings retain the full walk and must be inspectable.
	 *
	 * @param string               $path       An absolute spelling, without normalizing link targets.
	 * @param Read_Only_Filesystem $filesystem The filesystem.
	 * @param string[]             $roots      Canonical readable roots.
	 * @param array<string, bool>  $validated  Root checks reused only within this trace.
	 * @return array{0: string, 1: string[]}|null Null when a matching root is no longer canonical and accessible.
	 */
	private static function starting_point( string $path, Read_Only_Filesystem $filesystem, array $roots, array &$validated ): ?array {
		$anchor = null;
		foreach ( $roots as $root ) {
			if ( self::within( $path, $root ) && ( null === $anchor || strlen( $root ) < strlen( $anchor ) ) ) {
				$anchor = $root;
			}
		}
		if ( null === $anchor ) {
			return array( '', explode( '/', $path ) );
		}
		if ( ! self::valid_root( $anchor, $filesystem, $validated ) ) {
			return null;
		}

		return array( rtrim( $anchor, '/' ), explode( '/', substr( $path, strlen( $anchor ) ) ) );
	}

	/**
	 * Ordered parent traversal may revisit a known root ancestor. Its
	 * canonical identity is proven by that root; open_basedir may forbid a
	 * separate stat of it. Never skip a component inside any readable root
	 * or an unknown directory on a path which later returns to a root.
	 *
	 * @param string               $path       The next component's absolute path.
	 * @param Read_Only_Filesystem $filesystem The filesystem.
	 * @param string[]             $roots      Canonical readable roots.
	 * @param array<string, bool>  $validated  Root checks reused only within this trace.
	 * @return bool
	 */
	private static function is_trusted_ancestor( string $path, Read_Only_Filesystem $filesystem, array $roots, array &$validated ): bool {
		foreach ( $roots as $root ) {
			if ( self::within( $path, $root ) ) {
				return false;
			}
		}
		foreach ( $roots as $root ) {
			if ( self::within( $root, $path ) && self::valid_root( $root, $filesystem, $validated ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * A root grants no shortcut if it disappeared or became a link.
	 * Expected metadata failures stay inside the trace and fail closed.
	 *
	 * @param string               $root       A configured canonical root.
	 * @param Read_Only_Filesystem $filesystem The filesystem.
	 * @param array<string, bool>  $validated  Root checks reused only within this trace.
	 * @return bool
	 */
	private static function valid_root( string $root, Read_Only_Filesystem $filesystem, array &$validated ): bool {
		if ( ! array_key_exists( $root, $validated ) ) {
			try {
				$validated[ $root ] = $root === $filesystem->realpath( $root ) && $filesystem->info( $root, false )->is_directory();
			} catch ( Filesystem_Exception $failure ) {
				$validated[ $root ] = false;
			}
		}

		return $validated[ $root ];
	}

	/**
	 * Segment-boundary containment, including the filesystem root.
	 *
	 * @param string $path A path.
	 * @param string $root A canonical directory.
	 * @return bool
	 */
	private static function within( string $path, string $root ): bool {
		return $path === $root || 0 === strpos( $path, '/' === $root ? '/' : $root . '/' );
	}

	/** @return string[] Every spelling encountered. */
	public function paths(): array {
		return $this->paths;
	}

	/** @return string|null The destination, or null when the trace was incomplete. */
	public function destination(): ?string {
		return $this->destination;
	}
}
