<?php
/**
 * The sandbox refused a path.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Site_Commands\Domain\Files\Exception;

/**
 * The sandbox refused a path, either because it resolves outside every
 * root or because policy denies it. Carries what was asked and, for the
 * outside case, what is allowed, so the transcript can print both.
 */
final class Access_Denied extends Coder_Exception {

	/** The path resolves outside every root. */
	public const OUTSIDE = 'outside';

	/** Policy denies the path's content. */
	public const POLICY = 'policy';

	/**
	 * The path as the caller wrote it.
	 *
	 * @var string
	 */
	private string $requested;

	/**
	 * The readable roots, for the outside case.
	 *
	 * @var string[]
	 */
	private array $roots;

	/**
	 * OUTSIDE or POLICY.
	 *
	 * @var string
	 */
	private string $reason;

	/**
	 * Record the refusal.
	 *
	 * @param string   $requested The path as the caller wrote it.
	 * @param string[] $roots     The readable roots.
	 * @param string   $reason    OUTSIDE or POLICY.
	 */
	private function __construct( string $requested, array $roots, string $reason ) {
		$this->requested = $requested;
		$this->roots     = $roots;
		$this->reason    = $reason;
		parent::__construct( 'Permission denied' );
	}

	/**
	 * The path resolves outside every root.
	 *
	 * @param string   $requested The path as the caller wrote it.
	 * @param string[] $roots     The readable roots.
	 * @return self
	 */
	public static function outside( string $requested, array $roots ): self {
		return new self( $requested, $roots, self::OUTSIDE );
	}

	/**
	 * Policy denies the path's content.
	 *
	 * @param string $requested The path as the caller wrote it.
	 * @return self
	 */
	public static function policy( string $requested ): self {
		return new self( $requested, array(), self::POLICY );
	}

	/**
	 * The path as the caller wrote it.
	 *
	 * @return string
	 */
	public function requested(): string {
		return $this->requested;
	}

	/**
	 * The readable roots, empty for a policy denial.
	 *
	 * @return string[]
	 */
	public function roots(): array {
		return $this->roots;
	}

	/**
	 * Whether policy, not the roots, refused the path.
	 *
	 * @return bool
	 */
	public function is_policy(): bool {
		return self::POLICY === $this->reason;
	}
}
