<?php

namespace Uncanny_Automator\Integrations\SureMembers;

/**
 * Class SureMembers_Integration
 *
 * @package Uncanny_Automator
 */
class SureMembers_Integration extends \Uncanny_Automator\Integration {

	/**
	 * Setup Automator integration.
	 *
	 * @return void
	 */
	protected function setup() {

		$this->helpers = new SureMembers_Helpers();
		$this->set_integration( 'SUREMEMBERS' );
		$this->set_name( 'SureMembers' );
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/suremembers-icon.svg' );

	}

	/**
	 * Load Integration Classes.
	 *
	 * @return void
	 */
	public function load() {
		$this->hooks_router();
		new User_Added_To_Group( $this->helpers );
		new User_Removed_From_Group( $this->helpers );
		new Add_User_To_Group( $this->helpers );
		new Remove_User_From_Group( $this->helpers );
	}

	/**
	 * plugin_active
	 *
	 * @return void
	 */
	public function plugin_active() {
		return defined( 'SUREMEMBERS_FILE' );
	}

	/**
	 * hooks_router
	 *
	 * SureMember's action passes an array of groups.
	 * We need to check all of them, so we will fire a custom hook on each group.
	 *
	 * @return void
	 */
	public function hooks_router() {
		add_action( 'suremembers_after_access_grant', array( $this, 'after_access_grant_router' ), 10, 2 );
		add_action( 'suremembers_after_access_revoke', array( $this, 'after_access_revoke_router' ), 10, 2 );
	}

	/**
	 * after_access_grant_router
	 *
	 * @param  mixed $user_id
	 * @param  mixed $groups
	 * @return void
	 */
	public function after_access_grant_router( $user_id, $groups ) {
		foreach ( $groups as $group ) {
			do_action( 'automator_suremembers_after_access_grant', $user_id, $group );
		}
	}

	/**
	 * after_access_revoke_router
	 *
	 * @param  mixed $user_id
	 * @param  mixed $groups
	 * @return void
	 */
	public function after_access_revoke_router( $user_id, $groups ) {
		foreach ( $groups as $group ) {
			do_action( 'automator_suremembers_after_access_revoke', $user_id, $group );
		}
	}
}
