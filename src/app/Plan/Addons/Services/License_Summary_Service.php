<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Plan\Addons\Services;

use Uncanny_Automator\App\Infrastructure\License\License_Manager;
use Uncanny_Automator\App\Plan\Addons\Value_Objects\Addon;
use Uncanny_Automator\App\Plan\Addons\Value_Objects\License_Summary_Snapshot;
use Uncanny_Automator\App\Plan\Addons\Value_Objects\Summary_Scenario;
use Uncanny_Automator\App\Plan\Services\Plugin\Plugin_Service;

use function Uncanny_Automator\App\Infrastructure\automator_license_manager;

/**
 * Resolves the license state the AddOns summary renders.
 *
 * One read of the license payload produces the scenario, the owner, the
 * resolved plan and the addon counts, so the summary route cannot disagree
 * with itself.
 */
final class License_Summary_Service {

	/**
	 * Plan slugs a storefront plan_name may merely echo back.
	 *
	 * @var array<int,string>
	 */
	const TIER_SLUGS = array( 'lite', Addon::TIER_BASIC, Addon::TIER_PLUS, Addon::TIER_ELITE );

	/**
	 * Shared instance.
	 *
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * Injected license reader, when the caller supplied one.
	 *
	 * @var License_Manager|null
	 */
	private ?License_Manager $license_manager;

	/**
	 * Addon catalog.
	 *
	 * @var Addon_Catalog_Service
	 */
	private Addon_Catalog_Service $catalog;

	/**
	 * Addon access gate.
	 *
	 * @var Addon_Entitlement_Service
	 */
	private Addon_Entitlement_Service $entitlement;

	/**
	 * Plugin install lookups.
	 *
	 * @var Plugin_Service
	 */
	private Plugin_Service $plugins;

	/**
	 * Construct with lazy-default dependencies.
	 *
	 * @param License_Manager|null           $license_manager License reader.
	 * @param Addon_Catalog_Service|null     $catalog         Addon catalog.
	 * @param Addon_Entitlement_Service|null $entitlement     Access gate.
	 * @param Plugin_Service|null            $plugins         Plugin lookups.
	 */
	private function __construct(
		?License_Manager $license_manager = null,
		?Addon_Catalog_Service $catalog = null,
		?Addon_Entitlement_Service $entitlement = null,
		?Plugin_Service $plugins = null
	) {
		$this->license_manager = $license_manager;
		$this->catalog         = $catalog ?? Addon_Catalog_Service::instance();
		// A caller that pins the license manager but not the gate would
		// otherwise get a snapshot half-resolved from the global one.
		$this->entitlement = $entitlement ?? (
			null === $license_manager
				? Addon_Entitlement_Service::instance()
				: Addon_Entitlement_Service::create_with_dependencies( $license_manager, $this->catalog )
		);
		$this->plugins     = $plugins ?? new Plugin_Service();
	}

	/**
	 * Shared-instance accessor.
	 *
	 * @return self
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Build with explicit dependencies.
	 *
	 * @param License_Manager|null           $license_manager License reader.
	 * @param Addon_Catalog_Service|null     $catalog         Addon catalog.
	 * @param Addon_Entitlement_Service|null $entitlement     Access gate.
	 * @param Plugin_Service|null            $plugins         Plugin lookups.
	 *
	 * @return self
	 */
	public static function create_with_dependencies(
		?License_Manager $license_manager = null,
		?Addon_Catalog_Service $catalog = null,
		?Addon_Entitlement_Service $entitlement = null,
		?Plugin_Service $plugins = null
	): self {
		return new self( $license_manager, $catalog, $entitlement, $plugins );
	}

	/**
	 * The license reader.
	 *
	 * Resolved per call rather than captured at construction: the shared
	 * instance outlives any single license read, and the license manager can
	 * be replaced (after an activation, or by a test seam) mid-request.
	 *
	 * @return License_Manager
	 */
	private function license_manager(): License_Manager {
		return $this->license_manager ?? automator_license_manager();
	}

	/**
	 * Resolve the current license summary state.
	 *
	 * @return License_Summary_Snapshot
	 */
	public function get_snapshot(): License_Summary_Snapshot {

		$license  = $this->license_manager()->get_license_data();
		$resolved = $this->license_manager()->get_resolved_plan();
		$status   = $this->resolve_status( $license, $resolved );
		$scenario = $this->scenario( $status );

		return new License_Summary_Snapshot(
			$scenario,
			$this->owner_name( $license ),
			$this->owner_email( $license ),
			$resolved,
			$this->plan_display_label( $resolved ),
			$status,
			// A license the cross-check just invalidated entitles nothing, even
			// though the payload it came from still names a tier. Legacy reached
			// the same answer through its raw license-key gate.
			$scenario->is_active_license() ? $this->entitlement->available_count() : 0,
			$this->catalog->total_count()
		);
	}

	/**
	 * Resolve the current license scenario alone, without the addon counts.
	 *
	 * @return Summary_Scenario
	 */
	public function get_scenario(): Summary_Scenario {

		$license  = $this->license_manager()->get_license_data();
		$resolved = $this->license_manager()->get_resolved_plan();

		return $this->scenario( $this->resolve_status( $license, $resolved ) );
	}

	/**
	 * The scenario for a cross-checked license status.
	 *
	 * @param string $status Cross-checked license status.
	 *
	 * @return Summary_Scenario
	 */
	private function scenario( string $status ): Summary_Scenario {

		return Summary_Scenario::from_state(
			$this->plugins->is_pro_installed(),
			$this->license_manager()->is_pro_active(),
			$status,
			$this->entitled_tier()
		);
	}

	/**
	 * The license status, cross-checked against the locally stored Pro
	 * status so a stale cached payload cannot keep reporting valid after Pro
	 * was deactivated or its key removed.
	 *
	 * @param array<string,mixed>|null $license  License payload.
	 * @param string                   $resolved Resolved plan slug.
	 *
	 * @return string
	 */
	private function resolve_status( ?array $license, string $resolved ): string {

		if ( null === $license ) {
			return 'invalid';
		}

		$status = isset( $license['license'] ) ? (string) $license['license'] : 'invalid';

		if ( 'valid' === $status && 'lite' !== $resolved && 'pro' !== $this->license_manager()->get_type() ) {
			return 'invalid';
		}

		return $status;
	}

	/**
	 * The highest tier this license entitles.
	 *
	 * @return string
	 */
	private function entitled_tier(): string {

		if ( $this->entitlement->has_access_to_tier( Addon::TIER_ELITE ) ) {
			return Addon::TIER_ELITE;
		}

		if ( $this->entitlement->has_access_to_tier( Addon::TIER_PLUS ) ) {
			return Addon::TIER_PLUS;
		}

		return Addon::TIER_BASIC;
	}

	/**
	 * The license owner's name.
	 *
	 * @param array<string,mixed>|null $license License payload.
	 *
	 * @return string
	 */
	private function owner_name( ?array $license ): string {

		$name = null === $license || empty( $license['customer_name'] )
			? ''
			: (string) $license['customer_name'];

		return '' === $name
			? esc_html_x( 'Not connected', 'Addons', 'uncanny-automator' )
			: $name;
	}

	/**
	 * The license owner's email.
	 *
	 * The frontend requires a valid address, so an unconnected site reports
	 * the site admin email — globally available and independent of whoever
	 * is viewing the page.
	 *
	 * @param array<string,mixed>|null $license License payload.
	 *
	 * @return string
	 */
	private function owner_email( ?array $license ): string {

		$email = null === $license || empty( $license['customer_email'] )
			? ''
			: (string) $license['customer_email'];

		return '' === $email ? (string) get_bloginfo( 'admin_email' ) : $email;
	}

	/**
	 * The plan label to display.
	 *
	 * Prefers the storefront plan_name ("Plus AI + Automation Monthly"),
	 * falling back to the capitalised tier when it is empty or merely echoes
	 * the slug.
	 *
	 * @param string $resolved Resolved plan slug.
	 *
	 * @return string
	 */
	private function plan_display_label( string $resolved ): string {

		$plan_name = $this->license_manager()->get_resolved_plan_name();

		if ( '' !== $plan_name && ! in_array( strtolower( $plan_name ), self::TIER_SLUGS, true ) ) {
			return $plan_name;
		}

		return ucfirst( $resolved );
	}
}
