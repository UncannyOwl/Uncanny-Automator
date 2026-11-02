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
		License_Transient_Keys::LICENSE,
		'automator_dashboard_recent_articles_posts',
		'automator_dashboard_recent_articles_failed',
		Hosted_Read_Transient_Keys::GENERATION,
		'automator_get_all_integrations',
		'automator_integration_directories_loaded',
		License_Transient_Keys::LICENSE_CHECK_FAILED,
		License_Transient_Keys::LICENSE_FAILURE_STREAK,
		'automator_load_errors',
		'automator_pro_integrations_list',
		'automator_pro_integrations_list_items',
		'automator_recipes_data',
		'automator_setup_wizard_error',
		'automator_test_remote_get',
		'automator_test_remote_post',
		License_Transient_Keys::CREDIT_DATA,
		License_Transient_Keys::CREDITS,
		License_Transient_Keys::LLM_ALLOCATION_FACTS,
		License_Transient_Keys::LLM_ALLOCATION_FAILED,
		License_Transient_Keys::FEATURE_STATE_LAST_KNOWN_GOOD,
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
