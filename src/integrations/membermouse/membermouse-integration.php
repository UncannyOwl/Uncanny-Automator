<?php

namespace Uncanny_Automator\Integrations\MemberMouse;

use Uncanny_Automator\Integration;

/**
 * Class Membermouse_Integration
 *
 * @pacakge Uncanny_Automator
 */
class Membermouse_Integration extends Integration {

	/**
	 * Must use function in new integration to setup all required values
	 *
	 * @return mixed
	 */
	protected function setup() {
		$this->helpers = new Membermouse_Helpers();
		$this->set_integration( 'MEMBER_MOUSE' );
		$this->set_name( 'MemberMouse' );
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/membermouse-icon.svg' );
	}

	/**
	 * Load Integration Classes.
	 *
	 * @return void
	 */
	public function load() {
		// Load triggers.
		new MM_MEMBER_CREATED( $this->helpers );
		new MM_MEMBER_UPDATED( $this->helpers );
		new MM_MEMBER_ACCOUNT_DELETED( $this->helpers );
		new MM_ORDER_SUBMITTED( $this->helpers );
		new MM_REFUND_ISSUED( $this->helpers );
		new MM_RENEWAL_RECEIVED( $this->helpers );
		new MM_BUNDLE_ADDED_TO_MEMBERS_ACCOUNT( $this->helpers );

		// Load actions.
		new MM_REMOVE_BUNDLE_FROM_MEMBERS_ACCOUNT( $this->helpers );
	}

	/**
	 * Check if Plugin is active.
	 *
	 * @return bool
	 */
	public function plugin_active() {
		return class_exists( 'MemberMouse' );
	}
}
