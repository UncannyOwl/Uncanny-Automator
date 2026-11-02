<?php
/**
 * The readable roots and the policy that resolves a requested path.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Command output is plain terminal text for AI clients, never rendered as HTML.

namespace Uncanny_Automator\App\Site_Commands\Domain\Files\Sandbox;

use InvalidArgumentException;
use Throwable;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Exception\Access_Denied;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Exception\Filesystem_Exception;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Exception\Unexpected_Failure;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Filesystem\Read_Only_Filesystem;
use Uncanny_Automator\App\Site_Commands\Domain\Files\Filesystem\Standard_Input;

/**
 * The set of readable roots and the policy that resolves a requested path.
 *
 * This is the only object in the system that turns a string into a
 * filesystem path. Roots arrive already resolved; the sandbox holds no
 * WordPress knowledge. The first root is the base for relative paths.
 */
final class Sandbox {

	/**
	 * Absolute, canonical, deduplicated roots.
	 *
	 * @var string[]
	 */
	private array $roots;

	/**
	 * The content policy.
	 *
	 * @var Denylist
	 */
	private Denylist $denylist;

	/**
	 * A directory inside the roots that relative paths resolve from; the first root when unset.
	 *
	 * @var string|null
	 */
	private ?string $working_directory = null;

	/**
	 * Every name encountered when selecting the working directory.
	 *
	 * @var Resolution_Trace|null
	 */
	private ?Resolution_Trace $working_directory_trace = null;

	/**
	 * Whether what a command prints reaches the caller, so the content policy reads it.
	 *
	 * @var bool
	 */
	private bool $prints_to_caller = true;

	/**
	 * Normalise and deduplicate the roots.
	 *
	 * @param string[]      $roots    Absolute, already-canonical directories.
	 * @param Denylist|null $denylist The content policy; the defaults when null.
	 *
	 * @throws InvalidArgumentException When no absolute root remains.
	 */
	private function __construct( array $roots, ?Denylist $denylist ) {
		$this->denylist = $denylist ?? Denylist::defaults();
		$normalized     = array();
		foreach ( $roots as $root ) {
			$root = Path_Normalizer::normalize( $root );
			if ( '' === $root || 0 !== strpos( $root, '/' ) ) {
				continue;
			}
			$normalized[ $root ] = $root;
		}

		if ( array() === $normalized ) {
			throw new InvalidArgumentException( 'A sandbox needs at least one absolute root.' );
		}

		$this->roots = array_values( $normalized );
	}

	/**
	 * A sandbox over the given roots.
	 *
	 * @param string[]      $roots    Absolute, already-canonical directories.
	 * @param Denylist|null $denylist The content policy; the defaults when null.
	 * @return self
	 */
	public static function from_roots( array $roots, ?Denylist $denylist = null ): self {
		return new self( $roots, $denylist );
	}

	/**
	 * The readable roots.
	 *
	 * @return string[]
	 */
	public function roots(): array {
		return $this->roots;
	}

	/**
	 * The directory relative paths resolve from.
	 *
	 * @return string
	 */
	public function base(): string {
		return $this->working_directory ?? $this->roots[0];
	}

	/**
	 * The same sandbox with relative paths resolving from $real, which must
	 * already be a resolved directory inside the roots.
	 *
	 * @param string               $real       A resolved directory inside the roots.
	 * @param string               $requested  The directory as requested, before links were followed.
	 * @param Read_Only_Filesystem $filesystem The filesystem, to retain every intermediate directory name.
	 * @return self
	 */
	public function with_working_directory( string $real, string $requested, Read_Only_Filesystem $filesystem ): self {
		$scoped                          = clone $this;
		$scoped->working_directory       = rtrim( $real, '/' );
		$scoped->working_directory_trace = Resolution_Trace::inspect( $this->physical( $requested ), $filesystem, $this->roots );

		return $scoped;
	}

	/**
	 * The same sandbox for a pipeline stage whose output goes only to the next
	 * stage. The caller never sees that output, and head or grep may drop the
	 * credential in it, so text passed on unchanged is judged by the last stage.
	 *
	 * @return self
	 */
	public function for_intermediate_stage(): self {
		$scoped                   = clone $this;
		$scoped->prints_to_caller = false;

		return $scoped;
	}

	/**
	 * Resolve a requested path to a real absolute path inside the sandbox.
	 *
	 * Non-existent paths resolve through their nearest existing ancestor so
	 * commands can report "No such file or directory" with the right name.
	 * A symlink whose target escapes is denied at the symlink.
	 *
	 * @param string               $requested  The path as the caller wrote it.
	 * @param Read_Only_Filesystem $filesystem The filesystem.
	 * @return string
	 *
	 * @throws Access_Denied When the path resolves outside every root.
	 */
	public function resolve( string $requested, Read_Only_Filesystem $filesystem ): string {
		if ( '' === $requested || false !== strpos( $requested, "\0" ) ) {
			throw Access_Denied::outside( $requested, $this->roots );
		}

		$candidate = 0 === strpos( $requested, '/' )
			? $requested
			: Path_Normalizer::join( $this->base(), $requested );
		$candidate = Path_Normalizer::normalize( $candidate );

		$real = $this->realpath_through_ancestors( $candidate, $filesystem );

		if ( ! $this->contains( $real ) ) {
			throw Access_Denied::outside( $requested, $this->roots );
		}

		return $real;
	}

	/**
	 * Refuse content reads the denylist forbids by name. Call after resolve()
	 * and before opening the file. Metadata commands do not call this.
	 *
	 * @param string               $requested  The path as the caller wrote it.
	 * @param string               $real       The resolved path.
	 * @param Read_Only_Filesystem $filesystem The filesystem.
	 * @return void
	 *
	 * @throws Access_Denied When policy denies the content.
	 */
	public function assert_content_readable( string $requested, string $real, Read_Only_Filesystem $filesystem ): void {
		if ( ! $this->allows_content( $real, $filesystem, $this->physical( $requested ), $requested ) ) {
			throw Access_Denied::policy( $requested );
		}
	}

	/**
	 * Whether a known name is excluded, even if the file is missing.
	 * Used to preserve policy errors when metadata fails. Passing this check
	 * does not authorize opening content; a complete trace is still required.
	 *
	 * @param string $requested The operand as written.
	 * @param string $real      The resolved or prospective destination.
	 * @return bool
	 */
	public function excludes_content_names( string $requested, string $real ): bool {
		return ! $this->allows_known_names( $real, $this->physical( $requested ), $requested );
	}

	/**
	 * Whether a resolved file's content may be read: its name and every link
	 * to it pass the denylist. A path that cannot be examined is left for the
	 * command to report as the shell would.
	 *
	 * Trade-off: a file is not refused for what it holds. No check can
	 * recognise every credential, and refusing a whole file for one signature
	 * stops a site owner's AI reading the safe parts of it. So known sensitive
	 * names stay blocked, the owner can block any other file in the settings,
	 * and allows_printed() catches what it can on the way out.
	 *
	 * @param string               $real       The resolved path.
	 * @param Read_Only_Filesystem $filesystem The filesystem.
	 * @param string|null          $physical   The path before links were followed, when known, which must pass too.
	 * @param string|null          $requested  The operand as written, to retain the original working-directory name too.
	 * @return bool
	 */
	public function allows_content( string $real, Read_Only_Filesystem $filesystem, ?string $physical = null, ?string $requested = null ): bool {
		$standard_input = Standard_Input::PATH === $real && $filesystem instanceof Standard_Input;
		// Cwd aliases describe disk operands, not text from a previous stage.
		if ( ! $this->allows_known_names( $real, $physical, $standard_input ? null : $requested ) ) {
			return false;
		}
		// Standard input is already-produced text, not a path through the disk:
		// what a stage prints from it is judged when it reaches the caller.
		if ( $standard_input ) {
			return true;
		}

		try {
			return $this->contains( $real ) && $this->allows_resolution( $real, $physical ?? $real, $requested, $filesystem );
		} catch ( Filesystem_Exception $unreadable ) {
			return true;
		} catch ( Throwable $failure ) {
			// Preserve expected control-flow exceptions; contextualize unexpected failures.
			throw Unexpected_Failure::during( 'checking content policy', $failure );
		}
	}

	/**
	 * Whether text a command printed from a file, or from standard input,
	 * may reach the caller: it holds none of the known credential signatures.
	 * It reads every byte it is given, which the output budgets bound.
	 *
	 * Trade-off: this is best effort, not a guarantee. It recognises a closed
	 * set of signatures, so a password with no recognisable shape, or only a
	 * value printed by `cut -d= -f2`, passes. Blocking more would refuse useful
	 * output on guesses. A file the owner knows is sensitive belongs in the
	 * settings, which stop it being opened at all.
	 *
	 * A stage that prefixes its lines is judged where it adds them, even when
	 * its output goes only to the next stage: the signatures anchor to the
	 * start of a line, so no later stage could recognise a prefixed credential.
	 *
	 * @param string $printed  The printed text, without the prefixes the command added.
	 * @param bool   $prefixed Whether the command added prefixes to the text.
	 * @return bool
	 */
	public function allows_printed( string $printed, bool $prefixed = false ): bool {
		if ( ! $this->prints_to_caller && ! $prefixed ) {
			return true;
		}

		return ! $this->denylist->denies_sample( $printed );
	}

	/**
	 * The absolute path before symlink resolution, for lstat. Only
	 * meaningful for a path that resolve() has accepted.
	 *
	 * @param string $requested The path as the caller wrote it.
	 * @return string
	 */
	public function physical( string $requested ): string {
		$candidate = 0 === strpos( $requested, '/' )
			? $requested
			: Path_Normalizer::join( $this->base(), $requested );

		return Path_Normalizer::normalize( $candidate );
	}

	/**
	 * Every intermediate alias must pass before even a content sample opens.
	 * Filesystem operations retain their canonical cwd and parent semantics;
	 * the cwd trace also keeps names already consumed when that cwd was selected.
	 *
	 * @param string               $real       The destination already accepted by resolve().
	 * @param string               $physical   The operand under the canonical cwd, before its links are followed.
	 * @param string|null          $requested  The operand as written, when known.
	 * @param Read_Only_Filesystem $filesystem The filesystem.
	 * @return bool
	 */
	private function allows_resolution( string $real, string $physical, ?string $requested, Read_Only_Filesystem $filesystem ): bool {
		$trace = Resolution_Trace::inspect( $physical, $filesystem, $this->roots );
		if ( $real !== $trace->destination() ) {
			return false;
		}
		if ( null !== $requested && '/' !== substr( $requested, 0, 1 ) && null !== $this->working_directory_trace ) {
			if ( $this->base() !== $this->working_directory_trace->destination() ) {
				return false;
			}
		}

		return $this->allows_name_spellings( $trace->paths() );
	}

	/**
	 * Check names already known without performing any filesystem operation.
	 *
	 * @param string      $real      The destination.
	 * @param string|null $physical  The operand under the canonical cwd.
	 * @param string|null $requested The operand as written.
	 * @return bool
	 */
	private function allows_known_names( string $real, ?string $physical, ?string $requested ): bool {
		$paths = null === $physical ? array( $real ) : array( $real, $physical );
		if ( null !== $requested && '/' !== substr( $requested, 0, 1 ) && null !== $this->working_directory_trace ) {
			foreach ( $this->working_directory_trace->paths() as $directory ) {
				$paths[] = Path_Normalizer::join( $directory, $requested );
			}
		}

		return $this->allows_name_spellings( $paths );
	}

	/**
	 * Check both raw and normalized names without following any links.
	 *
	 * @param string[] $paths The encountered spellings.
	 * @return bool
	 */
	private function allows_name_spellings( array $paths ): bool {
		// Normalize policy spellings as we do caller operands, without changing
		// the trace's ordered link/parent resolution. Both names must pass:
		// dot segments must not hide an excluded alias of a public destination.
		$spellings = array();
		foreach ( $paths as $path ) {
			$spellings[ $path ]                               = true;
			$spellings[ Path_Normalizer::normalize( $path ) ] = true;
		}
		foreach ( array_keys( $spellings ) as $path ) {
			if ( $this->denylist->denies_content( $path ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Whether a resolved path lies inside a root.
	 *
	 * @param string $real A resolved path.
	 * @return bool
	 */
	public function contains( string $real ): bool {
		foreach ( $this->roots as $root ) {
			if ( $real === $root ) {
				return true;
			}
			$prefix = '/' === $root ? '/' : $root . '/';
			if ( 0 === strpos( $real, $prefix ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * The real path, or the real nearest ancestor joined with the rest.
	 *
	 * @param string               $candidate  A normalised absolute path.
	 * @param Read_Only_Filesystem $filesystem The filesystem.
	 * @return string
	 */
	private function realpath_through_ancestors( string $candidate, Read_Only_Filesystem $filesystem ): string {
		$real = $filesystem->realpath( $candidate );
		if ( null !== $real ) {
			return $real;
		}

		$suffix = array();
		$cursor = $candidate;
		while ( '/' !== $cursor && '' !== $cursor ) {
			$suffix[]      = basename( $cursor );
			$cursor        = dirname( $cursor );
			$real_ancestor = $filesystem->realpath( $cursor );
			if ( null !== $real_ancestor ) {
				return rtrim( $real_ancestor, '/' ) . '/' . implode( '/', array_reverse( $suffix ) );
			}
		}

		return $candidate;
	}
}
