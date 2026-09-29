<?php
/**
 * Read-only feature-state diagnostics for the administrator status report.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Feature_State\Infrastructure;

use Uncanny_Automator\Admin_Settings_Uncanny_Agent_General;
use Uncanny_Automator\App\Application\Mcp\Mcp_Client;
use Uncanny_Automator\App\Feature_State\Domain\Feature_State_Policy;
use Uncanny_Automator\App\Feature_State\Domain\Pending_First_Use_Allocation;
use Uncanny_Automator\App\Transient\Domain\License_Transient_Keys;
use function Uncanny_Automator\App\Infrastructure\automator_feature_state_query;
use function Uncanny_Automator\App\Infrastructure\automator_license_manager;

/**
 * Exposes only policy-relevant facts; credentials and identity hashes stay private.
 */
final class Feature_State_Report {

	/**
	 * Build sections compatible with the existing copy-for-support status tables.
	 *
	 * Cached facts are observations at report time. Resolution metadata comes from
	 * the actual request query, including when it retained an earlier fallback.
	 * This collector never refreshes remote facts or invokes SDK initialization.
	 *
	 * @return array<string,array<string,string>>
	 */
	public function get(): array {
		if ( ! current_user_can( automator_get_capability() ) ) {
			return array();
		}

		$sections   = array();
		$collectors = array(
			'Feature state' => 'visibility',
			'Feature state resolution' => 'resolution',
			'Feature state license facts' => 'license_facts',
			'Feature state allocation facts' => 'allocation_facts',
			'Feature state cache and settings' => 'cache_and_settings',
		);
		foreach ( $collectors as $heading => $method ) {
			try {
				$sections[ $heading ] = $this->$method();
			} catch ( \Throwable $error ) {
				// Hooks can throw messages containing credentials. Use a fixed reason
				// instead of scrubbing all values, which can corrupt valid facts.
				$sections[ $heading ] = array( 'error' => 'This section could not be collected.' );
			}
		}
		return $sections;
	}

	/** Register the collector for HTML and other system-report consumers. */
	public function register(): void {
		add_filter( 'automator_system_report_get', array( $this, 'add_to_report' ) );
	}

	/**
	 * @param mixed $report Existing report, possibly changed by another plugin.
	 * @return array
	 */
	public function add_to_report( $report ): array {
		$report                  = is_array( $report ) ? $report : array();
		$report['feature_state'] = $this->get();
		return $report;
	}

	/** @return array<string,string> */
	private function visibility(): array {
		return automator_feature_state_query()->diagnostics()['visibility'];
	}

	/** @return array<string,string> */
	private function resolution(): array {
		$details = automator_feature_state_query()->diagnostics();
		return array(
			'policy_revision' => Feature_State_Policy::REVISION,
			'source' => $details['source'],
			'policy_state' => $this->format( $details['policy_state'] ),
			'resolution_error' => $this->format( $details['error'] ),
		);
	}

	/** @return array<string,string> */
	private function license_facts(): array {
		$licenses = automator_license_manager();
		$license  = $licenses->get_cached_license_data();
		$key      = trim( $licenses->get_key() );
		return array_merge(
			array(
				'pro_plugin_active'           => $this->format( $licenses->is_pro_active() ),
				'pro_license_key_present'     => $this->key_present( 'pro' ),
				'pro_license_status'          => $this->local_status( 'pro' ),
				'lite_license_key_present'    => $this->key_present( 'free' ),
				'lite_license_status'         => $this->local_status( 'free' ),
				'license_cache_present'       => $this->format( null !== $license ),
				'license_cache_key_matches'   => $this->format( null !== $license ? '' !== $key && is_string( $license['license_key'] ?? null ) && trim( $license['license_key'] ) === $key : null ),
				'extended_support_license'   => 'Unknown',
			),
			array( 'identifier' => $this->license_identifier( $license ) ),
			$this->fields( $license, array( 'license', 'item_name', 'is_lifetime' ) )
		);
	}

	/** @return array<string,string> */
	private function allocation_facts(): array {
		$key    = trim( automator_license_manager()->get_key() );
		$cached = get_transient( Mcp_Allocation_Facts_Reader::TRANSIENT );
		$facts  = is_array( $cached ) ? ( $cached['facts'] ?? null ) : null;
		$hash   = is_array( $cached ) ? ( $cached['license_key_hash'] ?? null ) : null;
		return array_merge(
			array(
				'allocation_cache_present'     => $this->format( false !== $cached ),
				'allocation_schema_version'    => $this->field( $cached, 'schema_version' ),
				'allocation_cache_key_matches' => $this->format( false !== $cached ? '' !== $key && is_string( $hash ) && hash_equals( hash( 'sha256', $key ), $hash ) : null ),
			),
			$this->fields( $facts, array( 'success', 'active_allocations', 'used_allocations', 'expired_allocations', 'pending_first_use_allocation', 'observed_at' ) )
		);
	}

	/** @return array<string,string> */
	private function cache_and_settings(): array {
		$snapshot = get_transient( License_Transient_Keys::FEATURE_STATE_LAST_KNOWN_GOOD );
		$lock     = get_option( Mcp_Allocation_Facts_Refresh::LOCK_OPTION, 0 );
		$rows     = array(
			'allocation_service'              => $this->service_url(),
			'allocation_refresh_suppressed'   => $this->format( false !== get_transient( Mcp_Allocation_Facts_Refresh::FAILURE_TRANSIENT ) ),
			'allocation_refresh_lock_active'  => $this->format( is_numeric( $lock ) && (int) $lock > time() ),
			'allocation_cache_ttl_seconds'    => (string) Mcp_Allocation_Facts_Refresh::CACHE_DURATION,
			'allocation_failure_ttl_seconds'  => (string) Mcp_Allocation_Facts_Refresh::FAILURE_DURATION,
			'last_known_good_present'         => $this->format( false !== $snapshot ),
			'last_known_good_valid'           => $this->format( null !== ( new WP_Last_Known_Feature_State_Store() )->load() ),
			'last_known_good_schema_version'  => $this->field( $snapshot, 'schema_version' ),
			'last_known_good_policy_revision' => $this->field( $snapshot, 'policy_revision' ),
			'last_known_good_saved_at_utc'    => is_array( $snapshot ) && is_int( $snapshot['resolved_at'] ?? null ) ? gmdate( 'Y-m-d H:i:s', $snapshot['resolved_at'] ) : 'Unavailable',
			'last_known_good_retention_seconds' => (string) WP_Last_Known_Feature_State_Store::RETENTION_SECONDS,
		);
		$settings = Admin_Settings_Uncanny_Agent_General::get_settings( true );
		$rows    += array(
			'launcher_tab_enabled'     => $this->format( $settings['enabled'] ?? null ),
			'launcher_top_bar_enabled' => $this->format( $settings['top_bar_button_enabled'] ?? null ),
			'admin_sdk_surface_allowed' => $this->format( (bool) apply_filters( 'automator_mcp_should_render_surface', true, 'admin_sdk' ) ),
		);
		return $rows;
	}

	/**
	 * Combine license, download, and price IDs in that order, without separators.
	 *
	 * @param array|null $license Cached license facts.
	 * @return string
	 */
	private function license_identifier( ?array $license ): string {
		$parts = array();
		foreach ( array( 'license_id', 'download_id', 'price_id' ) as $field ) {
			$value = $license[ $field ] ?? null;
			if ( ( ! is_int( $value ) && ! is_string( $value ) ) || ! ctype_digit( (string) $value ) ) {
				return 'Unavailable';
			}
			$parts[] = (string) $value;
		}
		return implode( '', $parts );
	}

	/**
	 * Report key presence without exposing the credential.
	 *
	 * @param string $type Local license type.
	 * @return string
	 */
	private function key_present( string $type ): string {
		$key = automator_get_option( 'uap_automator_' . $type . '_license_key', '' );
		if ( null !== $key && ! is_string( $key ) ) {
			return 'Invalid value';
		}
		return $this->format( '' !== trim( $key ?? '' ) );
	}

	/**
	 * Match the policy adapter's normalization of locally stored status options.
	 *
	 * @param string $type Local license type.
	 * @return string
	 */
	private function local_status( string $type ): string {
		$status = automator_get_option( 'uap_automator_' . $type . '_license_status', '' );
		if ( null !== $status && ! is_string( $status ) ) {
			return 'Invalid value';
		}
		return $this->format( trim( $status ?? '' ) );
	}

	/**
	 * Show the actual request endpoint with credentials, query and fragment removed.
	 * Sanitization is deliberate: a custom URL may contain secrets, so this is
	 * a safe diagnostic address rather than a byte-for-byte request URL.
	 *
	 * @return string
	 */
	private function service_url(): string {
		$parts = wp_parse_url( Mcp_Allocation_Facts_Refresh::endpoint_url( Mcp_Client::get_inference_url() ) );
		if ( ! is_array( $parts ) || empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
			return 'Unavailable';
		}
		return $parts['scheme'] . '://' . $parts['host']
			. ( isset( $parts['port'] ) ? ':' . $parts['port'] : '' )
			. ( $parts['path'] ?? '' );
	}

	/**
	 * Read an explicit allowlist, never a raw license or response dump.
	 *
	 * @param mixed    $data Cached data.
	 * @param string[] $keys Allowed fields.
	 * @return array<string,string>
	 */
	private function fields( $data, array $keys ): array {
		$values = array();
		foreach ( $keys as $key ) {
			$values[ $key ] = $this->field( $data, $key );
		}
		return $values;
	}

	/**
	 * Preserve the distinction between a missing fact, null, false, and zero.
	 *
	 * @param mixed  $data Cached data.
	 * @param string $key Field name.
	 * @return string
	 */
	private function field( $data, string $key ): string {
		if ( ! is_array( $data ) || ! array_key_exists( $key, $data ) ) {
			return 'Unavailable';
		}

		$value = $data[ $key ];
		// Do not turn malformed scalar types into apparently authoritative facts.
		// The policy readers require real booleans and non-negative integer counts.
		if ( null !== $value ) {
			$is_boolean = in_array( $key, array( 'success', 'is_lifetime' ), true );
			$is_count   = in_array( $key, array( 'active_allocations', 'used_allocations', 'expired_allocations', 'schema_version' ), true );
			$is_pending = 'pending_first_use_allocation' === $key;
			if (
				( $is_boolean && ! is_bool( $value ) )
				|| ( $is_count && ( ! is_int( $value ) || $value < 0 ) )
				|| ( $is_pending && ! in_array( $value, array( Pending_First_Use_Allocation::PHASE_1_LITE, Pending_First_Use_Allocation::LEGACY_HEAD_START ), true ) )
			) {
				return 'Invalid value';
			}
		}

		return $this->format( $value );
	}

	/**
	 * Format scalar observations without serializing unexpected objects or arrays.
	 *
	 * @param mixed $value Fact value.
	 * @return string
	 */
	private function format( $value ): string {
		if ( null === $value ) {
			return 'null';
		}
		if ( is_bool( $value ) ) {
			return $value ? 'true' : 'false';
		}
		if ( ! is_scalar( $value ) ) {
			return 'Invalid value';
		}
		return '' === (string) $value ? '(empty)' : (string) $value;
	}
}
