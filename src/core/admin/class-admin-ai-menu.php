<?php
/**
 * The AI entry in the Automator menu.
 *
 * One slot, two states. While no AI app is connected it reads "Connect your
 * AI" and opens the hosted app at mcp.automatorplugin.com, the same
 * destination as the "Connect an AI app" button on the MCP settings tab.
 * Once an AI app is connected it reads "AI Connections" and opens that tab, whose page is titled Connections.
 *
 * Where the slot sits depends on when the site installed Automator. A site
 * that installed 8.0 or later sees it directly below Dashboard. A site that
 * upgraded sees it below Logs, so the menu it already knows changes as
 * little as possible.
 *
 * On a site that installed 8.0 or later and has no account linked yet, the
 * "Connect your AI" state opens the setup wizard instead, whose first screen
 * creates the account the connection needs. Admin_Menu_Order arranges the
 * rest of that site's menu.
 *
 * @package Uncanny_Automator
 * @since   8.0
 */

namespace Uncanny_Automator;

use Uncanny_Automator\App\Application\Mcp\Hosted_App;
use Uncanny_Automator\App\Plan\Services\License\License_Service;

/**
 * Class Admin_Ai_Menu
 *
 * @package Uncanny_Automator
 */
class Admin_Ai_Menu {

	/**
	 * The Automator menu the entry belongs to.
	 *
	 * @var string
	 */
	const PARENT_SLUG = 'edit.php?post_type=uo-recipe';

	/**
	 * The "AI Connections" entry's slug: the MCP settings tab, relative to wp-admin.
	 *
	 * @var string
	 */
	const SETTINGS_SLUG = 'edit.php?post_type=uo-recipe&page=uncanny-automator-config&tab=model-context-protocol';

	/**
	 * The "Connect your AI" entry's slug while no account is linked on a site
	 * that installed 8.0 or later: the setup wizard, relative to wp-admin. It
	 * differs from the wizard's own entry so WordPress treats it as a link.
	 *
	 * @var string
	 */
	const WIZARD_SLUG = 'edit.php?post_type=uo-recipe&page=uncanny-automator-setup-wizard&automator_ref=connect-your-ai';

	/**
	 * The first plugin version whose fresh installs see the entry below Dashboard.
	 *
	 * @var string
	 */
	const DASHBOARD_PLACEMENT_SINCE = '8.0.0';

	/**
	 * The entry the slot follows on a fresh install.
	 *
	 * @var string
	 */
	const ANCHOR_NEW_INSTALL = 'uncanny-automator-dashboard';

	/**
	 * The entry the slot follows on an upgraded site.
	 *
	 * @var string
	 */
	const ANCHOR_EXISTING_INSTALL = 'uncanny-automator-admin-logs';

	/**
	 * CSS class on the external-link glyph, also used by the admin
	 * stylesheet and the new-tab script to find the connect link.
	 *
	 * @var string
	 */
	const ICON_CLASS = 'automator-ai-menu__icon';

	/**
	 * The slug registered on this request, or an empty string when the
	 * entry was not added.
	 *
	 * @var string
	 */
	private $registered_slug = '';

	/**
	 * Class constructor.
	 */
	public function __construct() {
		$this->register_hooks();
	}

	/**
	 * Hooks the entry into the Automator menu.
	 *
	 * The item is added after the core entries and moved into place after
	 * every plugin has registered its own, so its position does not depend
	 * on registration order.
	 *
	 * @return void
	 */
	private function register_hooks() {
		add_action( 'admin_menu', array( $this, 'register_menu_item' ), 20 );
		add_action( 'admin_menu', array( $this, 'reposition' ), 999 );
		add_filter( 'submenu_file', array( $this, 'highlight_settings_item' ) );
		add_action( 'admin_head', array( $this, 'print_styles' ) );
		add_action( 'admin_footer', array( $this, 'print_script' ) );
	}

	/**
	 * Whether the entry should be in the menu on this request.
	 *
	 * @return bool
	 */
	public function should_display() {
		return Admin_Ai_Settings::current_user_can_manage_ai();
	}

	/**
	 * Whether an AI app is connected, which decides the entry's state.
	 *
	 * @return bool
	 */
	public function is_connected() {
		return Ai_Connection_State::is_connected();
	}

	/**
	 * Where the "Connect your AI" state goes: the hosted app for this site, as
	 * on the MCP settings tab.
	 *
	 * @return string
	 */
	public function get_connect_url() {
		return Hosted_App::connect_url();
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
	 * Whether the "Connect your AI" state opens the setup wizard: a site that
	 * installed 8.0 or later with no account linked yet. An upgraded site keeps
	 * the hosted link whatever its account state.
	 *
	 * @return bool
	 */
	public function connect_opens_wizard() {
		return $this->is_new_install() && ! $this->is_account_linked();
	}

	/**
	 * The slug of the entry in its current state.
	 *
	 * @return string
	 */
	public function get_slug() {

		if ( $this->is_connected() ) {
			return self::SETTINGS_SLUG;
		}

		return $this->connect_opens_wizard() ? self::WIZARD_SLUG : $this->get_connect_url();
	}

	/**
	 * Adds the entry to the Automator menu.
	 *
	 * @return void
	 */
	public function register_menu_item() {

		if ( ! $this->should_display() ) {
			return;
		}

		$connected = $this->is_connected();
		$slug      = $this->get_slug();

		add_submenu_page(
			self::PARENT_SLUG,
			$connected ? esc_attr__( 'AI Connections', 'uncanny-automator' ) : esc_attr__( 'Connect your AI', 'uncanny-automator' ),
			$connected ? esc_html__( 'AI Connections', 'uncanny-automator' ) : ( self::WIZARD_SLUG === $slug ? esc_html__( 'Connect your AI', 'uncanny-automator' ) : $this->get_connect_menu_title() ),
			automator_get_admin_capability(), // phpcs:ignore WordPress.WP.Capabilities.Undetermined
			$slug
		);

		$this->registered_slug = $slug;
	}

	/**
	 * The "Connect your AI" label with the external-link glyph on its right.
	 *
	 * The menu renders on every wp-admin screen, where the Automator bundle
	 * and its icon font are not loaded, so the glyph is inline SVG. The
	 * glyph classes stay on the wrapper for styling parity with uas-icon.
	 *
	 * @return string
	 */
	public function get_connect_menu_title() {

		return esc_html__( 'Connect your AI', 'uncanny-automator' )
			. ' <span class="' . esc_attr( self::ICON_CLASS ) . ' icon-glyph-solid icon-glyph-classic icon-glyph-arrow-up-right-from-square" aria-hidden="true">'
			. '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" focusable="false"><path d="M320 0c-17.7 0-32 14.3-32 32s14.3 32 32 32h82.7L201.4 265.4c-12.5 12.5-12.5 32.8 0 45.3s32.8 12.5 45.3 0L448 109.3V192c0 17.7 14.3 32 32 32s32-14.3 32-32V32c0-17.7-14.3-32-32-32H320zM80 32C35.8 32 0 67.8 0 112V432c0 44.2 35.8 80 80 80H400c44.2 0 80-35.8 80-80V320c0-17.7-14.3-32-32-32s-32 14.3-32 32V432c0 8.8-7.2 16-16 16H80c-8.8 0-16-7.2-16-16V112c0-8.8 7.2-16 16-16H192c17.7 0 32-14.3 32-32s-14.3-32-32-32H80z"/></svg>'
			. '</span>';
	}

	/**
	 * Whether this site installed Automator at a version that places the
	 * entry below Dashboard. Sites that upgraded have no installed version.
	 *
	 * @return bool
	 */
	public function is_new_install() {
		return self::site_installed_at_8_or_later();
	}

	/**
	 * Whether this site installed Automator at 8.0 or later, read from the
	 * version recorded on install. Sites that upgraded have no value.
	 *
	 * @return bool
	 */
	public static function site_installed_at_8_or_later() {

		$installed = (string) automator_get_option( Automator_Load::OPTION_INSTALLED_VERSION, '' );

		return '' !== $installed && version_compare( $installed, self::DASHBOARD_PLACEMENT_SINCE, '>=' );
	}

	/**
	 * The entry the slot follows.
	 *
	 * @return string
	 */
	public function get_anchor_slug() {
		return $this->is_new_install() ? self::ANCHOR_NEW_INSTALL : self::ANCHOR_EXISTING_INSTALL;
	}

	/**
	 * Moves the entry directly after its anchor.
	 *
	 * Runs after every plugin has registered its Automator submenu items. If
	 * the anchor is missing, the entry falls to the end of the menu.
	 *
	 * @return void
	 */
	public function reposition() {

		if ( '' === $this->registered_slug || empty( $GLOBALS['submenu'][ self::PARENT_SLUG ] ) ) {
			return;
		}

		$items = array_values( $GLOBALS['submenu'][ self::PARENT_SLUG ] );
		$index = array_search( $this->registered_slug, array_column( $items, 2 ), true );

		if ( false === $index ) {
			return;
		}

		$entry = $items[ $index ];
		unset( $items[ $index ] );
		$items = array_values( $items );

		$anchor = array_search( $this->get_anchor_slug(), array_column( $items, 2 ), true );
		$at     = false === $anchor ? count( $items ) : $anchor + 1;

		array_splice( $items, $at, 0, array( $entry ) );

		$GLOBALS['submenu'][ self::PARENT_SLUG ] = $items;
	}

	/**
	 * Highlights "AI Connections" instead of Settings while the MCP settings
	 * tab is open.
	 *
	 * @param string|null $submenu_file The submenu item WordPress resolved.
	 *
	 * @return string|null
	 */
	public function highlight_settings_item( $submenu_file ) {
		global $plugin_page;

		if ( self::SETTINGS_SLUG !== $this->registered_slug ) {
			return $submenu_file;
		}

		if ( 'uncanny-automator-config' !== $plugin_page || 'model-context-protocol' !== $this->get_requested_tab() ) {
			return $submenu_file;
		}

		return self::SETTINGS_SLUG;
	}

	/**
	 * The settings tab named in the request. Overridable for tests, where
	 * filter_input() cannot see a query string.
	 *
	 * @return string
	 */
	protected function get_requested_tab() {
		return (string) automator_filter_input( 'tab' );
	}

	/**
	 * Whether the connect link, with its glyph and new-tab behaviour, is in
	 * the menu on this request.
	 *
	 * @return bool
	 */
	private function connect_link_registered() {
		return '' !== $this->registered_slug && self::SETTINGS_SLUG !== $this->registered_slug && self::WIZARD_SLUG !== $this->registered_slug;
	}

	/**
	 * Right-aligns the glyph. Inline because the menu renders on screens
	 * where no Automator stylesheet is loaded.
	 *
	 * @return void
	 */
	public function print_styles() {

		if ( ! $this->connect_link_registered() ) {
			return;
		}

		?>
		<style>
			#adminmenu .<?php echo esc_attr( self::ICON_CLASS ); ?> {
				float: right;
				width: 12px;
				height: 12px;
				margin-top: 2px;
				opacity: 0.75;
			}
			#adminmenu .<?php echo esc_attr( self::ICON_CLASS ); ?> svg {
				display: block;
				width: 100%;
				height: 100%;
				fill: currentColor;
			}
		</style>
		<?php
	}

	/**
	 * Opens the connect link in a new tab. WordPress menus cannot carry a target.
	 *
	 * @return void
	 */
	public function print_script() {

		if ( ! $this->connect_link_registered() ) {
			return;
		}

		?>
		<script>
			( function () {
				var icons = document.querySelectorAll( '#adminmenu .<?php echo esc_js( self::ICON_CLASS ); ?>' );

				for ( var i = 0; i < icons.length; i++ ) {
					var link = icons[ i ].closest ? icons[ i ].closest( 'a' ) : null;

					if ( link ) {
						link.target = '_blank';
						link.rel = 'noopener noreferrer';
					}
				}
			}() );
		</script>
		<?php
	}
}
