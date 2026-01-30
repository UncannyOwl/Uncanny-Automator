<?php
/**
 * MCP catalog tool that enumerates the tokens available to a recipe.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog\Recipes;

use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Abstract_MCP_Tool;
use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Json_Rpc_Response;
use Uncanny_Automator\Api\Components\User\Value_Objects\User_Context;
use Uncanny_Automator\Api\Components\Token\Integration\Registry\WP_Integration_Token_Registry;

/**
 * MCP Tool for retrieving available tokens from an Automator recipe.
 *
 * This tool provides AI agents with a comprehensive list of all tokens available
 * within a specific recipe context, categorized by type for easy discovery and usage.
 *
 * @since 7.0.0
 */
class Get_Recipe_Tokens_Tool extends Abstract_MCP_Tool {

	/**
	 * Get the tool's unique identifier name.
	 *
	 * @since 7.0.0
	 * @return string The tool's system name used for MCP registration.
	 */
	public function get_name() {
		return 'get_recipe_tokens';
	}

	/**
	 * Get the tool's human-readable description for AI agent guidance.
	 *
	 * @since 7.0.0
	 * @return string Detailed description explaining tool purpose and token categories.
	 */
	public function get_description() {
		return 'List tokens available in a recipe. Returns grouped tokens (user, trigger, system) for populating action fields with dynamic data.';
	}

	/**
	 * Define the JSON schema for tool parameters.
	 *
	 * @since 7.0.0
	 * @return array JSON Schema array defining required parameters.
	 */
	public function schema_definition() {
		return array(
			'type'       => 'object',
			'properties' => array(
				'recipe_id' => array(
					'type'        => 'integer',
					'description' => 'The ID of the recipe to retrieve tokens for.',
					'minimum'     => 1,
				),
			),
			'required'   => array( 'recipe_id' ),
		);
	}

	/**
	 * Execute the tool to retrieve recipe tokens.
	 *
	 * @since 7.0.0
	 * @param User_Context $user_context The current user context object.
	 * @param array        $params       Tool parameters containing recipe_id.
	 * @return array JSON-RPC response with categorized token data.
	 */
	public function execute_tool( User_Context $user_context, array $params ) {
		$recipe_id = isset( $params['recipe_id'] ) ? (int) $params['recipe_id'] : 0;

		if ( $recipe_id <= 0 ) {
			return Json_Rpc_Response::create_error_response( 'Parameter recipe_id must be a positive integer.' );
		}

		$token_categories = $this->initialize_token_categories();

		try {
			$recipe_object = $this->get_recipe_object( $recipe_id );

			if ( ! empty( $recipe_object ) ) {
				$token_categories = $this->add_dynamic_tokens( $token_categories, $recipe_object );
			}
		} catch ( \Exception $e ) {
			return Json_Rpc_Response::create_error_response( $e->getMessage() );
		}

		return Json_Rpc_Response::create_success_response( 'Recipe tokens retrieved successfully', $token_categories );
	}

	/**
	 * Initialize the base token categories with static tokens.
	 *
	 * @since 7.0.0
	 * @return array Initial token category structure with static tokens.
	 */
	private function initialize_token_categories() {
		return array(
			'advanced'       => array(
				'description' => 'Universal Tokens for accessing meta fields, calculations, and integration-specific data. These are template-based tokens - use the idTemplate and fields to construct valid tokens by replacing placeholders with actual values.',
				'tokens'      => $this->get_advanced_tokens(),
			),
			'common'         => array(
				'description' => 'General tokens always available (user_email, site_name, etc.)',
				'tokens'      => $this->get_common_tokens(),
			),
			'date-and-time'  => array(
				'description' => 'Current date, time, and timestamp tokens (always available)',
				'tokens'      => $this->get_date_and_time_tokens(),
			),
			'trigger-tokens' => array(
				'description' => 'Tokens from recipe triggers (only available after adding triggers)',
			),
			'action-tokens'  => array(
				'description' => 'Tokens from recipe actions (only available after adding actions)',
			),
		);
	}

	/**
	 * Retrieve the recipe object from Automator.
	 *
	 * @since 7.0.0
	 * @param int $recipe_id The recipe ID to retrieve.
	 * @return array The recipe object data or empty array if not found.
	 */
	private function get_recipe_object( $recipe_id ) {
		return Automator()->get_recipe_object( $recipe_id, ARRAY_A );
	}

	/**
	 * Add dynamic tokens from triggers and actions to the token categories.
	 *
	 * @since 7.0.0
	 * @param array $token_categories The current token categories array.
	 * @param array $recipe_object    The recipe object containing triggers and actions.
	 * @return array Updated token categories with dynamic tokens added.
	 */
	private function add_dynamic_tokens( $token_categories, $recipe_object ) {
		$triggers = $this->extract_recipe_items( $recipe_object, 'triggers' );
		$actions  = $this->extract_recipe_items( $recipe_object, 'actions' );

		$token_categories['trigger-tokens'] = $this->process_trigger_tokens( $triggers );
		$token_categories['action-tokens']  = $this->process_action_tokens( $actions );

		return $token_categories;
	}

	/**
	 * Extract items (triggers or actions) from the recipe object.
	 *
	 * @since 7.0.0
	 * @param array  $recipe_object The recipe object.
	 * @param string $item_type     The type of items to extract ('triggers' or 'actions').
	 * @return array Extracted items or empty array if none found.
	 */
	private function extract_recipe_items( $recipe_object, $item_type ) {
		if ( ! isset( $recipe_object[ $item_type ]['items'] ) ) {
			return array();
		}

		return (array) $recipe_object[ $item_type ]['items'];
	}

	/**
	 * Process trigger items and extract their tokens.
	 *
	 * @since 7.0.0
	 * @param array $triggers Array of trigger items from the recipe.
	 * @return array Processed trigger tokens organized by trigger code.
	 */
	private function process_trigger_tokens( $triggers ) {
		$processed_tokens = array(
			'description' => 'Tokens from recipe triggers (only available after adding triggers)',
		);

		foreach ( $triggers as $trigger ) {
			$tokens = $this->extract_tokens_from_item( $trigger, 'trigger' );
			if ( ! empty( $tokens ) ) {
				$processed_tokens = array_merge( $processed_tokens, $tokens );
			}
		}

		return $processed_tokens;
	}

	/**
	 * Process action items and extract their tokens.
	 *
	 * @since 7.0.0
	 * @param array $actions Array of action items from the recipe.
	 * @return array Processed action tokens organized by action code.
	 */
	private function process_action_tokens( $actions ) {
		$processed_tokens = array(
			'description' => 'Tokens from recipe actions (only available after adding actions)',
		);

		foreach ( $actions as $action ) {
			$tokens = $this->extract_tokens_from_item( $action, 'action' );
			if ( ! empty( $tokens ) ) {
				$processed_tokens = array_merge( $processed_tokens, $tokens );
			}
		}

		return $processed_tokens;
	}

	/**
	 * Extract tokens from a single trigger or action item.
	 *
	 * @since 7.0.0
	 * @param array  $item      The trigger or action item.
	 * @param string $item_type The type of item ('trigger' or 'action').
	 * @return array Extracted tokens organized by item code.
	 */
	private function extract_tokens_from_item( $item, $item_type ) {
		$item_id     = isset( $item['id'] ) ? $item['id'] : 0;
		$item_tokens = isset( $item['tokens'] ) ? $item['tokens'] : array();
		$item_code   = isset( $item['code'] ) ? $item['code'] : '';

		if ( empty( $item_code ) ) {
			return array();
		}

		$formatted_tokens = array();

		foreach ( $item_tokens as $token ) {
			$formatted_tokens[ $item_code ][] = $this->format_token_data( $token, $item_type, $item_id );
		}

		return $formatted_tokens;
	}

	/**
	 * Format individual token data for consistent output.
	 *
	 * @since 7.0.0
	 * @param array  $token     The raw token data.
	 * @param string $item_type The type of item ('trigger' or 'action').
	 * @param int    $item_id   The ID of the parent item.
	 * @return array Formatted token data with name, usage, and description.
	 */
	private function format_token_data( $token, $item_type, $item_id ) {
		return array(
			'name'        => $token['name'],
			'usage'       => '{{' . $token['id'] . '}}',
			'description' => sprintf( 'Available %s token from this %s with ID %d.', $item_type, $item_type, $item_id ),
		);
	}

	/**
	 * Get Universal Tokens available on this site.
	 *
	 * Uses the integration token registry to enumerate all registered
	 * Universal Tokens with their template metadata for AI construction.
	 *
	 * @since 7.0.0
	 * @return array Array of Universal Token definitions.
	 */
	private function get_advanced_tokens() {
		$universal_tokens = array();

		try {
			$registry   = new WP_Integration_Token_Registry();
			$all_tokens = $registry->get_available_tokens();

			foreach ( $all_tokens as $token_id => $token ) {
				// Only include Universal Tokens (ID starts with 'UT:')
				if ( strpos( $token_id, 'UT:' ) !== 0 ) {
					continue;
				}

				$universal_tokens[] = $this->format_universal_token( $token );
			}
		} catch ( \Exception $e ) {
			// Fail gracefully - return empty array if registry fails
			return array();
		}

		return $universal_tokens;
	}

	/**
	 * Format a Universal Token for AI consumption.
	 *
	 * @since 7.0.0
	 * @param array $token The raw token data from registry.
	 * @return array Formatted token for MCP response.
	 */
	private function format_universal_token( $token ) {
		$has_template = ! empty( $token['idTemplate'] );

		// Build usage pattern showing the template
		$base_id = $token['id'] ?? '';
		$usage   = '{{' . $base_id . '}}';

		if ( $has_template ) {
			$usage = '{{' . $base_id . ':' . $token['idTemplate'] . '}}';
		}

		$result = array(
			'name'         => $token['name'] ?? '',
			'usage'        => $usage,
			'description'  => $this->build_universal_token_description( $token ),
			'requiresUser' => $token['requiresUser'] ?? false,
		);

		// Add template metadata if this is a parameterized token
		if ( $has_template ) {
			$result['idTemplate']   = $token['idTemplate'];
			$result['nameTemplate'] = $token['nameTemplate'] ?? '';
			$result['fields']       = $this->format_fields_for_ai( $token['fields'] ?? array() );
		}

		return $result;
	}

	/**
	 * Build a comprehensive description for Universal Token.
	 *
	 * @since 7.0.0
	 * @param array $token Token data.
	 * @return string Description for AI guidance.
	 */
	private function build_universal_token_description( $token ) {
		$has_template = ! empty( $token['idTemplate'] );
		$desc         = 'Universal Token. ';

		if ( $has_template ) {
			// Explain parameterized usage
			$params     = explode( ':', $token['idTemplate'] );
			$param_list = implode( ', ', array_map( 'strtolower', $params ) );

			$desc .= sprintf(
				'Construct by replacing %s in the usage pattern with actual values. ',
				$param_list
			);

			// Add field-specific guidance
			$fields = $token['fields'] ?? array();
			foreach ( $fields as $field ) {
				$field_code = $field['option_code'] ?? '';
				$field_desc = $field['description'] ?? $field['label'] ?? 'Required parameter';
				// Truncate long descriptions
				if ( strlen( $field_desc ) > 100 ) {
					$field_desc = substr( $field_desc, 0, 100 ) . '...';
				}
				$desc .= sprintf( '%s: %s ', $field_code, $field_desc );
			}
		} else {
			$desc .= 'Use as-is without additional parameters.';
		}

		if ( ! empty( $token['requiresUser'] ) ) {
			$desc .= 'Requires user context.';
		}

		return trim( $desc );
	}

	/**
	 * Format fields array for AI consumption.
	 *
	 * Simplifies field definitions to essential information for AI.
	 *
	 * @since 7.0.0
	 * @param array $fields Raw fields from token.
	 * @return array Simplified fields for AI.
	 */
	private function format_fields_for_ai( $fields ) {
		$formatted = array();

		foreach ( $fields as $field ) {
			$field_data = array(
				'code'        => $field['option_code'] ?? '',
				'label'       => $field['label'] ?? '',
				'required'    => $field['required'] ?? true,
			);

			// Include description if available
			if ( ! empty( $field['description'] ) ) {
				$field_data['description'] = $field['description'];
			}

			// Indicate if tokens can be nested in this field
			if ( ! empty( $field['supports_tokens'] ) ) {
				$field_data['supportsTokens'] = true;
			}

			$formatted[] = $field_data;
		}

		return $formatted;
	}

	/**
	 * Get common system tokens available in all recipes.
	 *
	 * @since 7.0.0
	 * @return array Array of common token definitions.
	 */
	private function get_common_tokens() {
		$tokens = array();

		$tokens[] = $this->create_token( 'Admin email', '{{admin_email}}', 'The site administrator email.' );
		$tokens[] = $this->create_token( 'Recipe ID', '{{recipe_id}}', 'The ID of the recipe.' );
		$tokens[] = $this->create_token( 'Recipe name', '{{recipe_name}}', 'The name of the recipe.' );
		$tokens[] = $this->create_token( 'Reset password link', '{{reset_pass_link}}', 'The reset password link.' );
		$tokens[] = $this->create_token( 'Site name', '{{site_name}}', 'The name of the site.' );
		$tokens[] = $this->create_token( 'Site tagline', '{{site_tagline}}', 'The tagline of the site.' );
		$tokens[] = $this->create_token( 'Site URL', '{{site_url}}', 'The URL of the site.' );

		return array_merge( $tokens, $this->get_user_tokens() );
	}

	/**
	 * Get user-related common tokens.
	 *
	 * @since 7.0.0
	 * @return array Array of user token definitions.
	 */
	private function get_user_tokens() {
		$tokens = array();

		$tokens[] = $this->create_token( 'User display name', '{{user_displayname}}', 'The display name of the user who fired the trigger or the user selected in user-selector.' );
		$tokens[] = $this->create_token( 'User email', '{{user_email}}', 'The email of the user who fired the trigger or the user selected in user-selector.' );
		$tokens[] = $this->create_token( 'User first name', '{{user_firstname}}', 'The first name of the user who fired the trigger or the user selected in user-selector.' );
		$tokens[] = $this->create_token( 'User ID', '{{user_id}}', 'The ID of the user who fired the trigger or the user selected in user-selector.' );
		$tokens[] = $this->create_token( 'User IP address', '{{user_ip_address}}', 'The IP address of the user who fired the trigger or the user selected in user-selector.' );
		$tokens[] = $this->create_token( 'User last name', '{{user_lastname}}', 'The last name of the user who fired the trigger or the user selected in user-selector.' );
		$tokens[] = $this->create_token( 'User locale', '{{user_locale}}', 'The locale of the user who fired the trigger or the user selected in user-selector.' );
		$tokens[] = $this->create_token( 'User reset password URL', '{{user_reset_pass_url}}', 'The reset password URL of the user who fired the trigger or the user selected in user-selector.' );
		$tokens[] = $this->create_token( 'User role', '{{user_role}}', 'The role of the user who fired the trigger or the user selected in user-selector.' );
		$tokens[] = $this->create_token( 'User username', '{{user_username}}', 'The username of the user who fired the trigger or the user selected in user-selector.' );
		$tokens[] = $this->create_token( 'User registration date', '{{user_registered}}', 'The registration date of the user who fired the trigger or the user selected in user-selector.' );

		return $tokens;
	}

	/**
	 * Get date and time tokens available in all recipes.
	 *
	 * @since 7.0.0
	 * @return array Array of date/time token definitions.
	 */
	private function get_date_and_time_tokens() {
		$tokens = array();

		$tokens[] = $this->create_token( 'Current date and time', '{{current_date_and_time}}', 'The current date and time.' );
		$tokens[] = $this->create_token( 'Current Unix timestamp', '{{current_unix_timestamp}}', 'The current Unix timestamp.' );
		$tokens[] = $this->create_token( 'Current date', '{{current_date}}', 'The current date.' );
		$tokens[] = $this->create_token( 'Current time', '{{current_time}}', 'The current time.' );
		$tokens[] = $this->create_token( 'Current timestamp', '{{current_timestamp}}', 'The current timestamp.' );
		$tokens[] = $this->create_token( 'Current month', '{{current_month}}', 'The current month.' );
		$tokens[] = $this->create_token( 'Current month (numeric)', '{{current_month_numeric}}', 'The current month (numeric).' );
		$tokens[] = $this->create_token( 'Current month (numeric leading zero)', '{{current_month_numeric_leading_zero}}', 'The current month (numeric leading zero).' );
		$tokens[] = $this->create_token( 'Current day of the month', '{{current_day_of_month}}', 'The current day of the month.' );
		$tokens[] = $this->create_token( 'Current day of the week', '{{current_day_of_week}}', 'The current day of the week.' );

		return $tokens;
	}

	/**
	 * Create a standardized token definition array.
	 *
	 * @since 7.0.0
	 * @param string $name        The human-readable token name.
	 * @param string $usage       The token usage syntax with curly braces.
	 * @param string $description The token description for AI guidance.
	 * @return array Standardized token definition.
	 */
	private function create_token( $name, $usage, $description ) {
		return array(
			'name'        => $name,
			'usage'       => $usage,
			'description' => $description,
		);
	}
}
