<?php
/**
 * Cache-only MCP allocation-facts reader.
 *
 * @package Uncanny_Automator
 * @since 7.5.1.1
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Feature_State\Infrastructure;

use Uncanny_Automator\App\Feature_State\Domain\Allocation_Facts;
use Uncanny_Automator\App\Feature_State\Domain\Pending_First_Use_Allocation;
use Uncanny_Automator\App\Infrastructure\License\License_Manager;

/**
 * Reads allocation facts that the MCP refresh service stored locally.
 */
final class Mcp_Allocation_Facts_Reader {

	public const TRANSIENT      = 'automator_llm_allocation_facts';
	public const SCHEMA_VERSION = 2;

	private License_Manager $licenses;

	/**
	 * @param License_Manager $licenses Automator license manager.
	 */
	public function __construct( License_Manager $licenses ) {
		$this->licenses = $licenses;
	}

	/**
	 * Read facts without an HTTP request.
	 *
	 * The key hash binds the cached response to the selected local license. This
	 * check also protects sites when another plugin writes the transient directly.
	 *
	 * @return Allocation_Facts
	 */
	public function read(): Allocation_Facts {
		$license_key = trim( $this->licenses->get_key() );
		$cached      = get_transient( self::TRANSIENT );

		if ( '' === $license_key || ! is_array( $cached ) ) {
			throw new \UnexpectedValueException( 'The MCP allocation facts are not cached.' );
		}

		$cached_hash = $cached['license_key_hash'] ?? null;
		$facts       = $cached['facts'] ?? null;

		if (
			self::SCHEMA_VERSION !== ( $cached['schema_version'] ?? null )
			|| ! is_string( $cached_hash )
			|| ! hash_equals( hash( 'sha256', $license_key ), $cached_hash )
		) {
			throw new \UnexpectedValueException( 'The cached MCP allocation identity does not match the selected license.' );
		}

		$response = Mcp_Allocation_Facts_Response::from_array( $facts );
		$pending  = $response->pending_first_use_allocation();

		if ( Pending_First_Use_Allocation::PHASE_1_LITE === $pending ) {
			$pending_fact = Pending_First_Use_Allocation::phase_1_lite();
		} elseif ( Pending_First_Use_Allocation::LEGACY_HEAD_START === $pending ) {
			$pending_fact = Pending_First_Use_Allocation::legacy_head_start();
		} else {
			$pending_fact = Pending_First_Use_Allocation::none();
		}

		return Allocation_Facts::observed(
			$response->active_allocations(),
			$response->used_allocations(),
			$response->expired_allocations(),
			$pending_fact
		);
	}
}
