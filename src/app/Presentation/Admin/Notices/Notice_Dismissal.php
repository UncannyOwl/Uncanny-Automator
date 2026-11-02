<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Presentation\Admin\Notices;

/**
 * How a notice may be put away: not at all (it shows while its condition
 * holds), for this page view only, or stored for the user or for the whole
 * site, forever or for a time.
 *
 * A stored dismissal can be tied to a variant, a fingerprint of the state
 * it was given for: when the notice's state changes, the variant changes and
 * the notice shows again.
 *
 * @since 8.0.0
 */
final class Notice_Dismissal {

	public const SCOPE_NONE = 'none';
	public const SCOPE_VIEW = 'view';
	public const SCOPE_USER = 'user';
	public const SCOPE_SITE = 'site';

	/**
	 * @var string
	 */
	private $scope;

	/**
	 * @var int
	 */
	private $seconds;

	/**
	 * @var string
	 */
	private $variant;

	/**
	 * @param string $scope   One of the constants.
	 * @param int    $seconds How long a stored dismissal lasts; 0 for forever.
	 * @param string $variant The state it was given for; '' for any.
	 */
	private function __construct( string $scope, int $seconds = 0, string $variant = '' ) {

		$this->scope   = $scope;
		$this->seconds = max( 0, $seconds );
		$this->variant = $variant;
	}

	/**
	 * Shows while its condition holds; no dismiss.
	 *
	 * @return self
	 */
	public static function none(): self {

		return new self( self::SCOPE_NONE );
	}

	/**
	 * Its dismiss hides it until the next page load.
	 *
	 * @return self
	 */
	public static function for_this_view(): self {

		return new self( self::SCOPE_VIEW );
	}

	/**
	 * Dismissed for the user who dismissed it.
	 *
	 * @param int    $seconds How long; 0 for forever.
	 * @param string $variant The state it was given for; '' for any.
	 *
	 * @return self
	 */
	public static function per_user( int $seconds = 0, string $variant = '' ): self {

		return new self( self::SCOPE_USER, $seconds, $variant );
	}

	/**
	 * Dismissed for every user of the site.
	 *
	 * @param int    $seconds How long; 0 for forever.
	 * @param string $variant The state it was given for; '' for any.
	 *
	 * @return self
	 */
	public static function per_site( int $seconds = 0, string $variant = '' ): self {

		return new self( self::SCOPE_SITE, $seconds, $variant );
	}

	/**
	 * @return bool Whether the notice has a dismiss.
	 */
	public function is_dismissible(): bool {

		return self::SCOPE_NONE !== $this->scope;
	}

	/**
	 * @return bool Whether a dismiss is remembered past this page view.
	 */
	public function is_stored(): bool {

		return self::SCOPE_USER === $this->scope || self::SCOPE_SITE === $this->scope;
	}

	/**
	 * @return string One of the constants.
	 */
	public function scope(): string {

		return $this->scope;
	}

	/**
	 * @return int How long a stored dismissal lasts; 0 for forever.
	 */
	public function seconds(): int {

		return $this->seconds;
	}

	/**
	 * @return string The state it was given for; '' for any.
	 */
	public function variant(): string {

		return $this->variant;
	}
}
