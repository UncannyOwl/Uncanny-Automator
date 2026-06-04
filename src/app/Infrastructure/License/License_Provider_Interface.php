<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Infrastructure\License;

/**
 * Interface License_Provider_Interface
 *
 * Minimal contract for license information needed by License_Header_Injector
 * and other infrastructure components that only require identity / routing
 * fields (key, type, site, item). Lives flat alongside its implementations
 * (`License_Manager`, etc.) per the app-layer skill rule against
 * `contracts/` subdirectories.
 *
 * Scope boundary — what this interface intentionally does NOT cover:
 *
 *   - Plan resolution (`get_plan_name()`, `get_resolved_plan()`,
 *     `get_resolved_plan_name()`)
 *   - Pro plugin activation state (`is_pro_active()`)
 *
 * Those methods live on the concrete `License_Manager` because they are
 * presentation/CTA concerns consumed by `License_Summary`, not header-
 * injection inputs. Callers that need them should depend on the concrete
 * class. If a second consumer ever needs them through an abstraction,
 * introduce a separate richer interface rather than widening this one.
 *
 * @since 7.0.0
 * @package Uncanny_Automator\App\Infrastructure\License
 */
interface License_Provider_Interface {

	/**
	 * Get the license key.
	 *
	 * @return string The license key, or empty string if none.
	 */
	public function get_key(): string;

	/**
	 * Get the license type.
	 *
	 * @return string 'pro', 'free', or empty string.
	 */
	public function get_type(): string;

	/**
	 * Get the site name (home URL without protocol).
	 *
	 * @return string
	 */
	public function get_site_name(): string;

	/**
	 * Get the item name for the license.
	 *
	 * @return string The item name constant value, or empty string.
	 */
	public function get_item_name(): string;
}
