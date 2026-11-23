<?php

namespace Uncanny_Automator\Integrations\Asgaros_Forum;

use Uncanny_Automator\Integration;
use Uncanny_Automator\Integrations\Asgaros_Forum\Dispatchers\Usergroup_Dispatcher;

/**
 * Class Asgaros_Forum_Integration
 *
 * @package Uncanny_Automator
 */
class Asgaros_Forum_Integration extends Integration {

	/**
	 * Metadata only. Declared identically in Pro, whose registration replaces this one.
	 *
	 * @return void
	 */
	protected function setup() {
		$this->helpers = new Asgaros_Forum_Helpers();

		$this->set_integration( 'ASGAROS_FORUM' );
		$this->set_name( 'Asgaros Forum' );
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/asgaros-forum-icon.svg' );

		$this->set_plugin_file_path( 'asgaros-forum/asgaros-forum.php' );
		$this->set_developer_name( 'Thomas Belser' );
		$this->set_integration_type( 'plugin' );
		$this->set_distribution_type( 'wp_org' );
	}

	/**
	 * Run-time listeners. Called first from load(), and alone in targeted mode.
	 * The usergroup dispatcher also serves Pro's "removed from" trigger.
	 *
	 * @return void
	 */
	protected function load_shared_hooks() {
		Usergroup_Dispatcher::boot();
	}

	/**
	 * @return void
	 */
	public function load() {

		$this->load_shared_hooks();

		new Asgaros_Forum_User_Publishes_Topic( $this->helpers );
		new Asgaros_Forum_User_Replies_To_Topic( $this->helpers );
		new Asgaros_Forum_User_Reacts_To_Post( $this->helpers );
		new Asgaros_Forum_Topic_Approved( $this->helpers );
		new Asgaros_Forum_User_Added_To_Usergroup( $this->helpers );

		new Asgaros_Forum_Create_Topic( $this->helpers );
		new Asgaros_Forum_Create_Reply( $this->helpers );
		new Asgaros_Forum_Add_User_To_Usergroup( $this->helpers );
		new Asgaros_Forum_Remove_User_From_Usergroup( $this->helpers );
	}

	/**
	 * Asgaros defines no constant or function; its main class is the signal.
	 *
	 * @return bool
	 */
	public function plugin_active() {
		return class_exists( 'AsgarosForum' );
	}
}
