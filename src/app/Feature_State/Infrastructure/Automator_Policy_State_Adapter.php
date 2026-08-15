<?php
/**
 * Automator policy-state adapter.
 *
 * @package Uncanny_Automator
 * @since 7.5.1.1
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Feature_State\Infrastructure;

use Uncanny_Automator\App\Feature_State\Domain\Allocation_Facts;
use Uncanny_Automator\App\Feature_State\Domain\License_Facts;
use Uncanny_Automator\App\Feature_State\Domain\Policy_State;
use Uncanny_Automator\App\Feature_State\Domain\Policy_State_Resolver;
use Uncanny_Automator\App\Feature_State\Ports\Policy_State_Port;
use Uncanny_Automator\App\Infrastructure\License\License_Manager;

/**
 * Observes Automator-owned facts and delegates all policy interpretation.
 *
 * This adapter validates and translates storage/wire shapes, then hands those
 * facts to Policy_State_Resolver::resolve(). Keeping visibility choices in the
 * Domain gives reviewers one place to compare behavior with the Axis.
 */
final class Automator_Policy_State_Adapter implements Policy_State_Port {

	private License_Manager $licenses;
	private Mcp_Allocation_Facts_Reader $allocations;

	/**
	 * @param License_Manager             $licenses    Automator license manager.
	 * @param Mcp_Allocation_Facts_Reader $allocations Cache-only MCP allocation reader.
	 */
	public function __construct(
		License_Manager $licenses,
		Mcp_Allocation_Facts_Reader $allocations
	) {
		$this->licenses    = $licenses;
		$this->allocations = $allocations;
	}

	/**
	 * Get the current policy state from local observations.
	 *
	 * @return Policy_State
	 */
	public function get_state(): Policy_State {
		if ( ! $this->licenses->is_pro_active() ) {
			$license_key    = self::local_option_string(
				automator_get_option( 'uap_automator_free_license_key', '' )
			);
			$license_status = self::local_option_string(
				automator_get_option( 'uap_automator_free_license_status', '' )
			);

			$connected = 'valid' === $license_status && '' !== $license_key;

			// Connected Lite rows require a successful MCP allocation observation.
			// The local Free key and status are the connection authority. Unlike valid
			// Pro, Lite has no product catalog decision, so a separate Platform cache
			// would add an unrelated cold-cache failure to the direct MCP read.
			// A missing observation is unavailable; three zero counts are "never had".
			return Policy_State_Resolver::resolve(
				License_Facts::lite_only( $connected ),
				$connected ? $this->allocation_facts() : null
			);
		}

		$license_key    = self::local_option_string(
			automator_get_option( 'uap_automator_pro_license_key', '' )
		);
		$license_status = self::local_option_string(
			automator_get_option( 'uap_automator_pro_license_status', '' )
		);

		$license_entered = '' !== $license_key;
		$status          = $license_status;

		// These three local-only Pro rows are complete without a cached Platform
		// payload: no key, expired, and every other non-valid status. Requiring a
		// ledger here would incorrectly turn recovery UI into all-hidden on a cold
		// cache. It also keeps menu rendering cache-only and free of remote work.
		if ( ! $license_entered || 'valid' !== $status ) {
			return Policy_State_Resolver::resolve(
				License_Facts::pro( $license_entered, $status, null, null, null, null ),
				null
			);
		}

		$license = $this->validated_cached_license( $license_key );

		$download_id = self::nullable_integer_field( $license, 'download_id' );
		$price_id    = self::nullable_integer_field( $license, 'price_id' );
		$is_lifetime = self::nullable_boolean_field( $license, 'is_lifetime' );
		// v2/credits identifies lifetime licenses but does not expose the status
		// of their sibling extended-support license. Preserve that fact as unknown;
		// both lifetime Axis rows are all-hidden, so the application safely closes.
		$has_active_extended_support_license = null;
		$facts                               = License_Facts::pro(
			true,
			$status,
			$download_id,
			$price_id,
			$is_lifetime,
			$has_active_extended_support_license
		);

		if ( true === $is_lifetime ) {
			return Policy_State_Resolver::resolve( $facts, null );
		}

		return Policy_State_Resolver::resolve( $facts, $this->allocation_facts() );
	}

	/**
	 * Normalize the Automator option boundary without accepting corrupt shapes.
	 *
	 * Automator_Options deliberately calls its formatter with a null default
	 * while warming autoloaded values. The formatter therefore exposes a stored
	 * empty value as null even when this caller supplied an empty-string default.
	 * Accepting null here preserves the legitimate Pro "no license entered" row.
	 * We still accept only null and strings, so collections and objects remain
	 * malformed evidence and follow the normal fail-closed path.
	 *
	 * @see \Uncanny_Automator\Automator_Option_Formatter::format_value()
	 *
	 * @param mixed $value Option value.
	 *
	 * @return string
	 */
	private static function local_option_string( $value ): string {
		if ( null === $value ) {
			return '';
		}

		if ( ! is_string( $value ) ) {
			throw new \UnexpectedValueException( 'A local license option is malformed.' );
		}

		return trim( $value );
	}

	/**
	 * Read cached facts only when they belong to the selected valid license.
	 *
	 * @param string $license_key Locally selected license key.
	 *
	 * @return array<string,mixed>
	 */
	private function validated_cached_license( string $license_key ): array {
		$license = $this->licenses->get_cached_license_data();

		if ( null === $license ) {
			throw new \UnexpectedValueException( 'The valid license response is not cached.' );
		}

		// automator_api_license is shared with legacy code. Bind it to the exact
		// selected key before trusting catalog or allocation facts from that cache.
		if (
			! array_key_exists( 'license_key', $license )
			|| ! is_string( $license['license_key'] )
			|| trim( $license['license_key'] ) !== $license_key
			|| ! array_key_exists( 'license', $license )
			|| 'valid' !== $license['license']
		) {
			throw new \UnexpectedValueException( 'The cached license identity does not match the selected valid license.' );
		}

		return $license;
	}

	/**
	 * Translate the validated response into policy-relevant row counts.
	 *
	 * @return Allocation_Facts
	 */
	private function allocation_facts(): Allocation_Facts {
		return $this->allocations->read();
	}

	/**
	 * Read a nullable integer without coercing an unknown producer shape.
	 *
	 * @param array<string,mixed> $values Response values.
	 * @param string              $field  Field name.
	 *
	 * @return int|null
	 */
	private static function nullable_integer_field( array $values, string $field ): ?int {
		if ( ! array_key_exists( $field, $values ) || null === $values[ $field ] ) {
			return null;
		}

		if ( ! is_int( $values[ $field ] ) ) {
			throw new \UnexpectedValueException( 'A cached license identity field has an invalid type.' );
		}

		return $values[ $field ];
	}

	/**
	 * Read a nullable boolean without coercing an unknown producer shape.
	 *
	 * @param array<string,mixed> $values Response values.
	 * @param string              $field  Field name.
	 *
	 * @return bool|null
	 */
	private static function nullable_boolean_field( array $values, string $field ): ?bool {
		if ( ! array_key_exists( $field, $values ) || null === $values[ $field ] ) {
			return null;
		}

		if ( ! is_bool( $values[ $field ] ) ) {
			throw new \UnexpectedValueException( 'A cached license identity field has an invalid type.' );
		}

		return $values[ $field ];
	}
}
