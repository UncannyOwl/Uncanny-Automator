<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Plan\Addons\Services;

use Uncanny_Automator\App\Infrastructure\License\License_Manager;
use Uncanny_Automator\App\Infrastructure\Plan\Plan_Implementation;
use Uncanny_Automator\App\Plan\Domain\Plan_Levels;
use Uncanny_Automator\App\Plan\Addons\Value_Objects\Addon;

use function Uncanny_Automator\App\Infrastructure\automator_license_manager;

/**
 * The single gate deciding which addon tiers this site may install.
 *
 * Everything on the addons surface — list access, card CTAs, the summary
 * counts, the installer — asks this service, so there is one place the policy
 * can be read and changed.
 *
 * It runs on the concrete License_Manager because only `get_resolved_plan()`
 * collapses a broken license (`lite` when Pro is inactive, `basic` when there
 * is no key or the payload is not valid). Plan_Service does not, so an
 * expired Elite license would hand out Elite addons.
 */
final class Addon_Entitlement_Service {

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
	 * Construct with lazy-default dependencies.
	 *
	 * @param License_Manager|null       $license_manager License reader.
	 * @param Addon_Catalog_Service|null $catalog         Addon catalog.
	 */
	private function __construct( ?License_Manager $license_manager = null, ?Addon_Catalog_Service $catalog = null ) {
		$this->license_manager = $license_manager;
		$this->catalog         = $catalog ?? Addon_Catalog_Service::instance();
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
	 * @param License_Manager|null       $license_manager License reader.
	 * @param Addon_Catalog_Service|null $catalog         Addon catalog.
	 *
	 * @return self
	 */
	public static function create_with_dependencies( ?License_Manager $license_manager = null, ?Addon_Catalog_Service $catalog = null ): self {
		return new self( $license_manager, $catalog );
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
	 * The plan this site resolves to (lite|basic|plus|elite).
	 *
	 * @return string
	 */
	public function resolved_plan(): string {
		return $this->license_manager()->get_resolved_plan();
	}

	/**
	 * Whether this site may install the addons of a tier.
	 *
	 * @param string $tier Tier slug (basic|plus|elite).
	 *
	 * @return bool
	 */
	public function has_access_to_tier( string $tier ): bool {

		$current  = self::plan_level( $this->resolved_plan() );
		$required = self::plan_level( $tier );

		if ( ! Plan_Implementation::is_valid( $current ) || ! Plan_Implementation::is_valid( $required ) ) {
			return false;
		}

		return ( new Plan_Implementation( $current ) )->is_at_least( new Plan_Implementation( $required ) );
	}

	/**
	 * Translate a bare tier slug into the Plan_Levels vocabulary the plan
	 * hierarchy is expressed in.
	 *
	 * The license manager and the addons feed both speak bare slugs
	 * (`basic`), while `Plan_Implementation::HIERARCHY` is keyed on
	 * `pro-basic`. Same mapping Plan_Factory applies to the API value.
	 *
	 * @param string $tier Bare tier slug.
	 *
	 * @return string
	 */
	private static function plan_level( string $tier ): string {
		return 'lite' === $tier ? Plan_Levels::LITE : 'pro-' . $tier;
	}

	/**
	 * How many addons the resolved plan includes.
	 *
	 * Answers the tier question only. A payload that is present but not
	 * valid resolves to the basic plan, so this reports the basic count
	 * rather than zero; callers that must reflect license validity gate on
	 * the summary scenario first, as License_Summary_Service does.
	 *
	 * @return int
	 */
	public function available_count(): int {

		if ( null === $this->license_manager()->get_license_data() ) {
			return 0;
		}

		return $this->catalog->count_for_plan( $this->resolved_plan() );
	}
}
