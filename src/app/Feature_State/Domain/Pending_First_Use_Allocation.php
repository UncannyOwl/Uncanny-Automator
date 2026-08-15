<?php
/**
 * Pending first-use allocation fact.
 *
 * @package Uncanny_Automator
 * @since 7.5.1.1
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Feature_State\Domain;

/**
 * Identifies a grant that MCP accounting will materialize on first product use.
 *
 * This is eligibility evidence, not an allocation row. Allocation_Facts keeps
 * the observed ledger counts truthful while the policy resolver uses this fact
 * to keep the first-use entry point reachable.
 */
final class Pending_First_Use_Allocation {

	public const PHASE_1_LITE      = 'phase_1_lite';
	public const LEGACY_HEAD_START = 'legacy_head_start';

	private ?string $value;

	/**
	 * @param string|null $value Pending grant kind, or null when none is pending.
	 */
	private function __construct( ?string $value ) {
		$this->value = $value;
	}

	/**
	 * @return self
	 */
	public static function none(): self {
		return new self( null );
	}

	/**
	 * @return self
	 */
	public static function phase_1_lite(): self {
		return new self( self::PHASE_1_LITE );
	}

	/**
	 * @return self
	 */
	public static function legacy_head_start(): self {
		return new self( self::LEGACY_HEAD_START );
	}

	/**
	 * @return bool
	 */
	public function is_none(): bool {
		return null === $this->value;
	}

	/**
	 * @return string|null
	 */
	public function value(): ?string {
		return $this->value;
	}
}
