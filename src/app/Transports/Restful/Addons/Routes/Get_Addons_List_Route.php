<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Restful\Addons\Routes;

use Uncanny_Automator\App\Plan\Addons\Services\Addon_Catalog_Service;
use Uncanny_Automator\App\Plan\Addons\Services\Addon_Entitlement_Service;
use Uncanny_Automator\App\Plan\Addons\Services\Addon_Status_Service;
use Uncanny_Automator\App\Plan\Addons\Services\License_Summary_Service;
use Uncanny_Automator\App\Plan\Addons\Value_Objects\Addon;
use Uncanny_Automator\App\Transports\Restful\Addons\Addon_Card_Response;
use Uncanny_Automator\App\Transports\Restful\Addons\Addons_List_Response;
use WP_REST_Request;
use WP_REST_Response;

/**
 * GET /uap/v2/addons/get-list/{plus|elite} — one addon group.
 *
 * The list IS the feed, so an unreachable feed fails loud with a 400 rather
 * than rendering an empty group that reads like "no addons exist".
 */
final class Get_Addons_List_Route {

	/**
	 * Handle the request.
	 *
	 * @param WP_REST_Request $request The request.
	 *
	 * @return WP_REST_Response
	 */
	public static function handle( WP_REST_Request $request ): WP_REST_Response {

		// Read the path, not get_param(): WP resolves query params ahead of URL
		// segments, so ?list_code=elite on a /get-list/plus request would win.
		$url_params = $request->get_url_params();
		$list_code  = isset( $url_params['list_code'] ) ? (string) $url_params['list_code'] : '';

		if ( ! in_array( $list_code, array( Addon::TIER_PLUS, Addon::TIER_ELITE ), true ) ) {
			return self::failure( esc_html_x( 'Invalid list requested.', 'Addons', 'uncanny-automator' ) );
		}

		$addons = Addon_Catalog_Service::instance()->for_list_code( $list_code );
		if ( is_wp_error( $addons ) ) {
			return self::failure( $addons->get_error_message() );
		}

		$has_access = Addon_Entitlement_Service::instance()->has_access_to_tier(
			Addon::TIER_ELITE === $list_code ? Addon::TIER_ELITE : Addon::TIER_PLUS
		);

		$cards = array();
		foreach ( $addons as $addon ) {
			$cards[] = Addon_Card_Response::card(
				$addon,
				Addon_Status_Service::instance()->get_state( $addon ),
				$has_access
			);
		}

		return new WP_REST_Response(
			array(
				'success' => true,
				'data'    => Addons_List_Response::body(
					$list_code,
					$has_access,
					$cards,
					License_Summary_Service::instance()->get_snapshot()
				),
			),
			200
		);
	}

	/**
	 * The failure shape `fetchApi` reads.
	 *
	 * @param string $message Failure message.
	 *
	 * @return WP_REST_Response
	 */
	private static function failure( string $message ): WP_REST_Response {
		return new WP_REST_Response(
			array(
				'success' => false,
				'error'   => $message,
			),
			400
		);
	}
}
