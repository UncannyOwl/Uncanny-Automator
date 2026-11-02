<?php
/**
 * The Automator menu's order on a site that installed 8.0 or later.
 *
 * A site that upgraded keeps the menu it knows. A site that started on 8.0
 * gets a menu that leads with the connection:
 *
 * - No account linked (Lite, or Pro with no license key): Setup wizard ·
 *   Dashboard · Connect your AI (the wizard) · AI activity · Connect Apps · Recipes · Add new
 *   recipe · Logs · Settings · Upgrade to Pro.
 * - Account linked (Lite, or Pro with its license active): Dashboard · Connect
 *   your AI (the hosted app; "AI Connections" once an AI is connected) · AI
 *   activity · Connect Apps · Recipes · Add new recipe · Logs · Settings · Upgrade to Pro
 *   (Plans on a Pro site).
 * - A Pro site adds Addons at the bottom. A Lite site has no Addons entry.
 *
 * Entries this class does not name (Pro's Scheduled recipes, anything a third
 * party adds) keep their order between Add new recipe and Logs. Recipe
 * templates and Status are off every menu (Admin_Menu::hide_submenu_pages()),
 * and the recipe taxonomies register without a menu entry. The Setup wizard entry comes and goes with the
 * Feature State policy; this class only places it first when it is there.
 *
 * @package Uncanny_Automator
 * @since   8.0
 */

namespace Uncanny_Automator;

use Uncanny_Automator\App\Application\Mcp\Hosted_App;
use Uncanny_Automator\App\Plan\Services\License\License_Service;

/**
 * Class Admin_Menu_Order
 *
 * @package Uncanny_Automator
 */
class Admin_Menu_Order {

	/**
	 * The Automator menu.
	 *
	 * @var string
	 */
	const PARENT_SLUG = 'edit.php?post_type=uo-recipe';

	/**
	 * The AI activity entry's slug: the AI activity tab of Logs, relative to wp-admin.
	 *
	 * @var string
	 */
	const AI_ACTIVITY_SLUG = 'edit.php?post_type=uo-recipe&page=uncanny-automator-admin-logs&tab=ai-activity';

	/**
	 * The Logs page and the tab the entry opens.
	 *
	 * @var string
	 */
	const LOGS_PAGE = 'uncanny-automator-admin-logs';

	/**
	 * @var string
	 */
	const AI_ACTIVITY_TAB = 'ai-activity';

	/**
	 * Slugs of the entries this class places, in the order the menu reads
	 * from top to bottom. Entries not listed keep their order after Add new
	 * recipe. The AI entry's slug varies by state; it is resolved at run time.
	 *
	 * @var string[]
	 */
	const ORDER = array(
		'uncanny-automator-setup-wizard',
		'uncanny-automator-dashboard',
		'{ai}',
		self::AI_ACTIVITY_SLUG,
		Admin_Menu::CONNECT_APPS_SLUG,
		'edit.php?post_type=uo-recipe',
		'post-new.php?post_type=uo-recipe',
		'{others}',
		'uncanny-automator-admin-logs',
		'uncanny-automator-config',
		'uncanny-automator-pro-upgrade',
		'uncanny-automator-addons',
	);

	/**
	 * Class constructor.
	 */
	public function __construct() {
		$this->register_hooks();
	}

	/**
	 * The AI activity entry registers with the AI entry (priority 20); the
	 * order is applied after Admin_Ai_Menu has moved its entry (999).
	 *
	 * @return void
	 */
	private function register_hooks() {
		add_action( 'admin_menu', array( $this, 'register_ai_activity_item' ), 20 );
		add_action( 'admin_menu', array( $this, 'arrange' ), 1000 );
		add_filter( 'submenu_file', array( $this, 'highlight_ai_activity_item' ) );
	}

	/**
	 * Whether this menu applies: a site that installed 8.0 or later.
	 *
	 * @return bool
	 */
	public function applies() {
		return Admin_Ai_Menu::site_installed_at_8_or_later();
	}

	/**
	 * Whether a free or Pro account is linked to the site.
	 *
	 * @return bool
	 */
	public function is_account_linked() {

		try {
			return '' !== ( new License_Service() )->get_license_key();
		} catch ( \Throwable $error ) {
			unset( $error );
			return false;
		}
	}

	/**
	 * Whether Uncanny Automator Pro is active, which keeps the Addons entry.
	 *
	 * @return bool
	 */
	public function is_pro_active() {
		return is_automator_pro_active();
	}

	/**
	 * Adds the AI activity entry, for users who can open Logs.
	 *
	 * @return void
	 */
	public function register_ai_activity_item() {

		if ( ! $this->applies() ) {
			return;
		}

		add_submenu_page(
			self::PARENT_SLUG,
			esc_attr__( 'AI activity', 'uncanny-automator' ),
			esc_attr__( 'AI activity', 'uncanny-automator' ),
			automator_get_capability(), // phpcs:ignore WordPress.WP.Capabilities.Undetermined -- automator_get_capability() returns the capability the Automator menu requires.
			self::AI_ACTIVITY_SLUG
		);
	}

	/**
	 * Puts the Automator menu in order and drops Addons from a Lite site.
	 *
	 * @return void
	 */
	public function arrange() {

		if ( ! $this->applies() || empty( $GLOBALS['submenu'][ self::PARENT_SLUG ] ) ) {
			return;
		}

		$items = array_values( $GLOBALS['submenu'][ self::PARENT_SLUG ] );

		if ( ! $this->is_pro_active() ) {
			$items = array_values(
				array_filter(
					$items,
					static function ( $item ) {
						return 'uncanny-automator-addons' !== $item[2];
					}
				)
			);
		}

		$GLOBALS['submenu'][ self::PARENT_SLUG ] = $this->ordered( $items );
	}

	/**
	 * The entries in the order ORDER names, unknown entries in their own
	 * order where ORDER puts them.
	 *
	 * @param array<int, array> $items The submenu entries, in registration order.
	 *
	 * @return array<int, array>
	 */
	public function ordered( array $items ) {

		$named    = array();
		$ai_slugs = array( Admin_Ai_Menu::SETTINGS_SLUG, Admin_Ai_Menu::WIZARD_SLUG );
		$hosted   = Hosted_App::base_url();

		foreach ( self::ORDER as $slug ) {
			if ( '{ai}' !== $slug && '{others}' !== $slug ) {
				$named[ $slug ] = true;
			}
		}

		$is_ai = static function ( $item ) use ( $ai_slugs, $hosted ) {
			return in_array( $item[2], $ai_slugs, true ) || 0 === strpos( $item[2], $hosted );
		};

		$others = array();
		foreach ( $items as $item ) {
			if ( ! isset( $named[ $item[2] ] ) && ! $is_ai( $item ) ) {
				$others[] = $item;
			}
		}

		$ordered = array();
		foreach ( self::ORDER as $slug ) {
			if ( '{others}' === $slug ) {
				foreach ( $others as $item ) {
					$ordered[] = $item;
				}
				continue;
			}

			foreach ( $items as $item ) {
				if ( '{ai}' === $slug ? $is_ai( $item ) : $slug === $item[2] ) {
					$ordered[] = $item;
				}
			}
		}

		return $ordered;
	}

	/**
	 * Highlights AI activity instead of Logs while that tab is open.
	 *
	 * @param string|null $submenu_file The submenu item WordPress resolved.
	 *
	 * @return string|null
	 */
	public function highlight_ai_activity_item( $submenu_file ) {
		global $plugin_page;

		if ( ! $this->applies() || self::LOGS_PAGE !== $plugin_page || self::AI_ACTIVITY_TAB !== $this->get_requested_tab() ) {
			return $submenu_file;
		}

		return self::AI_ACTIVITY_SLUG;
	}

	/**
	 * The Logs tab named in the request. Overridable for tests, where
	 * filter_input() cannot see a query string.
	 *
	 * @return string
	 */
	protected function get_requested_tab() {
		return (string) automator_filter_input( 'tab' );
	}
}
