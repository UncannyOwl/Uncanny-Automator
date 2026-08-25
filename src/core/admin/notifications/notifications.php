<?php
namespace Uncanny_Automator;

use function Uncanny_Automator\App\Infrastructure\automator_license_manager;

/**
 * Notifications.
 *
 * @since 3.9.1.2
 */

class Automator_Notifications {

	/**
	 * Source of notifications content.
	 *
	 * @since 3.9.1.2
	 *
	 * @var string
	 */
	public $source_url = 'https://autonotifs-cdn.automatorplugin.com/wp-content/notifications.json';

	/**
	 * Option value.
	 *
	 * @since 3.9.1.2
	 *
	 * @var bool|array
	 */
	public $option = false;

	/**
	 * The name of the option used to store the data.
	 *
	 * @var string
	 */
	public $option_name = 'automator_notifications';

	/**
	 *
	 */
	public function __construct() {

		if ( defined( 'AUTOMATOR_NOTIFICATIONS_SOURCE_URL' ) ) {

			$this->source_url = AUTOMATOR_NOTIFICATIONS_SOURCE_URL;

		}

		$this->init();
	}

	/**
	 * Initialize class.
	 *
	 * @since 3.9.1.2
	 */
	public function init() {

		$this->hooks();
	}

	/**
	 * Register hooks.
	 *
	 * @since 3.9.1.2
	 */
	public function hooks() {

		add_action( 'wp_ajax_automator_notification_dismiss', array( $this, 'dismiss' ) );

		add_action( 'automator_admin_notifications_update', array( $this, 'update' ) );

		add_action( 'automator_dashboard_header_after', array( $this, 'show_notifications' ) );

		add_action( 'automator_tools_header_after', array( $this, 'show_notifications' ) );

		if ( AUTOMATOR_POST_TYPE_RECIPE === automator_filter_input( 'post_type' ) ) {
			add_action(
				'current_screen',
				function () {
					$screen = get_current_screen();
					if ( 'edit-uo-recipe' === $screen->id ) {
						add_action( 'automator_show_internal_admin_notice', array( $this, 'show_notifications_as_admin_notice' ) );
					}
				},
				10
			);
		}
	}


	/**
	 * Check if user has access and is enabled.
	 *
	 * @return bool
	 * @since 3.9.1.2
	 */
	public function has_access() {

		$access = false;

		if ( current_user_can( automator_get_capability() ) ) {
			$access = true;
		}

		return apply_filters( 'automator_admin_notifications_has_access', $access );
	}

	/**
	 * Get option value.
	 *
	 * @param bool $cache Reference property cache if available.
	 *
	 * @return array
	 * @since 3.9.1.2
	 */
	public function get_option( $cache = true ) {

		if ( $this->option && $cache ) {
			return $this->option;
		}

		$option = automator_get_option( $this->option_name, array() );

		$this->option = array(
			'update'    => ! empty( $option['update'] ) ? $option['update'] : 0,
			'events'    => ! empty( $option['events'] ) ? $option['events'] : array(),
			'feed'      => ! empty( $option['feed'] ) ? $option['feed'] : array(),
			'dismissed' => ! empty( $option['dismissed'] ) ? $option['dismissed'] : array(),
		);

		return $this->option;
	}

	/**
	 * Fetch notifications from feed.
	 *
	 * @return array
	 * @since 3.9.1.2
	 */
	public function fetch_feed() {

		$res = wp_remote_get( $this->source_url );

		if ( is_wp_error( $res ) ) {

			return array();
		}

		$body = wp_remote_retrieve_body( $res );

		if ( empty( $body ) ) {
			return array();
		}

		return $this->verify( json_decode( $body, true ) );
	}

	/**
	 * Verify notification data before it is saved.
	 *
	 * @param array $notifications Array of notifications items to verify.
	 *
	 * @return array
	 * @since 3.9.1.2
	 */
	public function verify( $notifications ) { // phpcs:ignore Generic.Metrics.CyclomaticComplexity.TooHigh

		$data = array();

		if ( ! is_array( $notifications ) || empty( $notifications ) ) {
			return $data;
		}

		$option = $this->get_option();

		foreach ( $notifications as $notification ) {

			// The message and license should never be empty, if they are, ignore.
			if ( empty( $notification['content'] ) || empty( $notification['type'] ) ) {
				continue;
			}

			// Audience (license cohort) is intentionally NOT filtered at fetch time.
			// The stored feed is cohort-agnostic so a change in account state
			// (connect/disconnect, expiry, tier change) is reflected on the next
			// page load without a re-fetch. verify_active() applies the current
			// audience at display time.

			// Ignore if notification is not ready to display(based on start time).
			if ( ! empty( $notification['start'] ) && time() < strtotime( $notification['start'] ) ) {
				continue;
			}

			// Ignore if expired.
			if ( ! empty( $notification['end'] ) && time() > strtotime( $notification['end'] ) ) {
				continue;
			}

			// Ignore if notification has already been dismissed.
			$notification_already_dismissed = false;
			if ( is_array( $option['dismissed'] ) && ! empty( $option['dismissed'] ) ) {
				foreach ( $option['dismissed'] as $dismiss_notification ) {
					if ( $notification['id'] === $dismiss_notification['id'] ) {
						$notification_already_dismissed = true;
						break;
					}
				}
			}

			if ( true === $notification_already_dismissed ) {
				continue;
			}

			// Ignore if notification existed before installing automator.
			// Prevents bombarding the user with notifications after activation.
			$over_time = automator_get_option( 'automator_over_time', array() );

			if (
				! empty( $over_time['installed_date'] ) &&
				! empty( $notification['start'] ) &&
				$over_time['installed_date'] > strtotime( $notification['start'] )
			) {
				continue;
			}

			$data[] = $notification;
		}

		return $data;
	}

	/**
	 * Verify saved notification data for active notifications.
	 *
	 * @param array $notifications Array of notifications items to verify.
	 *
	 * @return array
	 * @since 3.9.1.2
	 */
	public function verify_active( $notifications ) {

		if ( ! is_array( $notifications ) || empty( $notifications ) ) {
			return array();
		}

		$audience = $this->get_notification_audience();

		// Remove notifications that are not active, or whose targets don't overlap the audience.
		foreach ( $notifications as $key => $notification ) {

			if (
				( ! empty( $notification['start'] ) && time() < strtotime( $notification['start'] ) ) ||
				( ! empty( $notification['end'] ) && time() > strtotime( $notification['end'] ) ) ||
				( ! empty( $notification['type'] ) && ! array_intersect( $audience, (array) $notification['type'] ) )
			) {
				unset( $notifications[ $key ] );
			}
		}

		return $notifications;
	}

	/**
	 * Get notification data.
	 *
	 * @return array
	 * @since 3.9.1.2
	 */
	public function get() {

		if ( ! $this->has_access() ) {
			return array();
		}

		$option = $this->get_option();

		// Refresh the feed on the daily cadence, or immediately when the site's
		// audience changes (account connect/disconnect, expiry, tier change) so a
		// freshly-relevant notice is not left unseen for up to a day.
		$refresh_due      = empty( $option['update'] ) || time() > $option['update'] + DAY_IN_SECONDS;
		$audience_changed = $this->current_audience_fingerprint() !== automator_get_option( 'automator_notifications_audience', '' );

		if ( $refresh_due || $audience_changed ) {
			if ( false === wp_next_scheduled( 'automator_admin_notifications_update' ) ) {
				wp_schedule_single_event( time(), 'automator_admin_notifications_update' );
			}
		}

		$events = ! empty( $option['events'] ) ? $this->verify_active( $option['events'] ) : array();
		$feed   = ! empty( $option['feed'] ) ? $this->verify_active( $option['feed'] ) : array();

		$notifications              = array();
		$notifications['active']    = array_merge( $events, $feed );
		$notifications['active']    = $this->get_notifications_with_human_readeable_start_time( $notifications['active'] );
		$notifications['active']    = $this->get_notifications_with_formatted_content( $notifications['active'] );
		$notifications['dismissed'] = ! empty( $option['dismissed'] ) ? $option['dismissed'] : array();
		$notifications['dismissed'] = $this->get_notifications_with_human_readeable_start_time( $notifications['dismissed'] );
		$notifications['dismissed'] = $this->get_notifications_with_formatted_content( $notifications['dismissed'] );

		return $notifications;
	}

	/**
	 * Improve format of the content of notifications before display. By default just runs wpautop.
	 *
	 * @param array $notifications The notifications to be parsed.
	 *
	 * @return mixed
	 */
	public function get_notifications_with_formatted_content( $notifications ) {
		if ( ! is_array( $notifications ) || empty( $notifications ) ) {
			return $notifications;
		}

		foreach ( $notifications as $key => $notification ) {
			if ( ! empty( $notification['content'] ) ) {
				$notifications[ $key ]['content'] = wpautop( $notification['content'] );
				$notifications[ $key ]['content'] = apply_filters( 'automator_notification_content_display', $notifications[ $key ]['content'] );
			}
		}

		return $notifications;
	}

	/**
	 * Get notifications start time with human time difference
	 *
	 * @return array $notifications
	 *
	 * @since 7.12.3
	 */
	public function get_notifications_with_human_readeable_start_time( $notifications ) {
		if ( ! is_array( $notifications ) || empty( $notifications ) ) {
			return $notifications;
		}

		foreach ( $notifications as $key => $notification ) {
			if ( ! isset( $notification['start'] ) || empty( $notification['start'] ) ) {
				continue;
			}

			$modified_start_time = sprintf(
				/* translators: %1$s: Human-readable time difference */
				esc_html_x( '%1$s ago', 'Notification time display', 'uncanny-automator' ),
				human_time_diff( strtotime( $notification['start'] ), current_time( 'timestamp' ) ) // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested
			);
			$notifications[ $key ]['start'] = $modified_start_time;
		}

		return $notifications;
	}

	/**
	 * Get active notifications.
	 *
	 * @return array $notifications['active'] active notifications
	 *
	 * @since 7.12.3
	 */
	public function get_active_notifications() {
		$notifications = $this->get();
		return isset( $notifications['active'] ) ? $notifications['active'] : array();
	}

	/**
	 * Get dismissed notifications.
	 *
	 * @return array $notifications['dismissed'] dismissed notifications
	 *
	 * @since 7.12.3
	 */
	public function get_dismissed_notifications() {
		$notifications = $this->get();

		return isset( $notifications['dismissed'] ) ? $notifications['dismissed'] : array();
	}

	/**
	 * Get notification count.
	 *
	 * @return int
	 * @since 3.9.1.2
	 */
	public function get_count() {

		return count( $this->get_active_notifications() );
	}

	/**
	 * Add a manual notification event.
	 *
	 * @param array $notification Notification data.
	 *
	 * @since 3.9.1.2
	 */
	public function add( $notification ) {

		if ( empty( $notification['id'] ) ) {
			return;
		}

		$option = $this->get_option();

		foreach ( $option['dismissed'] as $item ) {
			if ( $item['id'] === $notification['id'] ) {
				return;
			}
		}

		foreach ( $option['events'] as $item ) {
			if ( $item['id'] === $notification['id'] ) {
				return;
			}
		}

		$notification = $this->verify( array( $notification ) );

		automator_update_option(
			$this->option_name,
			array(
				'update'    => $option['update'],
				'feed'      => $option['feed'],
				'events'    => array_merge( $notification, $option['events'] ),
				'dismissed' => $option['dismissed'],
			),
			true
		);
	}

	/**
	 * Update notification data from feed.
	 *
	 * @param array $option (Optional) Added @since 7.13.2
	 *
	 * @since 3.9.1.2
	 */
	public function update() {

		$feed   = $this->fetch_feed();
		$option = $this->get_option();

		automator_update_option(
			$this->option_name,
			array(
				'update'    => time(),
				'feed'      => $feed,
				'events'    => $option['events'],
				'dismissed' => array_slice( $option['dismissed'], 0, 30 ), // Limit dismissed notifications to last 30.
			),
			true
		);

		// Record the audience this feed was fetched under, so get() can force an
		// immediate refresh when the site's audience later changes.
		automator_update_option( 'automator_notifications_audience', $this->current_audience_fingerprint() );
	}

	/**
	 * Dismiss notification via AJAX.
	 *
	 * @since 3.9.1.2
	 */
	public function dismiss() {

		// Run a security check.
		if ( ! wp_verify_nonce( automator_filter_input( 'nonce', INPUT_POST ), 'uncanny_automator' ) ) {
			return;
		}

		$notification_id = automator_filter_input( 'id', INPUT_POST );

		// Check for access and required param.
		if ( ! $this->has_access() || empty( $notification_id ) ) {
			wp_send_json_error();
		}

		$id = sanitize_text_field( wp_unslash( $notification_id ) );

		$option = $this->get_option();

		// Dismiss all notifications and add them to dissmiss array.
		if ( 'all' === $id ) {
			if ( is_array( $option['feed'] ) && ! empty( $option['feed'] ) ) {
				foreach ( $option['feed'] as $key => $notification ) {
					array_unshift( $option['dismissed'], $notification );
					unset( $option['feed'][ $key ] );
				}
			}
			if ( is_array( $option['events'] ) && ! empty( $option['events'] ) ) {
				foreach ( $option['events'] as $key => $notification ) {
					array_unshift( $option['dismissed'], $notification );
					unset( $option['events'][ $key ] );
				}
			}
		}

		$type = is_numeric( $id ) ? 'feed' : 'events';

		// Remove notification and add in dismissed array.
		if ( is_array( $option[ $type ] ) && ! empty( $option[ $type ] ) ) {
			foreach ( $option[ $type ] as $key => $notification ) {
				if ( $notification['id'] == $id ) { // phpcs:ignore WordPress.PHP.StrictComparisons
					// Add notification to dismissed array.
					array_unshift( $option['dismissed'], $notification );
					// Remove notification from feed or events.
					unset( $option[ $type ][ $key ] );
					break;
				}
			}
		}

		automator_update_option( $this->option_name, $option, true );

		wp_send_json_success();
	}

	/**
	 * This generates the markup for the notifications indicator if needed.
	 *
	 * @return string
	 */
	public function get_menu_count() {

		if ( $this->get_count() > 0 ) {
			return '<span class="automator-menu-notification-indicator update-plugins">' . $this->get_count() . '</span>';
		}

		return '';
	}

	/**
	 * Get the URL for the page where users can see/read notifications.
	 *
	 * @return string
	 */
	public function get_view_url( $scroll_to, $page, $tab = '' ) {
		$disabled = false;

		$url = add_query_arg(
			array(
				'page'                => $page,
				'automator-scroll'    => $scroll_to,
				'automator-highlight' => $scroll_to,
			),
			admin_url( 'admin.php' )
		);

		if ( ! empty( $tab ) ) {
			$url .= '#/' . $tab;
		}

		if ( false !== $disabled ) {
			$url = is_multisite() ? network_admin_url( 'admin.php?page=automator_network' ) : admin_url( 'admin.php?page=automator_settings' );
		}

		return $url;
	}

	/**
	 * Get the notification sidebar URL for the page where users can see/read notifications.
	 *
	 * @return string
	 */
	public function get_sidebar_url() {

		$disabled = false;

		$url = add_query_arg(
			array(
				'page' => 'automator_reports',
				'open' => 'automator_notification_sidebar',
			),
			admin_url( 'admin.php' )
		);

		if ( false !== $disabled ) {
			$url = is_multisite() ? network_admin_url( 'admin.php?page=automator_network' ) : admin_url( 'admin.php?page=automator_settings' );
		}

		return $url;
	}

	/**
	 * Render the notifications carousel inside a WP admin-notice wrapper.
	 *
	 * The carousel (banner.php) is a dashboard/settings-panel component. On the
	 * All Recipes list table it is echoed into the raw admin-notices slot, where
	 * — without the `notice` class — WP never gives it proper in-content
	 * placement (it renders flush-left, clipped behind the admin menu). Wrapping
	 * it in `uap notice` (same pattern as the sibling permalink notice) restores
	 * correct placement and applies the `.uap` design-system scope, while the
	 * outer chrome is zeroed so only the card's own styling shows.
	 *
	 * @return void
	 */
	public function show_notifications_as_admin_notice() {

		if ( empty( $this->get_active_notifications() ) ) {
			return;
		}

		echo '<div class="uap notice" style="padding:0;border:0;background:transparent;box-shadow:none;">';
		$this->show_notifications();
		echo '</div>';
	}

	/**
	 * @return void
	 */
	public function show_notifications() {

		$notifications = $this->get_active_notifications();

		$dismissed = $this->get_dismissed_notifications();

		wp_localize_script(
			'uap-admin',
			'uapNotifications',
			array(
				'itemsCount' => absint( count( $notifications ) ),
				'lastIndex'  => absint( count( $notifications ) ),
			)
		);

		require_once UA_ABSPATH . 'src/core/admin/notifications/views/banner.php';
	}

	/**
	 * @return string
	 */
	public function get_license_type() {

		return automator_license_manager()->get_type();
	}

	/**
	 * Fingerprint the site's current notification audience.
	 *
	 * Used to detect account-state changes (connect/disconnect, expiry, tier
	 * change) between feed fetches so get() can force an immediate refresh.
	 *
	 * @return string
	 */
	public function current_audience_fingerprint() {
		return implode( ',', $this->get_notification_audience() );
	}

	/**
	 * Resolve the current site into the notification audience slugs it belongs to.
	 *
	 * Mirrors the `mi_license` taxonomy on the notifications CDN. Returns the
	 * broad→specific chain (e.g. an active AI+Automation Plus site returns
	 * array( 'pro', 'pro-aa', 'pro-aa-plus' )). A notification is targeted when
	 * ANY slug overlaps its `type[]`, so tagging a notice `pro` reaches every Pro
	 * site while `pro-aa-plus` reaches only that tier.
	 *
	 * @return string[]
	 */
	public function get_notification_audience() {

		$license = automator_license_manager();

		// Lite — Pro plugin not active.
		if ( ! $license->is_pro_active() ) {
			return $this->lite_audience( (array) $license->get_license_data() );
		}

		// Active Pro — the locally stored EDD status is authoritative for terminal
		// states ( get_type() collapses anything non-valid to '' ).
		return $this->pro_audience(
			(string) automator_get_option( 'uap_automator_pro_license_status' ),
			(array) $license->get_license_data()
		);
	}

	/**
	 * Resolve the Lite audience chain.
	 *
	 * Splits on whether the Free Account ( download 23718 ) is connected, and —
	 * for connected accounts — whether an Agent (LLM) usage allocation exists.
	 * v2/credits omits the `llm_credits` key entirely when there is no allocation,
	 * so its absence marks the `lite-connected-no-agent` sub-cohort (targetable
	 * for "get Agent usage" messaging).
	 *
	 * @param array $license_data The cached v2/credits payload ( may be empty ).
	 *
	 * @return string[]
	 */
	protected function lite_audience( array $license_data ) {

		$download_id = (int) ( $license_data['download_id'] ?? $license_data['item_id'] ?? 0 );

		if ( 23718 !== $download_id ) {
			return array( 'free', 'lite-disconnected' );
		}

		$chain = array( 'free', 'lite-connected' );

		if ( empty( $license_data['llm_credits'] ) ) {
			$chain[] = 'lite-connected-no-agent';
		}

		return $chain;
	}

	/**
	 * Resolve the active-Pro audience chain — terminal states first, then the
	 * exact plan resolved from the license product.
	 *
	 * @param string $status       The stored EDD license status.
	 * @param array  $license_data The cached v2/credits payload.
	 *
	 * @return string[]
	 */
	protected function pro_audience( $status, array $license_data ) {

		// Terminal states are intentionally NOT merged with the generic `pro`
		// chain: a lapsed site sees only notices authored for its state, never
		// access-assuming `pro` notices (e.g. "Uncanny Agent — it's already
		// yours"). Win-back reach is per-notice — a notice can be tagged
		// pro-expired / pro-invalid alongside `pro` to include lapsed sites.
		if ( 'expired' === $status ) {
			return array( 'pro-expired' );
		}

		if ( 'valid' !== $status ) {
			return array( 'pro-invalid' );
		}

		$download_id = (int) ( $license_data['download_id'] ?? $license_data['item_id'] ?? 0 );
		$price_id    = (int) ( $license_data['price_id'] ?? 0 );

		return array_merge( array( 'pro' ), $this->pro_plan_slugs( $download_id, $price_id ) );
	}

	/**
	 * Map an EDD ( download_id, price_id ) pair to its notification plan slugs.
	 *
	 * Deterministic — mirrors the storefront product catalogue. Unknown pairs
	 * ( new/renamed variations, addon-only downloads ) return an empty array so
	 * the site still receives generic `pro` notices until the map is extended.
	 *
	 * @param int $download_id EDD product id.
	 * @param int $price_id    EDD variable-price id.
	 *
	 * @return string[]
	 */
	protected function pro_plan_slugs( $download_id, $price_id ) {

		$map = array(
			// Uncanny Automator Pro (506) — Legacy + AI+Automation subscription tiers.
			'506:1'  => array( 'pro-legacy', 'pro-legacy-basic' ),
			'506:2'  => array( 'pro-legacy', 'pro-legacy-plus' ),
			'506:3'  => array( 'pro-legacy', 'pro-legacy-elite' ),  // "Unlimited (Legacy)" — API reports license_plan: elite (PRO_ELITE_PRICE_IDS).
			'506:4'  => array( 'pro-legacy', 'pro-legacy-elite' ),
			'506:5'  => array( 'pro-aa', 'pro-aa-basic' ),
			'506:6'  => array( 'pro-aa', 'pro-aa-plus' ),
			'506:7'  => array( 'pro-aa', 'pro-aa-elite' ),
			'506:8'  => array( 'pro-aa', 'pro-aa-basic' ),   // Monthly.
			'506:9'  => array( 'pro-aa', 'pro-aa-plus' ),    // Monthly.
			'506:10' => array( 'pro-aa', 'pro-aa-elite' ),   // Monthly.

			// Uncanny Automator Lifetime (11067).
			'11067:1' => array( 'pro-lifetime', 'pro-lifetime-pro' ),
			'11067:2' => array( 'pro-lifetime', 'pro-lifetime-agency' ),
			'11067:3' => array( 'pro-lifetime', 'pro-lifetime-unlimited' ),
		);

		return $map[ $download_id . ':' . $price_id ] ?? array();
	}


	/**
	 * Add UTM parameters to any links.
	 *
	 * @param  mixed $url The url of the button.
	 * @param  mixed $campaign The title of the button. Urlencoded with spaces replaced by dash.
	 * @param  mixed $content The button tex. Urlencoded with spaces replaced by dash.
	 * @return string The link with utm specified parameters.
	 */
	public function url_add_utm( $url = '', $campaign = '', $content = '' ) {

		if ( empty( $url ) ) {
			return '';
		}

		return add_query_arg(
			array(
				'utm_medium'   => 'in-plugin',
				'utm_campaign' => strtolower( str_replace( ' ', '-', $campaign ) ),
				'utm_content'  => strtolower( str_replace( ' ', '-', $content ) ),
				'utm_source'   => 'uncanny-automator',
			),
			$url
		);
	}
}
