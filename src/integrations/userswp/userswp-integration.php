<?php

namespace Uncanny_Automator\Integrations\Userswp;

use Uncanny_Automator\Integration;

/**
 * Class Userswp_Integration
 *
 * Free half of the UsersWP integration.
 *
 * @package Uncanny_Automator\Integrations\Userswp
 */
class Userswp_Integration extends Integration {

	/**
	 * Integration setup.
	 *
	 * @return void
	 */
	protected function setup() {

		$this->helpers = new Userswp_Helpers();

		$this->set_integration( 'USERSWP' );
		$this->set_name( 'UsersWP' );
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/userswp-icon.svg' );

		$this->set_plugin_file_path( 'userswp/userswp.php' );
		$this->set_developer_name( 'AyeCode Ltd' );
		$this->set_integration_type( 'plugin' );
		$this->set_distribution_type( 'wp_org' );
		$this->set_plugin_required( 'userswp/userswp.php' );
	}

	/**
	 * @return bool
	 */
	public function plugin_active() {
		return defined( 'USERSWP_VERSION' );
	}

	/**
	 * Run-time listeners.
	 *
	 * The change-password handler logs the user out and exits right after its
	 * hook, and the delete-account hook fires once the user is gone, so both
	 * users are captured at priority 1, while the hook runs.
	 *
	 * @return void
	 */
	public function load_shared_hooks() {

		Userswp_Login_Dispatcher::boot();

		$helpers = $this->helpers;

		add_action(
			'uwp_before_delete_account',
			function ( $user_id ) use ( $helpers ) {
				$helpers->snapshot_user( 'delete_account', $user_id );
			},
			1,
			1
		);

		add_action(
			'uwp_after_process_change',
			function () use ( $helpers ) {
				$helpers->snapshot_user( 'change_password', get_current_user_id() );
			},
			1,
			1
		);
	}

	/**
	 * @return void
	 */
	public function load() {

		$this->load_shared_hooks();

		new Userswp_User_Registers( $this->helpers );
		new Userswp_User_Logs_In( $this->helpers );
		new Userswp_User_Updates_Profile( $this->helpers );
		new Userswp_User_Activates_Email( $this->helpers );
		new Userswp_User_Changes_Password( $this->helpers );
		new Userswp_User_Deletes_Account( $this->helpers );

		new Userswp_Update_Profile_Fields( $this->helpers );
	}
}
