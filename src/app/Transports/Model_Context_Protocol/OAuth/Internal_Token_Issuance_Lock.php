<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Model_Context_Protocol\OAuth;

// phpcs:disable PSR2.Methods.FunctionClosingBrace.SpacingBeforeClose -- Deliberate breathing room at function boundaries.

/**
 * Serializes recoverable internal-token issuance for one user and site.
 *
 * The site-local options table provides the unique-key ownership boundary that
 * WordPress user metadata does not provide on its first-write path.
 *
 * @since 7.4.1
 */
final class Internal_Token_Issuance_Lock {

	/**
	 * Maximum lifetime of an issuance lock.
	 *
	 * @var int
	 */
	const LOCK_TTL = 30;

	/**
	 * Maximum seconds to wait for an in-flight issuance.
	 *
	 * @var int
	 */
	const LOCK_WAIT = 2;

	/**
	 * Atomic site-option lock boundary.
	 *
	 * @var Atomic_Option_Lock
	 */
	private $lock;

	/**
	 * Constructor.
	 *
	 * @param Atomic_Option_Lock|null $lock Atomic site-option lock boundary.
	 */
	public function __construct( ?Atomic_Option_Lock $lock = null ) {

		$this->lock = $lock ?? new Atomic_Option_Lock();

	}

	/**
	 * Acquire the site-local issuance lock.
	 *
	 * @param int       $user_id WordPress user ID.
	 * @param int|float $wait_seconds Maximum seconds to wait.
	 * @return string|false Lock owner identifier or false on timeout.
	 */
	public function acquire( int $user_id, $wait_seconds = self::LOCK_WAIT ) {

		return $this->lock->acquire(
			$this->get_name( $user_id ),
			self::LOCK_TTL,
			$wait_seconds
		);

	}

	/**
	 * Release the lock only while this request still owns it.
	 *
	 * @param int    $user_id WordPress user ID.
	 * @param string $owner Lock owner identifier.
	 * @return void
	 */
	public function release( int $user_id, string $owner ): void {

		$this->lock->release( $this->get_name( $user_id ), $owner );

	}

	/**
	 * Build the site-local option name for one user's lock.
	 *
	 * Options are already site-specific in multisite, so the blog ID does not
	 * need to be duplicated in the option name.
	 *
	 * @param int $user_id WordPress user ID.
	 * @return string Lock option name.
	 */
	public function get_name( int $user_id ): string {

		return 'automator_mcp_internal_token_lock_' . $user_id;

	}
}
