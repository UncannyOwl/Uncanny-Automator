<?php
/**
 * Common Token Registry.
 *
 * Single source of truth for common (site-level and user-level) tokens
 * and date/time tokens. Replaces duplicated lists in Get_Tokens_Tool
 * and User_Selector_Advisor.
 *
 * @package Uncanny_Automator\Api\Services\Token
 * @since   7.1.0
 */

declare(strict_types=1);

namespace Uncanny_Automator\Api\Services\Token;

/**
 * Common_Token_Registry
 *
 * @since 7.1.0
 */
class Common_Token_Registry {

	/**
	 * Get common system tokens.
	 *
	 * Includes site-level tokens (no user required) and user-level tokens
	 * (requiresUser = true). This is the canonical list consumed by
	 * Token_Catalog_Service and User_Selector_Advisor.
	 *
	 * @since 7.1.0
	 *
	 * @return array[] Token definitions with name, usage, and optional requiresUser flag.
	 */
	public function get_common_tokens(): array {
		return array(
			// Site tokens -- no user required.
			array(
				'name'  => 'Admin email',
				'usage' => '{{admin_email}}',
			),
			array(
				'name'  => 'Recipe ID',
				'usage' => '{{recipe_id}}',
			),
			array(
				'name'  => 'Recipe name',
				'usage' => '{{recipe_name}}',
			),
			array(
				'name'  => 'Site name',
				'usage' => '{{site_name}}',
			),
			array(
				'name'  => 'Site URL',
				'usage' => '{{site_url}}',
			),
			// User tokens -- require logged-in user or user selector for anonymous recipes.
			array(
				'name'         => 'User email',
				'usage'        => '{{user_email}}',
				'requiresUser' => true,
			),
			array(
				'name'         => 'User first name',
				'usage'        => '{{user_firstname}}',
				'requiresUser' => true,
			),
			array(
				'name'         => 'User last name',
				'usage'        => '{{user_lastname}}',
				'requiresUser' => true,
			),
			array(
				'name'         => 'User ID',
				'usage'        => '{{user_id}}',
				'requiresUser' => true,
			),
			array(
				'name'         => 'User display name',
				'usage'        => '{{user_displayname}}',
				'requiresUser' => true,
			),
			array(
				'name'         => 'User username',
				'usage'        => '{{user_username}}',
				'requiresUser' => true,
			),
			array(
				'name'         => 'User role',
				'usage'        => '{{user_role}}',
				'requiresUser' => true,
			),
			array(
				'name'         => 'User IP address',
				'usage'        => '{{user_ip_address}}',
				'requiresUser' => true,
			),
			array(
				'name'         => 'User locale',
				'usage'        => '{{user_locale}}',
				'requiresUser' => true,
			),
			array(
				'name'         => 'User registered',
				'usage'        => '{{user_registered}}',
				'requiresUser' => true,
			),
			array(
				'name'         => 'User reset password URL',
				'usage'        => '{{user_reset_pass_url}}',
				'requiresUser' => true,
			),
			array(
				'name'         => 'Reset password link',
				'usage'        => '{{reset_pass_link}}',
				'requiresUser' => true,
			),
		);
	}

	/**
	 * Get date and time tokens.
	 *
	 * @since 7.1.0
	 *
	 * @return array[] Token definitions with name and usage.
	 */
	public function get_date_time_tokens(): array {
		return array(
			array(
				'name'  => 'Current date and time',
				'usage' => '{{current_date_and_time}}',
			),
			array(
				'name'  => 'Current date',
				'usage' => '{{current_date}}',
			),
			array(
				'name'  => 'Current time',
				'usage' => '{{current_time}}',
			),
			array(
				'name'  => 'Current Unix timestamp',
				'usage' => '{{current_unix_timestamp}}',
			),
		);
	}

	/**
	 * Get token patterns that require user context.
	 *
	 * Returns the usage strings (e.g. "{{user_email}}") for all common
	 * tokens that have requiresUser=true. Used by User_Selector_Advisor
	 * to detect user-dependent tokens in action fields.
	 *
	 * @since 7.1.0
	 *
	 * @return string[] Token usage patterns that require a user.
	 */
	public function get_user_requiring_patterns(): array {
		$patterns = array();

		foreach ( $this->get_common_tokens() as $token ) {
			if ( ! empty( $token['requiresUser'] ) ) {
				$patterns[] = $token['usage'];
			}
		}

		return $patterns;
	}
}
