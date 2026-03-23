<?php

namespace Uncanny_Automator;

use WP_REST_Request;
use WP_REST_Response;

/**
 * Class Automator_Tooltip_Notification
 *
 * Handles the tooltip notification that appears 48 hours after installation if no recipe is created.
 */
class Automator_Tooltip_Notification {
	/**
	 * Automator_Tooltip_Notification constructor.
	 *
	 * Initializes the class and registers the REST route.
	 */
	public function __construct() {
		// Register the REST API route
		add_action( 'rest_api_init', array( $this, 'register_rest_route' ) );

		// Enqueue the tooltip scripts
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	/**
	 * Registers the REST API route for the tooltip notification.
	 *
	 * The route is /tooltip-notification/tooltip_id/{tooltip_id}/tooltip_action/{tooltip_action}/
	 *
	 * @return void
	 */
	public function register_rest_route() {
		register_rest_route(
			AUTOMATOR_REST_API_END_POINT,
			'/tooltip-notification/tooltip_id/(?P<tooltip_id>[\w-]+)/tooltip_action/(?P<tooltip_action>[\w-]+)/',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'tooltip_notification_endpoint' ),
				'permission_callback' => function () {
					return is_user_logged_in() && current_user_can( automator_get_capability() );
				},
			)
		);
	}

	/**
	 * Callback function for the REST API endpoint.
	 *
	 * Handles the POST request to update the tooltip visibility based on the tooltip ID and action provided.
	 *
	 * @param WP_REST_Request $request The REST API request object.
	 *
	 * @return WP_REST_Response The REST API response object.
	 */
	public function tooltip_notification_endpoint( WP_REST_Request $request ) {
		// Get the current user ID
		$user_id = get_current_user_id();

		// Check if the user is logged in
		if ( ! $user_id ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => 'User not logged in.',
				),
				401
			);
		}

		$tooltip_id     = $request->get_param( 'tooltip_id' );
		$tooltip_action = $request->get_param( 'tooltip_action' );

		if ( ! isset( $tooltip_id ) || ! isset( $tooltip_action ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => 'Missing required parameters: tooltip_id or tooltip_action',
				),
				200
			);
		}

		// Get the user's tooltips visibility from user meta
		$tooltips_visibility = get_user_meta( $user_id, 'automator_tooltips_visibility', true );

		if ( ! is_array( $tooltips_visibility ) ) {
			$tooltips_visibility = array();
		}

		$tooltips_visibility[ $tooltip_id ] = time();
		$update_result                      = update_user_meta( $user_id, 'automator_tooltips_visibility', $tooltips_visibility );

		if ( ! $update_result ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => 'Failed to update tooltip visibility.',
				),
				500
			);
		}

		return new WP_REST_Response(
			array(
				'success'        => true,
				'tooltip_id'     => $tooltip_id,
				'tooltip_action' => $tooltip_action,
			),
			200
		);
	}

	/**
	 * Load assets for tooltip notifications. These are loaded on all admin pages.
	 *
	 * @return void
	 */
	public function enqueue_scripts() {
		if ( apply_filters( 'automator_tooltip_notifications_disable_assets', false ) ) {
			return;
		}

		Utilities::enqueue_asset(
			'uap-tooltip-notification',
			'tooltip-notification',
			array(
				'localize' => array(
					'UncannyAutomatorTooltipNotification' => $this->assets_tooltip_notification_js_object(),
				),
			)
		);
	}

	/**
	 * Get the JavaScript object for the tooltip notification.
	 *
	 * @return array The JavaScript object.
	 */
	private function assets_tooltip_notification_js_object() {
		$data = array(
			'rest' => array(
				'url'   => esc_url_raw( rest_url() . AUTOMATOR_REST_API_END_POINT ),
				'nonce' => \wp_create_nonce( 'wp_rest' ),
			),
		);

		return $data;
	}
}
