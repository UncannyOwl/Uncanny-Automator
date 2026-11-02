<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Restful\Addons;

use Uncanny_Automator\App\Plan\Addons\Value_Objects\Addon;
use Uncanny_Automator\App\Plan\Addons\Value_Objects\Addon_Install_State;

/**
 * Shapes the addon card the AddOns page strict-parses.
 *
 * Owns the frozen ModelAddonSchema shape: the numeric-string id, the
 * per-addon description overrides, the three detail rows, and the CTA set for
 * each install scenario. There is deliberately no `is_active` key — the
 * frontend derives that from the CTAs.
 */
final class Addon_Card_Response {

	/**
	 * Who publishes these addons.
	 *
	 * @var string
	 */
	const DEVELOPER_NAME = 'Uncanny Automator';

	/**
	 * Icon for a feed row that carries none. `ModelAddonSchema.icon` is a
	 * required IconCodeSchema, and `''` fails it — which fails the whole list
	 * response, not just the card.
	 *
	 * @var string
	 */
	const FALLBACK_ICON = 'puzzle-piece';

	/**
	 * Build one addon card.
	 *
	 * @param Addon               $addon      The addon.
	 * @param Addon_Install_State $state      Its current install state.
	 * @param bool                $has_access Whether the license entitles it.
	 *
	 * @return array<string,mixed>
	 */
	public static function card( Addon $addon, Addon_Install_State $state, bool $has_access ): array {
		return array(
			'id'           => (string) $addon->id(),
			'icon'         => '' === $addon->icon() ? self::FALLBACK_ICON : $addon->icon(),
			'name'         => $addon->name(),
			'description'  => self::description( $addon ),
			'is_official'  => true,
			'is_installed' => $state->is_installed(),
			'developer'    => array(
				'name' => esc_html( self::DEVELOPER_NAME ),
				'url'  => AUTOMATOR_STORE_URL,
			),
			'details'      => self::details( $addon ),
			'cta'          => self::ctas( $addon, $state, $has_access ),
		);
	}

	/**
	 * The card blurb.
	 *
	 * The five shipped addons carry a longer written description than the
	 * feed's; anything else falls back to the feed.
	 *
	 * @param Addon $addon The addon.
	 *
	 * @return string
	 */
	private static function description( Addon $addon ): string {

		switch ( $addon->name() ) {
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
					'Manage mailing lists and email campaigns effortlessly: set up newsletters, let users manage subscriptions, and send targeted campaigns with ease.',
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
				$description = esc_html( $addon->short_description() );
				break;
		}

		// The card binds this as text, so the entities the escaping above
		// introduces would be visible. Apostrophes, quotes and ampersands are
		// what our own copy contains; angle brackets stay escaped.
		return str_replace(
			array( '&quot;', '&#039;', '&amp;' ),
			array( '"', "'", '&' ),
			$description
		);
	}

	/**
	 * The three detail rows.
	 *
	 * Every value must be a string: the frontend requires it, and `wp_date()`
	 * returns false for an unparseable date.
	 *
	 * @param Addon $addon The addon.
	 *
	 * @return array<int,array<string,string>>
	 */
	private static function details( Addon $addon ): array {
		return array(
			array(
				'label' => esc_html_x( 'Version', 'Addons', 'uncanny-automator' ),
				'value' => self::version_detail( $addon ),
			),
			array(
				'label' => esc_html_x( 'Requires WordPress', 'Addons', 'uncanny-automator' ),
				'value' => sprintf(
					// translators: %s is a WordPress version number.
					esc_html_x( '%s or higher', 'Addons', 'uncanny-automator' ),
					$addon->requires_wp()
				),
			),
			array(
				'label' => esc_html_x( 'Last updated', 'Addons', 'uncanny-automator' ),
				'value' => self::last_updated_detail( $addon ),
			),
		);
	}

	/**
	 * A feed-supplied URL vetted for output, or '' when it cannot be used.
	 *
	 * Both callers hand their result to the frontend — one inside a markdown
	 * detail value, one as a CTA href — so the scheme is checked, not just the
	 * syntax. All three steps carry weight: FILTER_VALIDATE_URL accepts
	 * `javascript://host/%0a…`, esc_url_raw() promotes a scheme-less value to
	 * http://, and requiring the value to name http(s) itself is what rejects
	 * mailto:, tel: and data:.
	 *
	 * @param string $url Raw feed value.
	 *
	 * @return string
	 */
	private static function vetted_url( string $url ): string {

		if ( ! in_array( wp_parse_url( $url, PHP_URL_SCHEME ), array( 'http', 'https' ), true ) ) {
			return '';
		}

		$clean = esc_url_raw( $url, array( 'http', 'https' ) );

		return false === filter_var( $clean, FILTER_VALIDATE_URL ) ? '' : $clean;
	}

	/**
	 * The version row, with its changelog link when there is one.
	 *
	 * @param Addon $addon The addon.
	 *
	 * @return string
	 */
	private static function version_detail( Addon $addon ): string {

		$changelog = self::vetted_url( $addon->changelog_url() );

		if ( '' === $changelog ) {
			return $addon->version();
		}

		return sprintf(
			// translators: 1: Plugin version number 2: Changelog URL.
			esc_html_x( '%1$s [Changelog](%2$s)', 'Addons', 'uncanny-automator' ),
			$addon->version(),
			$changelog
		);
	}

	/**
	 * The last-updated row, formatted in site time.
	 *
	 * @param Addon $addon The addon.
	 *
	 * @return string
	 */
	private static function last_updated_detail( Addon $addon ): string {

		$timestamp = '' === $addon->last_updated() ? false : strtotime( $addon->last_updated() );
		if ( false === $timestamp ) {
			return '';
		}

		$formatted = wp_date( 'F j, Y', $timestamp );

		return is_string( $formatted ) ? $formatted : '';
	}

	/**
	 * The CTAs for the card's scenario.
	 *
	 * @param Addon               $addon      The addon.
	 * @param Addon_Install_State $state      Its current install state.
	 * @param bool                $has_access Whether the license entitles it.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private static function ctas( Addon $addon, Addon_Install_State $state, bool $has_access ): array {

		// Every card CTA url is z.url() frontend-side, so a row with no usable
		// product page links to the store rather than failing the list.
		$product_page = self::vetted_url( $addon->product_page() );
		$product_page = '' === $product_page ? AUTOMATOR_STORE_URL : $product_page;

		$learn_more = Addon_Ctas::learn_more( $product_page );
		$settings   = Addon_Ctas::settings( $addon->settings_tab() );
		$can_act    = Addon_Ctas::can_user_perform_plugin_actions();

		switch ( $state->card_scenario( $has_access ) ) {
			case Addon_Install_State::SCENARIO_NOT_INSTALLED:
				return array(
					$can_act ? Addon_Ctas::install() : Addon_Ctas::download(),
					$learn_more,
				);

			case Addon_Install_State::SCENARIO_NOT_ACTIVE:
				return is_multisite()
					? array( $learn_more )
					: array( Addon_Ctas::activate(), $learn_more );

			case Addon_Install_State::SCENARIO_UPDATE_AVAILABLE:
				return array(
					$can_act ? Addon_Ctas::update() : Addon_Ctas::download( 'update' ),
					empty( $settings ) ? $learn_more : $settings,
				);

			case Addon_Install_State::SCENARIO_ACTIVE:
				return empty( $settings )
					? array( $learn_more )
					: array( $settings, $learn_more );

			default:
				return array( $learn_more );
		}
	}
}
