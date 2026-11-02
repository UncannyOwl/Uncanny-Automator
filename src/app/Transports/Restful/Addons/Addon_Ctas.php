<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Restful\Addons;

use Uncanny_Automator\Addons;
use Uncanny_Automator\App\Plan\Services\Plugin\Plugin_Service;

/**
 * Every call-to-action the addons surface puts on the wire.
 *
 * The frontend models three CTA families with different strict shapes, so
 * they are all built here — one place to promote from if the surfaces ever
 * converge on a shared vocabulary.
 *
 * The link family (summary CTAs, page notices, user-intervention dialogs)
 * carries `label`, `icon`, `url` and never a `type` or `action`: those
 * frontend models have no `action` field, and `type` accepts ButtonColor
 * members only.
 *
 * No builder emits `arrow-up-right-from-square`. `uas-button` renders that
 * glyph itself for any external href, so supplying it produces two.
 */
final class Addon_Ctas {

	/**
	 * The only button colour ModelAddonCTASchema accepts. Card CTAs are
	 * never primary.
	 *
	 * @var string
	 */
	const CARD_BUTTON_COLOR = 'secondary';

	/**
	 * The store's pricing page, UTM-tagged.
	 *
	 * @return string
	 */
	private static function pricing_url(): string {
		return automator_utm_parameters(
			AUTOMATOR_STORE_URL . 'pricing/',
			'upgrade_to_pro',
			'upgrade_to_pro_button'
		);
	}

	/**
	 * The license settings screen.
	 *
	 * @return string
	 */
	private static function license_settings_url(): string {
		return add_query_arg(
			array(
				'post_type' => AUTOMATOR_POST_TYPE_RECIPE,
				'page'      => 'uncanny-automator-config',
				'tab'       => 'general',
				'general'   => 'license',
			),
			admin_url( 'edit.php' )
		);
	}

	/**
	 * Buy Automator Pro.
	 *
	 * @return array<string,string>
	 */
	public static function get_pro(): array {
		return array(
			'label' => esc_html_x( 'Get Automator Pro', 'Addons', 'uncanny-automator' ),
			'icon'  => 'gem',
			'url'   => self::pricing_url(),
		);
	}

	/**
	 * Move to a higher Pro plan.
	 *
	 * @return array<string,string>
	 */
	public static function upgrade_plan(): array {
		return array(
			'label' => esc_html_x( 'Upgrade Pro plan', 'Addons', 'uncanny-automator' ),
			'icon'  => 'gem',
			'url'   => self::pricing_url(),
		);
	}

	/**
	 * Activate the installed Pro plugin.
	 *
	 * The frontend's summary and notice CTA models are URL-only, so this
	 * points at the plugins screen filtered to Pro instead of firing the
	 * activation itself.
	 *
	 * @return array<string,string>
	 */
	public static function activate_pro(): array {
		return array(
			'label' => esc_html_x( 'Activate Automator Pro', 'Addons', 'uncanny-automator' ),
			'icon'  => 'plug-circle-bolt',
			'url'   => ( new Plugin_Service() )->get_plugin_search_url( 'Uncanny Automator Pro' ),
		);
	}

	/**
	 * Repair the license.
	 *
	 * @return array<string,string>
	 */
	public static function fix_license(): array {
		return array(
			'label' => esc_html_x( 'Fix your license', 'Addons', 'uncanny-automator' ),
			'icon'  => 'wrench',
			'url'   => self::license_settings_url(),
		);
	}

	/**
	 * Open the store account downloads page.
	 *
	 * @return array<string,string>
	 */
	public static function my_account(): array {
		return array(
			'label' => esc_html_x( 'My account', 'Addons', 'uncanny-automator' ),
			'icon'  => 'circle-user',
			'url'   => AUTOMATOR_STORE_URL . 'my-account/downloads/',
		);
	}

	/**
	 * Reload the AddOns page.
	 *
	 * @return array<string,string>
	 */
	public static function reload_addons(): array {
		return array(
			'label' => esc_html_x( 'Reload addons', 'Addons', 'uncanny-automator' ),
			'icon'  => 'rotate',
			'url'   => (string) Addons::get_addon_admin_url(),
		);
	}

	/**
	 * Download a package the site could not install itself.
	 *
	 * @param string $url Signed download URL.
	 *
	 * @return array<string,string>
	 */
	public static function direct_download( string $url ): array {
		return array(
			'label' => esc_html_x( 'Download', 'Addons', 'uncanny-automator' ),
			'icon'  => 'download',
			'url'   => $url,
		);
	}

	/**
	 * Activate an addon that is already on disk, from the plugins screen.
	 *
	 * Carries no icon: the intervention dialog it appears in has always
	 * rendered this button as label-and-link only.
	 *
	 * @param string $url Plugins-screen URL, filtered to the addon.
	 *
	 * @return array<string,string>
	 */
	public static function activate_plugin( string $url ): array {
		return array(
			'label' => esc_html_x( 'Activate', 'Addons', 'uncanny-automator' ),
			'url'   => $url,
		);
	}

	/**
	 * Read more about an addon on the store.
	 *
	 * @param string $url Product page URL.
	 *
	 * @return array<string,string>
	 */
	public static function learn_more( string $url ): array {
		return array(
			'label' => esc_html_x( 'Learn more', 'Addons', 'uncanny-automator' ),
			'type'  => self::CARD_BUTTON_COLOR,
			'url'   => esc_url_raw( $url ),
		);
	}

	/**
	 * Install the addon through the plugin-manager route.
	 *
	 * @return array<string,string>
	 */
	public static function install(): array {
		return self::action_cta(
			esc_html_x( 'Install addon', 'Addons', 'uncanny-automator' ),
			'install',
			'download'
		);
	}

	/**
	 * Activate the installed addon.
	 *
	 * @return array<string,string>
	 */
	public static function activate(): array {
		return self::action_cta(
			esc_html_x( 'Activate', 'Addons', 'uncanny-automator' ),
			'activate',
			'bolt'
		);
	}

	/**
	 * Update the installed addon.
	 *
	 * @return array<string,string>
	 */
	public static function update(): array {
		return self::action_cta(
			esc_html_x( 'Update addon', 'Addons', 'uncanny-automator' ),
			'update',
			'repeat'
		);
	}

	/**
	 * Hand the package to a user who cannot install plugins here.
	 *
	 * @param string $verb Whether this replaces an install or an update.
	 *
	 * @return array<string,string>
	 */
	public static function download( string $verb = 'install' ): array {
		return self::action_cta(
			'update' === $verb
				? esc_html_x( 'Download update', 'Addons', 'uncanny-automator' )
				: esc_html_x( 'Download', 'Addons', 'uncanny-automator' ),
			'download',
			'download'
		);
	}

	/**
	 * Open the addon's settings tab.
	 *
	 * @param string $tab Settings tab slug.
	 *
	 * @return array<string,mixed>
	 */
	public static function settings( string $tab ): array {

		if ( '' === $tab ) {
			return array();
		}

		return array(
			'label'    => esc_html_x( 'Settings', 'Addons', 'uncanny-automator' ),
			'type'     => self::CARD_BUTTON_COLOR,
			'url'      => (string) Addons::get_addon_settings_url( $tab ),
			'icon'     => 'gear',
			'disabled' => false,
		);
	}

	/**
	 * Whether this user may install, activate or update plugins here.
	 *
	 * @return bool
	 */
	public static function can_user_perform_plugin_actions(): bool {
		return ! is_multisite() && current_user_can( 'install_plugins' );
	}

	/**
	 * A card CTA that invokes a plugin-manager action.
	 *
	 * The frontend sends the addon id from the card itself, so an action CTA
	 * carries no `addon` key and no URL.
	 *
	 * @param string $label  Button label.
	 * @param string $action Plugin-manager action.
	 * @param string $icon   Icon code.
	 *
	 * @return array<string,string>
	 */
	private static function action_cta( string $label, string $action, string $icon ): array {
		return array(
			'label'  => $label,
			'type'   => self::CARD_BUTTON_COLOR,
			'action' => $action,
			'icon'   => $icon,
		);
	}
}
