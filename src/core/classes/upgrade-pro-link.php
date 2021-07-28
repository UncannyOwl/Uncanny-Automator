<?php

namespace Uncanny_Automator;

/**
 * Class Upgrade_Pro_Link
 * @package Upgrade_Pro_Link
 */

class Upgrade_Pro_Link {

	/**
	 * @var string The automator website's pricing page.
	 */
	const AUTOMATOR_PRICING_URI = '//automatorplugin.com/pricing';

	/**
	 * The upgrade uri.
	 *
	 * @var string
	 */
	protected $upgrade_uri = '';

	/**
	 * The property that we can use to pass the url query parameters for the pricing uri.
	 *
	 * @var array
	 */
	protected $upgrade_uri_param = array();

	/**
	 * Will hold the instance of Uncanny_Automator\Utilities
	 *
	 * @var Uncanny_Automator\Utilities
	 */
	protected $utilities = '';

	/**
	 * Our class constructor.
	 *
	 * @return void
	 */
	public function __construct() {

		// Setup the uri
		$this->set_upgrade_uri( self::AUTOMATOR_PRICING_URI );

		// Setup the uri parameters.
		$this->set_upgrade_uri_param(
			array(
				'utm_source'  => 'uncanny_automator',
				'utm_medium'  => 'plugins_page',
				'utm_content' => 'update_to_pro',
			)
		);

		$this->display_in_plugins_page();

	}

	/**
	 * Set some dependencies.
	 *
	 * @param  mixed $utilities
	 * @return void
	 */
	public function set_dependencies( Utilities $utilities ) {

		$this->utilities = $utilities;

	}

	/**
	 * Display the anchor markup for 'Upgrade to Pro' link into the plugins page.
	 *
	 * @param  array $existing_links The accepted argument for 'plugin_action_links_{path}' action hook.
	 * @return void
	 */
	public function display( $existing_links ) {

		$token = '<a target="_blank" style="font-weight: bold;" href="%1$s" title="%2$s">%2$s</a>';

		$html_link = array(
			sprintf(
				$token,
				$this->get_upgrade_link(),
				__( 'Upgrade to Pro', 'uncanny_automator' )
			),
		);

		$existing_links = $html_link + $existing_links;

		return $existing_links;

	}

	/**
	 * Setter method for prop: upgrade_uri_param
	 *
	 * @param  array $uri_query The key value pairs of query we pass to url.
	 * @return void
	 */
	public function set_upgrade_uri_param( $uri_query = array() ) {

		$this->upgrade_uri_param = $uri_query;

	}

	/**
	 * Getter method for prop: upgrade_uri_param
	 *
	 * @return array The upgrade uri parameters.
	 */
	public function get_upgrade_uri_param(): array {
		return apply_filters(
			'uo_automator_plugins_page_upgrade_uri_param',
			$this->upgrade_uri_param
		);
	}

	/**
	 * Setter method for prop: upgrade_uri
	 *
	 * @param  string $upgrade_uri The upgrade uri.
	 * @return void
	 */
	public function set_upgrade_uri( $upgrade_uri ) {

		$this->upgrade_uri = $upgrade_uri;

	}

	/**
	 * Getter method for prop: upgrade_uri
	 *
	 * @return string The upgrade uri.
	 */
	public function get_upgrade_uri(): string {

		return apply_filters(
			'uo_automator_plugins_page_upgrade_uri',
			$this->upgrade_uri
		);

	}

	/**
	 * Get the complete uri link.
	 *
	 * @return string The complete uri link.
	 */
	public function get_upgrade_link(): string {
		return add_query_arg(
			$this->get_upgrade_uri_param(),
			$this->get_upgrade_uri()
		);
	}

	/**
	 * Setups our link to add to the plugins page via `plugin_action_links_{$path}` action hook.
	 *
	 * @return void
	 */
	private function display_in_plugins_page() {
		// Only display the upgrade link when Automator Pro is not active.
		if ( ! defined( 'AUTOMATOR_PRO_FILE' ) || ! class_exists( '\Uncanny_Automator_Pro\InitializePlugin' ) ) {
			// Setup dependencies.
			$this->set_dependencies( new Utilities() );
			// Display in plugins page.
			add_action(
				'plugin_action_links_' . plugin_basename( $this->utilities::get_plugin_file() ),
				array( $this, 'display' )
			);
		}
	}

}
