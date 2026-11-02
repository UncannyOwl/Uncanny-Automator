<?php
/**
 * WooCommerce owns the MCP exposure of its own REST API versions 1, 2 and 3.
 *
 * @package Uncanny_Automator\Integrations\Woocommerce\Mcp
 */

declare(strict_types=1);

namespace Uncanny_Automator\Integrations\Woocommerce\Mcp;

use Uncanny_Automator\App\Transports\Model_Context_Protocol\Tools\Rest\Rest_Operation_Policy;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\Tools\Rest\Rest_Request_Scope;

/**
 * Store setup and catalogue work on WooCommerce's own v1, v2 and v3 controllers, without secrets or money movement.
 *
 * Keeping this here lets the active integration own WooCommerce's controllers
 * and settings shapes without adding plugin-specific rules to core.
 *
 * @package Uncanny_Automator\Integrations\Woocommerce\Mcp
 */
final class Woocommerce_Rest_Operations {

	/**
	 * Register the integration's MCP hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		// Review after ordinary opt-ins so they cannot admit a closed or replaced route.
		add_filter( 'automator_mcp_rest_operation_policy_review', array( $this, 'review_policy' ), PHP_INT_MAX, 4 );
		add_filter( 'rest_request_before_callbacks', array( $this, 'refuse_blank_secrets' ), 10, 3 );
		add_filter( 'rest_post_dispatch', array( $this, 'mask_secrets' ), PHP_INT_MAX, 3 );
	}

	/**
	 * Expose WooCommerce's own v1, v2 and v3 controllers and leave other plugins' routes unchanged.
	 *
	 * @param array|null  $policy  Policy from earlier filters.
	 * @param string|null $route   Registered route pattern.
	 * @param string|null $method  HTTP method.
	 * @param array|null  $handler Registered route handler.
	 *
	 * @return array|null Reviewed policy, or null when the operation stays closed.
	 */
	public function review_policy( $policy, $route = null, $method = null, $handler = null ) {
		if ( ! is_string( $route ) || ! is_string( $method ) || ! is_array( $handler ) || ! self::is_owned( $route, $handler ) ) {
			return $policy;
		}
		$controller = $handler['callback'][0];
		// Webhooks hold secrets and send store data to any URL; system status tools run maintenance jobs.
		if ( preg_match( '#^/wc/v[123]/(?:webhooks|system_status)(?:/|$)#i', $route ) ) {
			return null;
		}
		// WooCommerce batch deletes always force a permanent delete.
		if ( preg_match( '#/batch$#i', $route ) ) {
			return null;
		}
		// Another plugin may replace the permission check; only WooCommerce's own has been reviewed.
		$permission = $handler['permission_callback'] ?? null;
		if ( ! is_array( $permission ) || ( $permission[0] ?? null ) !== $controller ) {
			return null;
		}
		$read = in_array( $method, array( 'GET', 'HEAD' ), true );
		// Customers are WordPress users, so an edit could change an administrator's email or password.
		if ( ! $read && $controller instanceof \WC_REST_Customers_V1_Controller ) {
			return null;
		}
		// A refund can send money back through the payment gateway.
		if ( 'POST' === $method && ( $controller instanceof \WC_REST_Order_Refunds_V1_Controller || $controller instanceof \WC_REST_Order_Refunds_V2_Controller ) ) {
			return null;
		}
		$reviewed = array(
			'read_only' => $read,
			'tools'     => array(),
		);
		if ( 'DELETE' === $method ) {
			$reviewed['request_schema'] = Rest_Operation_Policy::TRASH_ONLY_REQUEST_SCHEMA;
		}
		return $reviewed;
	}

	/**
	 * Refuse an empty value for a password setting: the AI only ever sees it blanked, so sending it back would erase it.
	 *
	 * @param mixed            $response Response from earlier filters.
	 * @param array            $handler  Matched route handler.
	 * @param \WP_REST_Request $request  Request.
	 *
	 * @return mixed
	 */
	public function refuse_blank_secrets( $response, $handler = null, $request = null ) {
		if ( null !== $response || ! $request instanceof \WP_REST_Request || ! is_array( $handler ) || ! $this->is_mcp_request( $request, $handler ) ) {
			return $response;
		}
		$controller = $handler['callback'][0];
		$body       = $request->get_json_params();
		if ( ! is_array( $body ) ) {
			return $response;
		}
		$secrets = array();
		if ( $controller instanceof \WC_REST_Payment_Gateways_V2_Controller && is_array( $body['settings'] ?? null ) ) {
			$gateway = WC()->payment_gateways()->payment_gateways()[ (string) $request['id'] ] ?? null;
			if ( $gateway instanceof \WC_Payment_Gateway ) {
				foreach ( $gateway->get_form_fields() as $key => $field ) {
					if ( 'password' === ( $field['type'] ?? null ) && '' === ( $body['settings'][ $key ] ?? null ) ) {
						$secrets[] = $key;
					}
				}
			}
		}
		if ( $controller instanceof \WC_REST_Setting_Options_V2_Controller && '' === ( $body['value'] ?? null ) ) {
			$setting = $controller->get_setting( (string) $request['group_id'], (string) $request['id'] );
			if ( is_array( $setting ) && 'password' === ( $setting['type'] ?? null ) ) {
				$secrets[] = (string) $request['id'];
			}
		}
		if ( array() === $secrets ) {
			return $response;
		}
		return new \WP_Error(
			'rest_invalid_param',
			sprintf( 'These settings are hidden from AI apps, so an empty value would erase them: %s. Leave them out to keep them, or send the new value.', implode( ', ', $secrets ) ),
			array( 'status' => 400 )
		);
	}

	/**
	 * Blank every setting WooCommerce declares as a password, such as a gateway's secret key.
	 *
	 * @param mixed            $response Response.
	 * @param mixed            $server   REST server.
	 * @param \WP_REST_Request $request  Request.
	 *
	 * @return mixed
	 */
	public function mask_secrets( $response, $server = null, $request = null ) {
		if ( ! $response instanceof \WP_REST_Response || ! $request instanceof \WP_REST_Request ) {
			return $response;
		}
		$handler = $response->get_matched_handler();
		if ( ! is_array( $handler ) || ! $this->is_mcp_request( $request, $handler ) ) {
			return $response;
		}
		$response->set_data( self::mask( $response->get_data() ) );
		return $response;
	}

	/**
	 * Whether this WooCommerce request is running for an MCP caller.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @param array            $handler Matched route handler.
	 *
	 * @return bool
	 */
	private function is_mcp_request( \WP_REST_Request $request, array $handler ): bool {
		return self::is_owned( $request->get_route(), $handler ) && null !== Rest_Request_Scope::current_context_for_request( $request );
	}

	/**
	 * Whether WooCommerce's own v1, v2 or v3 controller handles this route; other plugins keep the default policy.
	 *
	 * @param string $route   Route.
	 * @param array  $handler Route handler.
	 *
	 * @return bool
	 */
	private static function is_owned( string $route, array $handler ): bool {
		$callback = $handler['callback'] ?? null;
		if ( 1 !== preg_match( '#^/wc/v[123]/#', $route ) || ! defined( 'WC_ABSPATH' ) || ! is_array( $callback ) || ! isset( $callback[0] ) || ! is_object( $callback[0] ) ) {
			return false;
		}
		$file = ( new \ReflectionClass( $callback[0] ) )->getFileName();
		// URL matching alone would also claim third-party controllers in WooCommerce's namespace.
		$controllers = wp_normalize_path( WC_ABSPATH . 'includes/rest-api/Controllers/' );
		return is_string( $file ) && 1 === preg_match( '#^' . preg_quote( $controllers, '#' ) . 'Version[123]/#', wp_normalize_path( $file ) );
	}

	/**
	 * Blank the value of every node WooCommerce types as a password.
	 *
	 * @param mixed $data Response data.
	 *
	 * @return mixed
	 */
	private static function mask( $data ) {
		if ( ! is_array( $data ) ) {
			return $data;
		}
		if ( 'password' === ( $data['type'] ?? null ) && array_key_exists( 'value', $data ) ) {
			$data['value'] = '';
		}
		foreach ( $data as $key => $child ) {
			$data[ $key ] = self::mask( $child );
		}
		return $data;
	}
}
