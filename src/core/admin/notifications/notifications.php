<?php
namespace Uncanny_Automator;

/**
 * Notifications.
 *
 * @since 3.9.1.2
 */

class Automator_Notifications {

	/**
	 * Source of notifications content.
	 *
	 * @since {VERSION}
	 *
	 * @var string
	 */
	const SOURCE_URL = 'https://autonotifs-cdn.automatorplugin.com/wp-content/notifications.json';

	/**
	 * Option value.
	 *
	 * @since {VERSION}
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

	public function __construct() {
		$this->init();
	}

	/**
	 * Initialize class.
	 *
	 * @since {VERSION}
	 */
	public function init() {

		$this->hooks();
	}

	/**
	 * Register hooks.
	 *
	 * @since {VERSION}
	 */
	public function hooks() {

		add_action( 'wp_ajax_automator_notification_dismiss', array( $this, 'dismiss' ) );

		add_action( 'automator_admin_notifications_update', array( $this, 'update' ) );

		add_action( 'automator_settings_header_after', array( $this, 'show_notifications' ) );

		add_action( 'automator_dashboard_header_after', array( $this, 'show_notifications' ) );

		add_action( 'automator_tools_header_after', array( $this, 'show_notifications' ) );

		if ( 'uo-recipe' === automator_filter_input( 'post_type' ) ) {
			add_action(
				'current_screen',
				function() {
					$screen = get_current_screen();
					if ( 'edit-uo-recipe' === $screen->id ) {
						add_action( 'admin_notices', array( $this, 'show_notifications' ) );
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
	 * @since {VERSION}
	 */
	public function has_access() {

		$access = false;

		if ( current_user_can( 'manage_options' ) ) {
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
	 * @since {VERSION}
	 */
	public function get_option( $cache = true ) {

		if ( $this->option && $cache ) {
			return $this->option;
		}

		$option = get_option( $this->option_name, array() );

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
	 * @since {VERSION}
	 */
	public function fetch_feed() {

		$res = wp_remote_get( self::SOURCE_URL );

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
	 * @since {VERSION}
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

			// Ignore if license type does not match.
			$license_type = $this->get_license_type();

			if ( ! in_array( $license_type, $notification['type'], true ) ) {
				continue;
			}

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
			$over_time = get_option( 'automator_over_time', array() );

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
	 * @since {VERSION}
	 */
	public function verify_active( $notifications ) {

		if ( ! is_array( $notifications ) || empty( $notifications ) ) {
			return array();
		}

		$license_type = $this->get_license_type();

		// Remove notifications that are not active, or if the license type not exists
		foreach ( $notifications as $key => $notification ) {

			if (
				( ! empty( $notification['start'] ) && time() < strtotime( $notification['start'] ) ) ||
				( ! empty( $notification['end'] ) && time() > strtotime( $notification['end'] ) ) ||
				( ! empty( $notification['type'] ) && ! in_array( $license_type, $notification['type'], true ) )
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
	 * @since {VERSION}
	 */
	public function get() {

		if ( ! $this->has_access() ) {
			return array();
		}

		$option = $this->get_option();

		// Update notifications using async task.
		if ( empty( $option['update'] ) || time() > $option['update'] - DAY_IN_SECONDS ) {
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
			return;
		}

		foreach ( $notifications as $key => $notification ) {
			if ( ! isset( $notification['start'] ) || empty( $notification['start'] ) ) {
				continue;
			}

			// Translators: Readable time to display
			$modified_start_time            = sprintf( __( '%1$s ago', 'google-analytics-for-wordpress' ), human_time_diff( strtotime( $notification['start'] ), current_time( 'timestamp' ) ) ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested
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
	 * @since {VERSION}
	 */
	public function get_count() {

		return count( $this->get_active_notifications() );
	}

	/**
	 * Add a manual notification event.
	 *
	 * @param array $notification Notification data.
	 *
	 * @since {VERSION}
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

		update_option(
			$this->option_name,
			array(
				'update'    => $option['update'],
				'feed'      => $option['feed'],
				'events'    => array_merge( $notification, $option['events'] ),
				'dismissed' => $option['dismissed'],
			),
			false
		);
	}

	/**
	 * Update notification data from feed.
	 *
	 * @param array $option (Optional) Added @since 7.13.2
	 *
	 * @since {VERSION}
	 */
	public function update() {

		$feed   = $this->fetch_feed();
		$option = $this->get_option();

		update_option(
			$this->option_name,
			array(
				'update'    => time(),
				'feed'      => $feed,
				'events'    => $option['events'],
				'dismissed' => array_slice( $option['dismissed'], 0, 30 ), // Limit dismissed notifications to last 30.
			),
			false
		);
	}

	/**
	 * Dismiss notification via AJAX.
	 *
	 * @since {VERSION}
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

		update_option( $this->option_name, $option, false );

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
	 * Delete the notification options.
	 */
	public function delete_notifications_data() {

		delete_option( $this->option_name );

		// Delete old notices option.
		delete_option( 'automator_notices' );

		automator_notification_event_runner()->delete_data();

	}

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

	public function get_license_type() {

		return Api_Server::get_license_type();

	}

}
