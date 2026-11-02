<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Restful\Addons\Routes;

use Uncanny_Automator\App\Plan\Addons\Services\Addon_Entitlement_Service;
use Uncanny_Automator\App\Plan\Addons\Services\Addon_Installer_Service;
use Uncanny_Automator\App\Plan\Addons\Services\Addon_Status_Service;
use Uncanny_Automator\App\Transports\Restful\Addons\Addon_Card_Response;
use Uncanny_Automator\App\Transports\Restful\Addons\Plugin_Manager_Response;
use WP_REST_Request;
use WP_REST_Response;

/**
 * POST /uap/v2/addons/plugin-manager/{action}/{addon_id} — install, update,
 * activate or download one addon.
 *
 * Always answers 200: the outcome lives in the body, which the frontend
 * strict-parses without an envelope.
 */
final class Plugin_Manager_Route {

	/**
	 * Handle the request.
	 *
	 * @param WP_REST_Request $request The request.
	 *
	 * @return WP_REST_Response
	 */
	public static function handle( WP_REST_Request $request ): WP_REST_Response {

		// Read the path, not get_param(): for a POST, WP resolves body and query
		// params ahead of URL segments, so ?action=download on an /install/
		// request would execute the wrong one.
		$url_params = $request->get_url_params();
		$action     = isset( $url_params['action'] ) ? (string) $url_params['action'] : '';
		$addon_id   = isset( $url_params['addon_id'] ) ? absint( $url_params['addon_id'] ) : 0;
		$service    = Addon_Installer_Service::instance();
		$result     = $service->execute( $action, $addon_id );

		// No card for an action that does not exist: `data` is omitted for an
		// unrecognised action just as it is for an unknown addon.
		$card = in_array( $action, Addon_Installer_Service::ACTIONS, true )
			? self::card( $service, $addon_id )
			: null;

		return new WP_REST_Response( Plugin_Manager_Response::body( $result, $card ), 200 );
	}

	/**
	 * The refreshed card for the addon that was acted on.
	 *
	 * Null when the addon does not exist, so the response omits `data`
	 * entirely rather than shaping a card from nothing.
	 *
	 * @param Addon_Installer_Service $service  Installer service.
	 * @param int                     $addon_id Product id.
	 *
	 * @return array|null
	 */
	private static function card( Addon_Installer_Service $service, int $addon_id ): ?array {

		$addon = $service->find_addon( $addon_id );
		if ( null === $addon ) {
			return null;
		}

		return Addon_Card_Response::card(
			$addon,
			Addon_Status_Service::instance()->get_state( $addon ),
			Addon_Entitlement_Service::instance()->has_access_to_tier( $addon->required_tier() )
		);
	}
}
