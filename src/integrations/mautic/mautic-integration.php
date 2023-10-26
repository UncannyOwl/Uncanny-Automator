<?php
namespace Uncanny_Automator\Integrations\Mautic;

use Uncanny_Automator\Integration;

/**
 * @package Uncanny_Automator\Integrations\Mautic
 */
class Mautic_Integration extends Integration {

	/**
	 * @var Mautic_Helpers
	 */
	protected $helpers = null;

	/**
	 * @var string
	 */
	const ID = 'MAUTIC';

	/**
	 * @return void
	 */
	protected function setup() {

		// Overwrite the parent's helper property with our helper.
		$this->helpers = new Mautic_Helpers();

		$this->load_hooks();

		$connected = ! empty( automator_get_option( 'automator_mautic_resource_owner', false ) );

		$this->set_integration( self::ID );
		$this->set_name( ucfirst( strtolower( self::ID ) ) );
		$this->set_connected( $connected );
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/mautic-icon.svg' );
		$this->set_settings_url( automator_get_premium_integrations_settings_url( 'mautic' ) );

	}

	/**
	 * @return void
	 */
	protected function load_hooks() {

		// Settings page.
		add_action( 'current_screen', array( $this->helpers, 'register_settings' ) );

		// Disconnect.
		add_action( 'wp_ajax_automator_mautic_disconnect_client', array( $this->helpers, 'disconnect_client' ) );

		// Contact fields.
		add_action( 'wp_ajax_automator_mautic_render_contact_fields', array( $this->helpers, 'render_contact_fields' ) );

		// Fetch the segments.
		add_action( 'wp_ajax_automator_mautic_segment_fetch', array( $this->helpers, 'segments_fetch' ) );

	}

	/**
	 * @return void
	 */
	public function load() {

		// Helpers.
		new Mautic_Settings( $this->helpers );
		// Actions.
		new CONTACT_UPSERT();
		new SEGMENT_CREATE();
		new SEGMENT_CONTACT_ADD();
		new SEGMENT_CONTACT_REMOVE();

	}
}
