<?php
/**
 * LearnDash owns its native curriculum route and MCP exposure.
 *
 * @package Uncanny_Automator\Integrations\Learndash\Mcp
 */

declare(strict_types=1);

namespace Uncanny_Automator\Integrations\Learndash\Mcp;

/**
 * Registers the LearnDash curriculum adapter and reviews its MCP exposure.
 *
 * Keeping registration here lets the active integration own LearnDash's
 * dependencies and permissions without adding plugin-specific rules to core.
 *
 * @package Uncanny_Automator\Integrations\Learndash\Mcp
 */
final class Curriculum_Operations {

	/**
	 * Register the integration's REST and MCP hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		// LearnDash loads its native controllers at priority 10; check them afterward.
		add_action( 'rest_api_init', array( $this, 'register_routes' ), 20 );
		// Review after ordinary opt-ins so they cannot admit a replacement handler.
		add_filter( 'automator_mcp_rest_operation_policy_review', array( $this, 'review_policy' ), PHP_INT_MAX, 4 );
	}

	/**
	 * Register the curriculum route when the required native API is available.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		( new Curriculum_Rest_Controller() )->register_routes();
	}

	/**
	 * Review the owned curriculum route and leave unrelated policies unchanged.
	 *
	 * @param array|null  $policy  Policy from earlier filters.
	 * @param string|null $route   Registered route pattern.
	 * @param string|null $method  HTTP method.
	 * @param array|null  $handler Registered route handler.
	 *
	 * @return array|null Reviewed policy, or null when the handler is not supported.
	 */
	public function review_policy( $policy, $route = null, $method = null, $handler = null ) {
		if ( Curriculum_Rest_Controller::ROUTE !== $route ) {
			return $policy;
		}
		$callback = $handler['callback'] ?? null;
		$action   = 'GET' === $method ? 'get_item' : ( 'POST' === $method ? 'update_item' : null );
		// Another plugin may replace this route; its callbacks have not been reviewed.
		if ( ! is_array( $callback ) || ! isset( $callback[0], $callback[1] ) || ! $callback[0] instanceof Curriculum_Rest_Controller || $action !== $callback[1] || array( $callback[0], 'permissions_check' ) !== ( $handler['permission_callback'] ?? null ) ) {
			return null;
		}
		// Native GET can repair relationships, so it requires write access as well.
		return array(
			'read_only' => false,
			'tools'     => array( 'get_posts' ),
		);
	}
}
