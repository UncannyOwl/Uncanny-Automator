<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Infrastructure\Connections;

/**
 * Where a staging copy's adoption stands — read from the rows, so every admin
 * sees the same thing on every page load, whichever request ran the adoption
 * (staging-copies handoff R4).
 *
 *  - wanted       the admin chose to use the live site's connections here.
 *  - waiting      an attempt is still owed: the copy is still registering, or
 *                 the platform did not answer for some row and it is retried.
 *  - attached     rows this copy attached to the live site's accounts.
 *  - not_attached rows still switched off, each with why: not on offer from
 *                 the live site (not shared, or the account is gone — the
 *                 platform does not say which), the account went away
 *                 during the call, refused, no account on record, or not
 *                 answered yet.
 *
 * @since 8.0.0
 */
final class Adoption_Report {

	/**
	 * @var bool
	 */
	private $wanted;

	/**
	 * @var bool
	 */
	private $waiting;

	/**
	 * @var array<int, Connection>
	 */
	private $attached;

	/**
	 * @var array<int, array{connection: Connection, reason: string}>
	 */
	private $not_attached;

	/**
	 * @param bool                                                      $wanted       Whether adoption was chosen.
	 * @param bool                                                      $waiting      Whether an attempt is still owed.
	 * @param array<int, Connection>                                    $attached     Attached rows.
	 * @param array<int, array{connection: Connection, reason: string}> $not_attached Rows still off, with why.
	 */
	public function __construct( bool $wanted, bool $waiting, array $attached, array $not_attached ) {

		$this->wanted       = $wanted;
		$this->waiting      = $waiting;
		$this->attached     = array_values( $attached );
		$this->not_attached = array_values( $not_attached );
	}

	/**
	 * @return bool
	 */
	public function wanted(): bool {
		return $this->wanted;
	}

	/**
	 * @return bool
	 */
	public function is_waiting(): bool {
		return $this->waiting;
	}

	/**
	 * @return array<int, Connection>
	 */
	public function attached(): array {
		return $this->attached;
	}

	/**
	 * @return array<int, array{connection: Connection, reason: string}>
	 */
	public function not_attached(): array {
		return $this->not_attached;
	}

	/**
	 * Whether a row is still off for a reason the admin can act on (anything
	 * but "not answered yet").
	 *
	 * @return bool
	 */
	public function has_refusals(): bool {

		foreach ( $this->not_attached as $item ) {
			if ( Connection_Adoption::REASON_NOT_ANSWERED !== $item['reason'] ) {
				return true;
			}
		}

		return false;
	}
}
