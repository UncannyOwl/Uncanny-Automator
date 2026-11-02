<?php
declare(strict_types=1);
namespace Uncanny_Automator\App\Infrastructure\Database;

use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Action_Error_Store;
use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Action_Store;
use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Mcp_Database_Boundary_Store;
use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Api_Log_Store;
use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Background_Job_Store;
use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Block_Modifier_Store;
use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Block_Store;
use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Execution_Log_Store;
use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Hosted_Read_Credential_Option_Store;
use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Recipe_Item_Status_Store;
use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Recipe_Reorder_Store;
use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Recipe_Legacy_Meta_Store;
use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Recipe_Store;
use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Recipe_Trigger_Store;
use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Platform_Inbox_Store;
use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Run_Snapshot_Store;
use Uncanny_Automator\App\Infrastructure\Database\Interfaces\User_Selector_Store;
use Uncanny_Automator\App\Infrastructure\Database\Stores\WP_Block_Modifier_Store;
use Uncanny_Automator\App\Infrastructure\Database\Stores\WP_Action_Error_Store;
use Uncanny_Automator\App\Infrastructure\Database\Stores\WP_Action_Store;
use Uncanny_Automator\App\Infrastructure\Database\Stores\WP_Mcp_Database_Boundary_Store;
use Uncanny_Automator\App\Infrastructure\Database\Stores\WP_Api_Log_Store;
use Uncanny_Automator\App\Infrastructure\Database\Stores\WP_Background_Job_Store;
use Uncanny_Automator\App\Infrastructure\Database\Stores\WP_Block_Store;
use Uncanny_Automator\App\Infrastructure\Database\Stores\WP_Execution_Log_Store;
use Uncanny_Automator\App\Infrastructure\Database\Stores\WP_Hosted_Read_Credential_Option_Store;
use Uncanny_Automator\App\Infrastructure\Database\Stores\WP_Recipe_Item_Status_Store;
use Uncanny_Automator\App\Infrastructure\Database\Stores\WP_Recipe_Reorder_Store;
use Uncanny_Automator\App\Infrastructure\Database\Stores\WP_Recipe_Legacy_Meta_Store;
use Uncanny_Automator\App\Infrastructure\Database\Stores\WP_Recipe_Store;
use Uncanny_Automator\App\Infrastructure\Database\Stores\WP_Recipe_Trigger_Store;
use Uncanny_Automator\App\Infrastructure\Database\Stores\WP_Platform_Inbox_Store;
use Uncanny_Automator\App\Infrastructure\Database\Stores\WP_Run_Snapshot_Store;
use Uncanny_Automator\App\Infrastructure\Database\Stores\WP_User_Selector_Store;
use Uncanny_Automator\App\Recipe_Builder\Trigger\Registry\Trigger_Registry;
use Uncanny_Automator\App\Recipe_Builder\Trigger\Registry\WP_Trigger_Registry;

/**
 * Database Factory.
 *
 * Factory class for creating database store instances.
 * Returns interface types — consumers depend on contracts, not WordPress implementations.
 *
 * @since 7.0.0
 */
final class Database {

	/** @var Hosted_Read_Credential_Option_Store|null */
	private static $hosted_read_credential_option_store = null;

	/** @var WP_Recipe_Store|null */
	private static $recipe_store = null;

	/** @var WP_Recipe_Trigger_Store|null */
	private static $recipe_trigger_store = null;

	/** @var WP_Action_Store|null */
	private static $action_store = null;

	/** @var WP_Trigger_Registry|null */
	private static $trigger_registry = null;

	/** @var WP_Api_Log_Store|null */
	private static $api_log_store = null;

	/** @var WP_Execution_Log_Store|null */
	private static $execution_log_store = null;

	/** @var WP_Action_Error_Store|null */
	private static $action_error_store = null;

	/** @var WP_Run_Snapshot_Store|null */
	private static $run_snapshot_store = null;

	/** @var WP_Platform_Inbox_Store|null */
	private static $platform_inbox_store = null;

	/**
	 * Memoised store for background app-action jobs.
	 *
	 * @var WP_Background_Job_Store|null
	 */
	private static $background_job_store = null;

	/**
	 * Memoised block store — the filter / loop / delay rows a recipe is built from.
	 *
	 * @var WP_Block_Store|null
	 */
	private static $block_store = null;

	/**
	 * Memoised user-selector store, holding who a recipe runs as.
	 *
	 * @var WP_User_Selector_Store|null
	 */
	private static $user_selector_store = null;

	/**
	 * Memoised block-modifier store — the filter conditions and loop filters attached to blocks.
	 *
	 * @var WP_Block_Modifier_Store|null
	 */
	private static $block_modifier_store = null;

	/**
	 * Memoised gateway to legacy `uo-recipe` post meta.
	 *
	 * @var WP_Recipe_Legacy_Meta_Store|null
	 */
	private static $recipe_legacy_meta_store = null;

	/**
	 * Memoised store for persisting the order of a recipe's items.
	 *
	 * @var WP_Recipe_Reorder_Store|null
	 */
	private static $recipe_reorder_store = null;

	/**
	 * Memoised store for each item's live / draft status.
	 *
	 * @var WP_Recipe_Item_Status_Store|null
	 */
	private static $recipe_item_status_store = null;

	/**
	 * Fresh metadata store for the current WordPress database connection.
	 *
	 * @since 8.0.0
	 * @return Mcp_Database_Boundary_Store
	 */
	public static function get_mcp_database_boundary_store(): Mcp_Database_Boundary_Store {
		global $wpdb;
		return new WP_Mcp_Database_Boundary_Store( $wpdb );
	}

	/**
	 * Authoritative hosted read-credential option reader.
	 *
	 * @since 8.0.0
	 */
	public static function get_hosted_read_credential_option_store(): Hosted_Read_Credential_Option_Store {
		if ( null === self::$hosted_read_credential_option_store ) {
			global $wpdb;
			self::$hosted_read_credential_option_store = new WP_Hosted_Read_Credential_Option_Store( $wpdb );
		}
		return self::$hosted_read_credential_option_store;
	}

	/**
	 * @since 7.0.0
	 * @return Recipe_Store
	 */
	public static function get_recipe_store(): Recipe_Store {
		if ( null === self::$recipe_store ) {
			self::$recipe_store = new WP_Recipe_Store();
		}
		return self::$recipe_store;
	}

	/**
	 * @since 7.0.0
	 * @return Action_Store
	 */
	public static function get_action_store(): Action_Store {
		if ( null === self::$action_store ) {
			global $wpdb;
			self::$action_store = new WP_Action_Store( $wpdb );
		}
		return self::$action_store;
	}

	/**
	 * @since 7.0.0
	 * @return Recipe_Trigger_Store
	 */
	public static function get_recipe_trigger_store(): Recipe_Trigger_Store {
		if ( null === self::$recipe_trigger_store ) {
			global $wpdb;
			self::$recipe_trigger_store = new WP_Recipe_Trigger_Store( $wpdb );
		}
		return self::$recipe_trigger_store;
	}

	/**
	 * @since 7.0.0
	 * @return Trigger_Registry
	 */
	public static function get_trigger_registry(): Trigger_Registry {
		if ( null === self::$trigger_registry ) {
			self::$trigger_registry = new WP_Trigger_Registry();
		}
		return self::$trigger_registry;
	}

	/**
	 * @since 7.4.0
	 * @return Api_Log_Store
	 */
	public static function get_api_log_store(): Api_Log_Store {
		if ( null === self::$api_log_store ) {
			global $wpdb;
			self::$api_log_store = new WP_Api_Log_Store( $wpdb );
		}
		return self::$api_log_store;
	}

	/**
	 * @since 7.4.0
	 * @return Execution_Log_Store
	 */
	public static function get_execution_log_store(): Execution_Log_Store {
		if ( null === self::$execution_log_store ) {
			global $wpdb;
			self::$execution_log_store = new WP_Execution_Log_Store( $wpdb );
		}
		return self::$execution_log_store;
	}

	/**
	 * @since 7.4.0
	 * @return Action_Error_Store
	 */
	public static function get_action_error_store(): Action_Error_Store {
		if ( null === self::$action_error_store ) {
			global $wpdb;
			self::$action_error_store = new WP_Action_Error_Store( $wpdb );
		}
		return self::$action_error_store;
	}

	/**
	 * @since 7.4.0
	 * @return Run_Snapshot_Store
	 */
	public static function get_run_snapshot_store(): Run_Snapshot_Store {
		if ( null === self::$run_snapshot_store ) {
			global $wpdb;
			self::$run_snapshot_store = new WP_Run_Snapshot_Store( $wpdb );
		}
		return self::$run_snapshot_store;
	}

	/**
	 * Unified block-modifier Store factory.
	 *
	 * Returns the CPT-backed implementation today. Swapping in a future
	 * `Block_Modifier_Store_Db` (custom-table impl) is a one-line change here
	 * with zero caller updates — every consumer depends on the interface.
	 *
	 * @return Block_Modifier_Store
	 */
	public static function get_block_modifier_store(): Block_Modifier_Store {
		if ( null === self::$block_modifier_store ) {
			self::$block_modifier_store = new WP_Block_Modifier_Store();
		}
		return self::$block_modifier_store;
	}

	/**
	 * Legacy `uo-recipe` post-meta gateway used by the block migrators, the
	 * old-format importer and the export adapter.
	 *
	 * @return Recipe_Legacy_Meta_Store
	 */
	public static function get_recipe_legacy_meta_store(): Recipe_Legacy_Meta_Store {
		if ( null === self::$recipe_legacy_meta_store ) {
			self::$recipe_legacy_meta_store = new WP_Recipe_Legacy_Meta_Store();
		}
		return self::$recipe_legacy_meta_store;
	}

	/**
	 * Store for a recipe's filter, loop and delay blocks.
	 *
	 * @return Block_Store
	 */
	public static function get_block_store(): Block_Store {
		if ( null === self::$block_store ) {
			self::$block_store = new WP_Block_Store( self::get_block_modifier_store() );
		}
		return self::$block_store;
	}

	/**
	 * Store for the user a recipe runs as.
	 *
	 * @return User_Selector_Store
	 */
	public static function get_user_selector_store(): User_Selector_Store {
		if ( null === self::$user_selector_store ) {
			self::$user_selector_store = new WP_User_Selector_Store();
		}
		return self::$user_selector_store;
	}

	/**
	 * Store for persisting the order of a recipe's items after a drag in the builder.
	 *
	 * @return Recipe_Reorder_Store
	 */
	public static function get_recipe_reorder_store(): Recipe_Reorder_Store {
		if ( null === self::$recipe_reorder_store ) {
			self::$recipe_reorder_store = new WP_Recipe_Reorder_Store();
		}
		return self::$recipe_reorder_store;
	}

	/**
	 * Store for the live / draft status carried by each recipe item.
	 *
	 * @return Recipe_Item_Status_Store
	 */
	public static function get_recipe_item_status_store(): Recipe_Item_Status_Store {
		if ( null === self::$recipe_item_status_store ) {
			self::$recipe_item_status_store = new WP_Recipe_Item_Status_Store();
		}
		return self::$recipe_item_status_store;
	}

	/**
	 * @since 8.0.0
	 * @return Platform_Inbox_Store
	 */
	public static function get_platform_inbox_store(): Platform_Inbox_Store {
		if ( null === self::$platform_inbox_store ) {
			global $wpdb;
			self::$platform_inbox_store = new WP_Platform_Inbox_Store( $wpdb );
		}
		return self::$platform_inbox_store;
	}

	/**
	 * Store for background app-action jobs (`uap_async_jobs`).
	 *
	 * @return Background_Job_Store
	 */
	public static function get_background_job_store(): Background_Job_Store {
		if ( null === self::$background_job_store ) {
			global $wpdb;
			self::$background_job_store = new WP_Background_Job_Store( $wpdb );
		}
		return self::$background_job_store;
	}
}
