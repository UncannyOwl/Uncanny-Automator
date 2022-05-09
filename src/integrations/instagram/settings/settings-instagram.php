<?php
/**
 * Creates the settings page for Instagram.
 *
 * @since   3.7
 * @version 3.7
 * @package Uncanny_Automator
 * @author  Joseph G.
 */

namespace Uncanny_Automator;

/**
 * Instagram Settings
 */
class Instagram_Settings {

	/**
	 * This trait defines properties and methods shared across all the
	 * settings pages of Premium Integrations
	 */
	use Settings\Premium_Integrations;

	protected $helper = '';
	/**
	 * Creates the settings page
	 */
	public function __construct( $helper ) {

		$this->helper = $helper;

		// Register the tab
		$this->setup_settings();

		// The methods above load even if the tab is not selected
		if ( ! $this->is_current_page_settings() ) {
			return;
		}

		// Add localization strings
		$this->add_localization_strings();
	}

	/**
	 * Sets up the properties of the settings page
	 */
	protected function set_properties() {

		$is_user_connected = $this->get_helper()->is_user_connected();

		$this->set_id( 'instagram' );

		$this->set_icon( 'instagram' );

		$this->set_name( 'Instagram' );

		$this->set_status( $is_user_connected ? 'success' : '' );

		if ( $is_user_connected ) {
			$this->set_js( '/instagram/settings/assets/script.js' );
		}

		$this->set_css( '/instagram/settings/assets/style.css' );

	}

	/**
	 * Returns the helper class.
	 *
	 * @return object The helper object.
	 */
	public function get_helper() {

		return $this->helper;

	}

	/**
	 * Creates the output of the settings page
	 *
	 * @return void.
	 */
	public function output() {

		$fb_helper = null;

		if ( isset( Automator()->helpers->recipe->facebook->options ) ) {

			$fb_helper = Automator()->helpers->recipe->facebook->options;

		}

		$is_user_connected = $this->get_helper()->is_user_connected();

		$facebook_pages_settings_uri = $this->get_helper()->get_facebook_pages_settings_url();

		$facebook_pages_oauth_dialog_uri = $this->get_helper()->get_facebook_pages_oauth_dialog_uri();

		if ( $fb_helper ) {

			$facebook_user = (object) $fb_helper->get_user_connected();

			$disconnect_uri = $fb_helper->get_disconnect_url();

		}

		include_once 'view-instagram.php';

	}

	/**
	 * Adds translatable strings for the JS
	 */
	private function add_localization_strings() {
		// Update the main JS object
		add_filter(
			'automator_assets_backend_js_data',
			function( $data ) {
				// Add strings
				$data['i18n']['settingsInstagram'] = array(
					'linkedFacebookPage'      => esc_html__( 'Account linked to Facebook Page:', 'uncanny-automator' ),
					'connectInstagramAccount' => esc_html__( 'Connect Instagram account', 'uncanny-automator' ),
					'noInstagram'             => esc_html__( 'No Instagram Business or Professional account connected to this Facebook page.', 'uncanny-automator' ),
					/* translators: 1. Number of followers */
					'followers'               => esc_html_x( '%1$s followers', 'Instagram', 'uncanny-automator' ),
				);

				return $data;
			}
		);
	}
}
