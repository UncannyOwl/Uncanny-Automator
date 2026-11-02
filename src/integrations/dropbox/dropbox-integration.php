<?php
/**
 * Dropbox Integration
 *
 * @package Uncanny_Automator
 * @author  Uncanny Owl
 */

namespace Uncanny_Automator\Integrations\Dropbox;

use Uncanny_Automator\App_Integrations\App_Integration;

/**
 * Class Dropbox_Integration
 *
 * @package Uncanny_Automator\Integrations\Dropbox
 */
class Dropbox_Integration extends App_Integration {

	/**
	 * Get the integration configuration.
	 *
	 * @return array
	 */
	public static function get_config() {
		return array(
			'integration'  => 'DROPBOX',
			'name'         => 'Dropbox',
			'api_endpoint' => 'v3/dropbox',
			'settings_id'  => 'dropbox',
		);
	}

	/**
	 * Setup the integration.
	 *
	 * @return void
	 */
	protected function setup() {
		$this->helpers = new Dropbox_App_Helpers( self::get_config() );
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/dropbox-icon.svg' );
		$this->setup_app_integration( self::get_config() );

		$this->set_developer_name( 'Dropbox' );
		$this->set_integration_type( 'app' );
		$this->set_distribution_type( 'saas' );
	}

	/**
	 * Load the integration components.
	 *
	 * @return void
	 */
	public function load() {
		// Load settings
		new Dropbox_Settings( $this->dependencies, $this->get_settings_config() );

		// Load actions
		new Dropbox_Upload_File( $this->dependencies );
		new Dropbox_Create_Folder( $this->dependencies );
		new Dropbox_Delete_Item( $this->dependencies );
		new Dropbox_Get_Shareable_Link( $this->dependencies );
		new Dropbox_Move_File( $this->dependencies );
		new Dropbox_Copy_File( $this->dependencies );
		new Dropbox_Share_Folder( $this->dependencies );
		new Dropbox_Add_Folder_Member( $this->dependencies );
		new Dropbox_Add_File_Member( $this->dependencies );

		// Load triggers
		new Dropbox_File_Folder_Created( $this->dependencies );
		new Dropbox_File_Updated( $this->dependencies );
		new Dropbox_File_Folder_Renamed( $this->dependencies );
		new Dropbox_File_Folder_Moved( $this->dependencies );
		new Dropbox_File_Folder_Deleted( $this->dependencies );
	}

	/**
	 * Check if the integration is connected.
	 *
	 * @return bool
	 */
	protected function is_app_connected() {
		try {
			$credentials = $this->helpers->get_credentials();
			// connection_id presence in the v3 envelope IS connectedness —
			// the registry row exists + V3ConnectionAuth will succeed.
			// `external_user_id` is display-time correlation only and may be
			// empty until the first `get-account-info` round-trip caches it.
			return ! empty( $credentials['connection_id'] );
		} catch ( \Exception $e ) {
			return false;
		}
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		// Turns each change the platform delivers into its trigger's hook.
		( new Dropbox_Event_Fanout() )->register();

		// Registers triggers that were on for a connection that got a new id.
		( new Dropbox_Trigger_Subscription( $this->dependencies->api, $this->dependencies->webhooks ) )->register();
	}
}
