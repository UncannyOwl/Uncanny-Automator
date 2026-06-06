<?php
/**
 * MCP Client context helpers.
 *
 * @since 7.0.0
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Model_Context_Protocol\Client;

use Uncanny_Automator\App\Plan\Services\Plan_Service;


use WP_Post;
/**
 * Provides contextual helpers for rendering the MCP client UI.
 */
class Client_Context_Service {

	/**
	 * Default capability required to use Uncanny Agent.
	 *
	 * @var string
	 */
	private const AGENT_ACCESS_CAPABILITY = 'manage_options';

	/**
	 * Determine whether the current user may access the client.
	 *
	 * @return bool
	 */
	public function can_access_client(): bool {
		return is_admin() && $this->user_has_capability( $this->get_client_access_capability() );
	}

	/**
	 * Resolve the capability required to use Uncanny Agent.
	 *
	 * This is intentionally not filterable. Uncanny Agent can inspect and operate
	 * on privileged site data, so third-party code must not be able to lower the
	 * access boundary at runtime.
	 *
	 * @return string
	 */
	public function get_client_access_capability(): string {
		return self::AGENT_ACCESS_CAPABILITY;
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
	 * Get the current user's locale as a BCP-47 language tag for the chat client SDK.
	 *
	 * WordPress stores locales as `xx_YY` (`es_AR`); the SDK reads its `locale` attribute as BCP-47 (`es-AR`). Variant suffixes such as `_formal` / `_informal` are dropped since the SDK has no concept of formality.
	 *
	 * @return string
	 */
	public function get_user_locale_bcp47(): string {

		$wp_locale = get_user_locale();

		$parts = explode( '_', $wp_locale );
		if ( count( $parts ) > 2 ) {
			$parts = array_slice( $parts, 0, 2 );
		}

		$language = strtolower( $parts[0] );
		$region   = isset( $parts[1] ) ? strtoupper( $parts[1] ) : '';

		return '' === $region ? $language : "{$language}-{$region}";
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
