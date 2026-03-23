<?php
/**
 * MCP Client context helpers.
 *
 * @since 7.0.0
 */

declare(strict_types=1);

namespace Uncanny_Automator\Api\Transports\Model_Context_Protocol\Client;

use Uncanny_Automator\Api\Services\Plan\Plan_Service;


use WP_Post;
/**
 * Provides contextual helpers for rendering the MCP client UI.
 */
class Client_Context_Service {

	/**
	 * Determine whether the current user may access the client.
	 *
	 * @return bool
	 */
	public function can_access_client(): bool {
		return is_admin() && current_user_can( automator_get_capability() );
	}

	/**
	 * Check if the current admin screen is a recipe editor.
	 *
	 * @return bool
	 */
	public function is_recipe_screen(): bool {
		$screen = get_current_screen();

		return $screen && isset( $screen->id ) && false !== strpos( (string) $screen->id, 'uo-recipe' );
	}

	/**
	 * Decide whether to render the chat trigger button.
	 *
	 * @param $post WordPress' mixed typed parameter.
	 * @return bool
	 */
	public function should_render_button( $post ): bool {
		return true;
	}

	/**
	 * Get the current user's display name.
	 *
	 * @return string
	 */
	public function get_current_user_display_name(): string {
		$user = wp_get_current_user();

		return isset( $user->display_name ) ? (string) $user->display_name : '';
	}

	/**
	 * Whether WordPress is currently serving an admin request.
	 *
	 * @return bool
	 */
	public function is_admin_area(): bool {
		return is_admin();
	}

	/**
	 * Check an arbitrary capability for the current user.
	 *
	 * @param string $capability Capability to check.
	 * @return bool
	 */
	public function user_has_capability( string $capability = 'manage_options' ): bool {
		return current_user_can( $capability );  // phpcs:ignore WordPress.WP.Capabilities -- Dynamic capability check.
	}
	/**
	 * Get current user plan.
	 *
	 * @return string
	 */
	public function get_current_user_plan(): string {
		return ( new Plan_Service() )->get_current_plan_id();
	}
	/**
	 * Get current user plan name.
	 *
	 * @return string
	 */
	public function get_current_user_plan_name(): string {
		return ucwords( str_replace( '-', ' ', ( new Plan_Service() )->get_current_plan_id() ) );
	}
}
