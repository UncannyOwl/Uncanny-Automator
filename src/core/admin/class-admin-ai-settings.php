<?php
/**
 * Who may manage the site's AI settings.
 *
 * The Automator menu's AI entry ("Connect your AI" or, once an AI app is
 * connected, "AI Connections") lives in Admin_Ai_Menu since 8.0.
 *
 * @package Uncanny_Automator
 */

namespace Uncanny_Automator;

/**
 * Class Admin_Ai_Settings
 */
class Admin_Ai_Settings {

	/**
	 * Tells whether the current user can manage the site's AI settings and read its AI activity.
	 *
	 * Every AI route requires both the Automator admin capability and the WordPress capability to manage site options, so the AI parts of the admin show only to users who have both.
	 *
	 * @return bool
	 */
	public static function current_user_can_manage_ai(): bool {
		return current_user_can( automator_get_admin_capability() ) && current_user_can( 'manage_options' ); // phpcs:ignore WordPress.WP.Capabilities.Undetermined
	}
}
