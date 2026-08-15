<?php
/**
 * MCP allocation-facts response.
 *
 * @package Uncanny_Automator
 * @since 7.5.1.1
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Feature_State\Infrastructure;

use Uncanny_Automator\App\Feature_State\Domain\Pending_First_Use_Allocation;

/**
 * Validates the small wire response before policy code can use it.
 */
final class Mcp_Allocation_Facts_Response {

	private int $active_allocations;
	private int $used_allocations;
	private int $expired_allocations;
	private string $observed_at;
	private ?string $pending_first_use_allocation;

	/**
	 * @param int    $active_allocations  Active allocation count.
	 * @param int    $used_allocations    Fully used allocation count.
	 * @param int    $expired_allocations Expired allocation count.
	 * @param string $observed_at         RFC 3339 observation time.
	 * @param string|null $pending_first_use_allocation Pending grant kind.
	 */
	private function __construct(
		int $active_allocations,
		int $used_allocations,
		int $expired_allocations,
		string $observed_at,
		?string $pending_first_use_allocation
	) {
		$this->active_allocations           = $active_allocations;
		$this->used_allocations             = $used_allocations;
		$this->expired_allocations          = $expired_allocations;
		$this->observed_at                  = $observed_at;
		$this->pending_first_use_allocation = $pending_first_use_allocation;
	}

	/**
	 * Build one validated response.
	 *
	 * Unknown fields are allowed so the endpoint can add data later.
	 *
	 * @param mixed $response Decoded response.
	 *
	 * @return self
	 */
	public static function from_array( $response ): self {
		if ( ! is_array( $response ) || true !== ( $response['success'] ?? null ) ) {
			throw new \UnexpectedValueException( 'The MCP allocation response is unavailable.' );
		}

		$active      = self::count_field( $response, 'active_allocations' );
		$used        = self::count_field( $response, 'used_allocations' );
		$expired     = self::count_field( $response, 'expired_allocations' );
		$observed_at = $response['observed_at'] ?? null;

		// Null is an authoritative "no pending grant" fact. Requiring the key
		// prevents an older three-count response from silently classifying a new
		// enrollee as someone who never had an allocation.
		if ( ! array_key_exists( 'pending_first_use_allocation', $response ) ) {
			throw new \UnexpectedValueException( 'The MCP pending first-use allocation fact is unavailable.' );
		}

		$pending = $response['pending_first_use_allocation'];
		if (
			null !== $pending
			&& ! in_array(
				$pending,
				array(
					Pending_First_Use_Allocation::PHASE_1_LITE,
					Pending_First_Use_Allocation::LEGACY_HEAD_START,
				),
				true
			)
		) {
			throw new \UnexpectedValueException( 'The MCP pending first-use allocation fact is invalid.' );
		}

		if ( null !== $pending && ( $active > 0 || $used > 0 || $expired > 0 ) ) {
			throw new \UnexpectedValueException( 'The MCP response cannot contain both a pending grant and allocation rows.' );
		}

		if (
			! is_string( $observed_at )
			|| 1 !== preg_match( '/\A\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d{1,6})?Z\z/', $observed_at )
		) {
			throw new \UnexpectedValueException( 'The MCP allocation observation time is invalid.' );
		}

		$format = false === strpos( $observed_at, '.' )
			? '!Y-m-d\TH:i:s\Z'
			: '!Y-m-d\TH:i:s.u\Z';
		$date   = \DateTimeImmutable::createFromFormat( $format, $observed_at, new \DateTimeZone( 'UTC' ) );
		$errors = \DateTimeImmutable::getLastErrors();

		if (
			false === $date
			|| ( is_array( $errors ) && ( $errors['warning_count'] > 0 || $errors['error_count'] > 0 ) )
		) {
			throw new \UnexpectedValueException( 'The MCP allocation observation time is invalid.' );
		}

		return new self( $active, $used, $expired, $observed_at, $pending );
	}

	/**
	 * @return int Active allocation count.
	 */
	public function active_allocations(): int {
		return $this->active_allocations;
	}

	/**
	 * @return int Fully used allocation count.
	 */
	public function used_allocations(): int {
		return $this->used_allocations;
	}

	/**
	 * @return int Expired allocation count.
	 */
	public function expired_allocations(): int {
		return $this->expired_allocations;
	}

	/**
	 * @return string RFC 3339 observation time.
	 */
	public function observed_at(): string {
		return $this->observed_at;
	}

	/**
	 * @return string|null Pending grant kind, or null when none is pending.
	 */
	public function pending_first_use_allocation(): ?string {
		return $this->pending_first_use_allocation;
	}

	/**
	 * @param array<string,mixed> $response Response fields.
	 * @param string              $field    Count field.
	 *
	 * @return int
	 */
	private static function count_field( array $response, string $field ): int {
		$value = $response[ $field ] ?? null;

		if ( ! is_int( $value ) || $value < 0 ) {
			throw new \UnexpectedValueException( 'An MCP allocation count is invalid.' );
		}

		return $value;
	}
}
