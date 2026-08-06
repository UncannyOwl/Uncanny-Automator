<?php
/**
 * Automator Free core transient keys.
 *
 * @package Uncanny_Automator
 * @since 7.5.0
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Transient\Domain;

/**
 * Lists the critical transients owned by Automator Free.
 */
class Core_Transient_Keys {

	/**
	 * Critical transients owned by Automator Free.
	 *
	 * @var string[]
	 */
	private static array $keys = array(
		'automator_actionified_triggers',
		'automator_all_integration_items',
		'automator_api_license',
		'automator_dashboard_recent_articles_posts',
		'automator_get_all_integrations',
		'automator_integration_collection_items',
		'automator_integration_directories_loaded',
		'automator_license_check_failed',
		'automator_load_errors',
		'automator_pro_integrations_list',
		'automator_pro_integrations_list_items',
		'automator_recipes_data',
		'automator_setup_wizard_error',
		'automator_test_remote_get',
		'automator_test_remote_post',
		'automator_api_credit_data',
		'automator_api_credits',
		'automator_transient_users',
		'uap_complete_json',
	);

	/**
	 * Get the Automator Free core transient keys.
	 *
	 * @return string[]
	 */
	public static function get_all(): array {
		return self::$keys;
	}
}
