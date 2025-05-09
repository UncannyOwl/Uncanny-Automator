<?php

namespace Uncanny_Automator\Services\Addons\Lists;

use Uncanny_Automator\Services\Addons\Data\License_Summary;
use Uncanny_Automator\Services\Addons\Data\Plan_Resolver;
use Uncanny_Automator\Services\Plugin\Info;

/**
 * Class Plan_List
 *
 * - Retrieves the addons list.
 * - Formats the addons list.
 * - Checks if the user has access to the addon plan.
 * - Generates an optional page notice for the list.
 * - Generates the CTAs for the addon.
 *
 * @package Uncanny_Automator\Services\Addons\Lists
 */
class Plan_List {

	/**
	 * The plan resolver.
	 *
	 * @var Plan_Resolver
	 */
	private $plan_resolver;

	/**
	 * The User's license summary.
	 *
	 * @var array
	 */
	private $license_summary;

	/**
	 * Constructor.
	 *
	 * @return void
	 */
	public function __construct() {
		$this->license_summary = ( new License_Summary() )->get_license_summary();
		$this->plan_resolver   = new Plan_Resolver();
	}

	/**
	 * Get the addons list.
	 *
	 * @param string $plan The plan.
	 *
	 * @return array
	 */
	public function get_list( $plan ) {

		// Get the addons for the plan.
		$is_elite = 'elite' === $plan;
		$addons   = $this->plan_resolver->filter_by_plan( $plan, $is_elite );

		// Check if the user has access to the addon group.
		$has_access = $this->plan_resolver->has_access_to_plan( $plan );

		// Generate the scenario ID.
		$scenario_id = $this->generate_scenario_id( $plan, $has_access );

		return array(
			'scenario_id' => $scenario_id,
			'has_access'  => $has_access,
			'page_notice' => $this->get_page_notice( $scenario_id, $plan, $has_access ),
			'addons'      => $this->format_addons_list( $addons, $has_access ),
		);
	}

	/**
	 * Get the addon by ID.
	 *
	 * @param array $addon The addon.
	 *
	 * @return array
	 */
	public function get_single_addon_list_item( $addon ) {
		$plan       = (bool) $addon['is_elite_specific'] ? 'elite' : 'plus';
		$has_access = $this->plan_resolver->has_access_to_plan( $plan );
		return $this->format_addon( $addon, $has_access );
	}

	/**
	 * Generate the scenario ID.
	 *
	 * @param string $plan The list code.
	 * @param bool   $has_access Whether the user has access to the addon group.
	 *
	 * @return string
	 */
	private function generate_scenario_id( $plan, $has_access ) {
		if ( $has_access ) {
			return 'has-access';
		}

		$pro_installed = Info::is_pro_plugin_installed();
		$pro_active    = Info::is_pro_plugin_active();

		// Prefix if pro is installed and active with check for valid license.
		$id = $pro_installed
			? ( $pro_active ? 'pro-installed' : 'pro-installed-but-deactivated' )
			: 'pro-not-installed';

		// Determine the requires plan suffix without leading dash
		$suffix = 'elite' === $plan
			? 'requires-elite'
			: 'requires-pro-' . $plan;

		// If not installed or active, add the requires suffix with a dash
		if ( ! $pro_installed || ! $pro_active ) {
			$id .= '-' . $suffix;
			return $id;
		}

		// Check if license valid or not and append plan or status to the id
		$id .= 'valid' === $this->license_summary['status']
			? '-license-active-pro-' . $this->slugify_string( $this->license_summary['current_plan'] )
			: '-license-' . $this->slugify_string( $this->license_summary['status'] );

		// Add the requires-plan suffix with a dash
		return $id . '-' . $suffix;
	}

	/**
	 * Get the page notice.
	 *
	 * @param string $scenario_id The scenario ID.
	 * @param string $plan The list code.
	 * @param bool   $has_access Whether the user has access to the addon group.
	 *
	 * @return array
	 */
	private function get_page_notice( $scenario_id, $plan, $has_access ) {
		if ( $has_access ) {
			return array();
		}
		$page_notice = new Page_Notice( $scenario_id, $plan );
		return $page_notice->get_page_notice();
	}

	/**
	* Format the addons list.
	*
	* @param array  $addons    The addons.
	* @param bool   $has_access Whether the user has access to the addon group.
	* @return array
	*/
	private function format_addons_list( $addons, $has_access ) {
		$data = array();
		foreach ( $addons as $addon ) {
			$data[] = $this->format_addon( $addon, $has_access );
		}
		return $data;
	}

	/**
	 * Format the addon.
	 *
	 * @param array $addon The addon.
	 * @param bool  $has_access Whether the user has access to the addon group.
	 *
	 * @return array
	 */
	private function format_addon( $addon, $has_access ) {
		return ( new Add_On_Card( $addon, $has_access ) )->get_card();
	}

	/**
	 * Slugify a string.
	 *
	 * @param string $string_to_slugify The string to slugify.
	 *
	 * @return string
	 */
	private function slugify_string( $string_to_slugify ) {
		return str_replace( '_', '-', sanitize_title( $string_to_slugify ) );
	}
}
