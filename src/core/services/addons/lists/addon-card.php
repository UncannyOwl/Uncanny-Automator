<?php

namespace Uncanny_Automator\Services\Addons\Lists;

use Uncanny_Automator\Services\Addons\Data\Calls_To_Action;
use Uncanny_Automator\Services\Plugin\Info;

/**
 * Class Add_On_Card
 *
 * - Generates the add-on card for the addons list.
 *
 * @package Uncanny_Automator\Services\Addons\Lists
 */
class Add_On_Card {

	/**
	 * The addon data from the feed.
	 *
	 * @var array
	 */
	private $addon;

	/**
	 * License has access to the addon.
	 *
	 * @var bool
	 */
	private $license_has_access;

	/**
	 * Is the addon installed.
	 *
	 * @var bool
	 */
	private $is_installed;

	/**
	 * Is the addon active.
	 *
	 * @var bool
	 */
	private $is_active;

	/**
	 * Is there an update available for the addon.
	 *
	 * @var bool
	 */
	private $is_update_available;

	/**
	 * The scenario ID.
	 *
	 * @var string
	 */
	private $scenario_id;

	/**
	 * The addon card.
	 *
	 * @var array
	 */
	private $card;

	/**
	 * Constructor.
	 *
	 * @param array $addon The addon.
	 * @param bool  $license_has_access Whether the license has access.
	 */
	public function __construct( $addon, $license_has_access ) {
		$this->addon              = $addon;
		$this->license_has_access = $license_has_access;
		$this->set_card();
	}

	/**
	 * Get the addon card.
	 *
	 * @return array
	 */
	public function get_card() {
		return $this->card;
	}

	/**
	 * Set the addon card.
	 *
	 * @return void
	 */
	private function set_card() {
		// Set plugin status variables.
		$this->set_is_installed();
		$this->set_is_active();
		$this->set_is_update_available();

		// Set the scenario ID.
		$this->set_scenario_id();

		// Set the addon card.
		$this->card = array(
			'id'           => $this->addon['id'],
			'icon'         => $this->addon['icon'],
			'name'         => $this->addon['name'],
			'description'  => $this->get_description(),
			'is_official'  => true,
			'is_installed' => $this->is_installed,
			'is_active'    => $this->is_active,
			'developer'    => array(
				'name' => 'Uncanny Automator',
				'url'  => AUTOMATOR_STORE_URL,
			),
			'details'      => $this->get_details(),
			'cta'          => $this->get_ctas(),
		);
	}

	/**
	 * Set the scenario ID.
	 *
	 * @return void
	 */
	private function set_scenario_id() {

		// No access.
		if ( ! $this->license_has_access ) {
			$this->scenario_id = 'no-access';
			return;
		}

		// Check if the addon is installed.
		if ( ! $this->is_installed ) {
			$this->scenario_id = 'has-access-addon-not-installed';
			return;
		}

		// Check if the addon is active.
		if ( ! $this->is_active ) {
			$this->scenario_id = 'has-access-addon-not-active';
			return;
		}

		// Check if there is an update available.
		if ( $this->is_update_available ) {
			$this->scenario_id = 'has-access-addon-update-available';
			return;
		}

		// If all checks pass, the addon is active and up to date.
		$this->scenario_id = 'has-access-addon-active';
	}

	/**
	 * Get the addon description.
	 *
	 * @return string
	 */
	private function get_description() {
		// Get the description from the addon.
		$description = $this->addon['short_description'];

		// Customize the description for certain addons.
		switch ( $this->addon['name'] ) {
			case 'Custom User Fields':
				$description = esc_html_x(
					'Add custom fields (e.g. "Job Title") to profiles and integrate them into Automator recipes for personalized workflows.',
					'Addons',
					'uncanny-automator'
				);
				break;
			case 'Restrict Content':
				$description = esc_html_x(
					'Restrict access to posts, pages, blocks, and shortcodes by role, access level, or profile fields to create membership sites, paywalls, and more.',
					'Addons',
					'uncanny-automator'
				);
				break;
			case 'Elite Integrations':
				$description = esc_html_x(
					'Access to premium integrations like Salesforce, Quickbooks, and more.',
					'Addons',
					'uncanny-automator'
				);
				break;
			case 'User Lists':
				$description = esc_html_x(
					'Manage mailing lists and email campaigns effortlessly—set up newsletters, let users manage subscriptions, and send targeted campaigns with ease.',
					'Addons',
					'uncanny-automator'
				);
				break;
			case 'Dynamic Content':
				$description = esc_html_x(
					'Define user-specific or global dynamic content blocks and shortcodes that update in real-time for use with Uncanny Automator.',
					'Addons',
					'uncanny-automator'
				);
				break;
			default:
				$description = esc_html( $description );
				break;
		}

		// Temp fix for esc_html and quotes.
		return str_replace(
			array( '&quot;', '&#039;', '&amp;' ),
			array( '"', "'", '&' ),
			$description
		);
	}

	/**
	 * Get the addon details.
	 *
	 * @return array
	 */
	private function get_details() {
		$details = array();
		$keys    = array(
			'stable_tag' => esc_html_x( 'Version', 'Addons', 'uncanny-automator' ),
			'requires'   => esc_html_x( 'Requires WordPress', 'Addons', 'uncanny-automator' ),
			'updated'    => esc_html_x( 'Last updated', 'Addons', 'uncanny-automator' ),
		);

		foreach ( $keys as $key => $label ) {
			$value = isset( $this->addon[ $key ] ) ? $this->addon[ $key ] : '';
			switch ( $key ) {
				case 'stable_tag':
					if ( empty( $this->addon['changelog_url'] ) ) {
						break;
					}
					$value = sprintf(
						// translators: 1: Plugin version number 2: Changelog URL
						esc_html_x( '%1$s [Changelog](%2$s)', 'Addons', 'uncanny-automator' ),
						$value,
						$this->addon['changelog_url']
					);
					break;
				case 'requires':
					$value = sprintf(
						// translators: 1: WP or PHP version number
						esc_html_x( '%s or higher', 'Addons', 'uncanny-automator' ),
						$value
					);
					break;
				case 'updated':
					$value = wp_date(
						'F j, Y',
						strtotime( $this->addon['last_updated'] )
					);
					break;
			}

			$details[] = array(
				'label' => $label,
				'value' => $value,
			);
		}

		return $details;
	}

	/**
	 * Get the addon CTAs.
	 *
	 * @return array
	 */
	private function get_ctas() {

		$has_settings = ! empty( $this->addon['tab'] );
		$ctas         = array();

		switch ( $this->scenario_id ) {
			// Unlicensed.
			case 'no-access':
				// Learn more.
				$ctas[] = Calls_To_Action::get_learn_more( $this->addon['product_page'] );
				break;
			// Licensed but not installed.
			case 'has-access-addon-not-installed':
				// Install.
				$ctas[] = Calls_To_Action::get_install_addon( $this->addon['id'] );
				// Learn more.
				$ctas[] = Calls_To_Action::get_learn_more( $this->addon['product_page'] );
				break;
			// Licensed, installed, but not active.
			case 'has-access-addon-not-active':
				// Only show activate on single sites.
				if ( ! is_multisite() ) {
					// Activate.
					$ctas[] = Calls_To_Action::get_activate_addon( $this->addon['id'] );
				}
				// Learn more.
				$ctas[] = Calls_To_Action::get_learn_more( $this->addon['product_page'] );
				break;
			// Licensed, installed, active, and update available.
			case 'has-access-addon-update-available':
				// Update.
				$ctas[] = Calls_To_Action::get_update_addon( $this->addon['id'] );
				// Settings or Learn more.
				$ctas[] = $has_settings
					? Calls_To_Action::get_addon_settings_page( $this->addon['tab'] )
					: Calls_To_Action::get_learn_more( $this->addon['product_page'] );
				break;
			// Licensed, installed, active and up to date.
			case 'has-access-addon-active':
				// Settings.
				if ( $has_settings ) {
					$ctas[] = Calls_To_Action::get_addon_settings_page( $this->addon['tab'] );
				}
				// Learn more.
				$ctas[] = Calls_To_Action::get_learn_more( $this->addon['product_page'] );
				break;
		}

		return $ctas;
	}

	/**
	 * Check if the addon is installed.
	 *
	 * @return void
	 */
	private function set_is_installed() {
		$this->is_installed = Info::is_plugin_installed( $this->addon['plugin_file'] );
	}

	/**
	 * Check if the addon is active.
	 *
	 * @return void
	 */
	private function set_is_active() {
		$this->is_active = Info::is_plugin_active( $this->addon['plugin_file'] );
	}

	/**
	 * Check if there is an update available.
	 *
	 * @return void
	 */
	private function set_is_update_available() {
		if ( ! $this->is_active ) {
			$this->is_update_available = false;
			return;
		}

		$this->is_update_available = version_compare(
			$this->addon['stable_tag'],
			constant( $this->addon['key'] . '_PLUGIN_VERSION' ),
			'>'
		);
	}
}
