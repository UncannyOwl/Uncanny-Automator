<?php
/**
 * Token Validator Service
 *
 * Validates that tokens used in recipe components (actions, conditions, loops)
 * are actually available in the recipe context.
 *
 * @package Uncanny_Automator\Api\Services\Token\Validation
 * @since 7.0.0
 */

declare(strict_types=1);

namespace Uncanny_Automator\Api\Services\Token\Validation;

/**
 * Token validation service.
 *
 * Extracts tokens from field values and validates them against
 * the recipe's available tokens (source of truth).
 *
 * @since 7.0.0
 */
class Token_Validator {

	/**
	 * Regex pattern to match token patterns like {{token_name}}.
	 * Supports nested tokens like {{UT:ADVANCED:CALCULATION:{{other_token}}+5}}.
	 */
	private const TOKEN_PATTERN = '/\{\{(?:[^{}]|\{\{[^}]*\}\})*\}\}/';

	/**
	 * Validate tokens in fields against recipe's available tokens.
	 *
	 * @param int   $recipe_id Recipe ID to validate against.
	 * @param array $fields    Field values that may contain tokens.
	 *
	 * @return array{valid: bool, invalid_tokens: array, message: string}
	 */
	public static function validate( int $recipe_id, array $fields ): array {
		// Extract tokens from fields
		$used_tokens = self::extract_tokens_from_fields( $fields );

		if ( empty( $used_tokens ) ) {
			return array(
				'valid'          => true,
				'invalid_tokens' => array(),
				'message'        => '',
			);
		}

		// Get valid tokens for this recipe
		$valid_tokens = self::get_valid_tokens( $recipe_id );

		// Find invalid tokens
		$invalid_tokens = self::find_invalid_tokens( $used_tokens, $valid_tokens );

		if ( empty( $invalid_tokens ) ) {
			return array(
				'valid'          => true,
				'invalid_tokens' => array(),
				'message'        => '',
			);
		}

		// Build error message
		$message = self::build_error_message( $invalid_tokens, $valid_tokens );

		return array(
			'valid'          => false,
			'invalid_tokens' => $invalid_tokens,
			'message'        => $message,
		);
	}

	/**
	 * Get diagnostic information about available tokens for a recipe.
	 *
	 * Useful for debugging token validation failures.
	 *
	 * @param int $recipe_id Recipe ID to analyze.
	 *
	 * @return array{
	 *     recipe_id: int,
	 *     common_count: int,
	 *     registry_count: int,
	 *     trigger_count: int,
	 *     action_count: int,
	 *     loop_count: int,
	 *     total_count: int,
	 *     sample_loop_tokens: array,
	 *     sample_trigger_tokens: array,
	 *     has_recipe_object: bool
	 * }
	 */
	public static function get_token_diagnostics( int $recipe_id ): array {
		$common_tokens   = self::get_common_tokens();
		$registry_tokens = self::get_registry_tokens();
		$trigger_tokens  = array();
		$action_tokens   = array();
		$loop_tokens     = array();
		$has_recipe      = false;

		if ( function_exists( 'Automator' ) ) {
			try {
				$recipe_object = Automator()->get_recipe_object( $recipe_id, ARRAY_A );

				if ( ! empty( $recipe_object ) && is_array( $recipe_object ) ) {
					$has_recipe = true;

					// Extract trigger tokens.
					$triggers = $recipe_object['triggers']['items'] ?? array();
					if ( is_array( $triggers ) ) {
						foreach ( $triggers as $trigger ) {
							if ( is_array( $trigger ) ) {
								$trigger_tokens = array_merge( $trigger_tokens, self::extract_tokens_from_item( $trigger ) );
							}
						}
					}

					// Extract action/loop tokens.
					$actions = $recipe_object['actions']['items'] ?? array();
					if ( is_array( $actions ) ) {
						foreach ( $actions as $item ) {
							if ( ! is_array( $item ) ) {
								continue;
							}

							$item_type = $item['type'] ?? '';

							if ( 'loop' === $item_type ) {
								// Loop tokens (LOOP_TOKEN, DATA_TOKEN_CHILDREN).
								$loop_tokens = array_merge( $loop_tokens, self::extract_tokens_from_item( $item ) );
								// Also get tokens from items inside the loop.
								if ( ! empty( $item['items'] ) && is_array( $item['items'] ) ) {
									$action_tokens = array_merge( $action_tokens, self::extract_tokens_from_items_recursive( $item['items'] ) );
								}
							} else {
								$action_tokens = array_merge( $action_tokens, self::extract_tokens_from_item( $item ) );
							}
						}
					}
				}
			} catch ( \Exception ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
				// Diagnostics should not throw.
			}
		}

		$loop_tokens    = array_unique( $loop_tokens );
		$trigger_tokens = array_unique( $trigger_tokens );
		$action_tokens  = array_unique( $action_tokens );

		return array(
			'recipe_id'            => $recipe_id,
			'common_count'         => count( $common_tokens ),
			'registry_count'       => count( $registry_tokens ),
			'trigger_count'        => count( $trigger_tokens ),
			'action_count'         => count( $action_tokens ),
			'loop_count'           => count( $loop_tokens ),
			'total_count'          => count( array_unique( array_merge( $common_tokens, $registry_tokens, $trigger_tokens, $action_tokens, $loop_tokens ) ) ),
			'sample_loop_tokens'   => array_slice( $loop_tokens, 0, 10 ),
			'sample_trigger_tokens' => array_slice( $trigger_tokens, 0, 10 ),
			'has_recipe_object'    => $has_recipe,
		);
	}

	/**
	 * Extract token patterns from field values.
	 *
	 * @param array $fields Field values (can be nested).
	 *
	 * @return array List of unique token patterns found.
	 */
	public static function extract_tokens_from_fields( array $fields ): array {
		$tokens = array();

		foreach ( $fields as $value ) {
			if ( is_string( $value ) ) {
				preg_match_all( self::TOKEN_PATTERN, $value, $matches );
				if ( ! empty( $matches[0] ) ) {
					$tokens = array_merge( $tokens, $matches[0] );
				}
			} elseif ( is_array( $value ) ) {
				// Handle nested field structures (e.g., field with 'value' key)
				if ( isset( $value['value'] ) && is_string( $value['value'] ) ) {
					preg_match_all( self::TOKEN_PATTERN, $value['value'], $matches );
					if ( ! empty( $matches[0] ) ) {
						$tokens = array_merge( $tokens, $matches[0] );
					}
				} else {
					// Recursively extract from nested arrays
					$tokens = array_merge( $tokens, self::extract_tokens_from_fields( $value ) );
				}
			}
		}

		return array_unique( $tokens );
	}

	/**
	 * Get valid tokens for a recipe.
	 *
	 * Collects tokens from:
	 * - Common/static tokens (user_email, site_name, etc.)
	 * - Universal Tokens from integration registry
	 * - Trigger tokens from recipe triggers
	 * - Action tokens from recipe actions (including loops)
	 *
	 * @param int $recipe_id Recipe ID.
	 *
	 * @return array List of valid token patterns.
	 */
	private static function get_valid_tokens( int $recipe_id ): array {
		// Common tokens are always available, even without Automator.
		$valid_tokens = self::get_common_tokens();

		if ( ! function_exists( 'Automator' ) ) {
			return $valid_tokens;
		}

		// 2. Add Universal Tokens and Loopable Tokens from registry.
		$valid_tokens = array_merge( $valid_tokens, self::get_registry_tokens() );

		// 3. Add trigger and action tokens from recipe.
		try {
			$recipe_object = Automator()->get_recipe_object( $recipe_id, ARRAY_A );

			if ( ! empty( $recipe_object ) && is_array( $recipe_object ) ) {
				// Extract trigger tokens.
				$triggers = $recipe_object['triggers']['items'] ?? array();
				if ( is_array( $triggers ) ) {
					foreach ( $triggers as $trigger ) {
						if ( is_array( $trigger ) ) {
							$valid_tokens = array_merge( $valid_tokens, self::extract_tokens_from_item( $trigger ) );
						}
					}
				}

				// Extract action/loop tokens recursively.
				$actions = $recipe_object['actions']['items'] ?? array();
				if ( is_array( $actions ) ) {
					$valid_tokens = array_merge( $valid_tokens, self::extract_tokens_from_items_recursive( $actions ) );
				}
			}
		} catch ( \Exception ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
			// Log error if WP_DEBUG is enabled, but continue with already collected tokens.
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( sprintf( '[Token_Validator] Failed to fetch recipe object for recipe %d', $recipe_id ) );
			}
		}

		return array_unique( $valid_tokens );
	}

	/**
	 * Extract tokens from a single item (trigger, action, loop, filter).
	 *
	 * @param array $item Item data with 'tokens' key.
	 *
	 * @return array List of token usage patterns.
	 */
	private static function extract_tokens_from_item( array $item ): array {
		$tokens = array();
		$item_tokens = $item['tokens'] ?? array();

		foreach ( $item_tokens as $token ) {
			if ( isset( $token['id'] ) ) {
				$tokens[] = '{{' . $token['id'] . '}}';
			}
		}

		return $tokens;
	}

	/**
	 * Recursively extract tokens from nested items.
	 *
	 * Handles the recursive structure:
	 * - actions.items[].tokens (actions, loops)
	 * - actions.items[].iterable_expression.fields (loopable token for token-type loops)
	 * - actions.items[].items[].tokens (items inside loops)
	 * - actions.items[].items[].conditions[].tokens (filter conditions)
	 * - actions.items[].items[].items[].tokens (items inside filters)
	 *
	 * @param array $items Array of items to extract tokens from.
	 *
	 * @return array List of token usage patterns.
	 */
	private static function extract_tokens_from_items_recursive( array $items ): array {
		$tokens = array();

		foreach ( $items as $item ) {
			// Extract tokens from this item's tokens array.
			$tokens = array_merge( $tokens, self::extract_tokens_from_item( $item ) );

			// For loops, also extract the loopable token from iterable_expression.
			if ( ( $item['type'] ?? '' ) === 'loop' ) {
				$tokens = array_merge( $tokens, self::extract_loopable_token_from_loop( $item ) );
			}

			// Recursively extract from nested 'items' (loops, filters).
			if ( ! empty( $item['items'] ) && is_array( $item['items'] ) ) {
				$tokens = array_merge( $tokens, self::extract_tokens_from_items_recursive( $item['items'] ) );
			}

			// Recursively extract from 'conditions' in filters.
			if ( ! empty( $item['conditions'] ) && is_array( $item['conditions'] ) ) {
				$tokens = array_merge( $tokens, self::extract_tokens_from_items_recursive( $item['conditions'] ) );
			}
		}

		return $tokens;
	}

	/**
	 * Extract loopable token from a loop's iterable_expression.
	 *
	 * For token-type loops, the loopable token (e.g., TOKEN_EXTENDED:DATA_TOKEN_ALL_ORDERS_YEARLY:...)
	 * is stored in iterable_expression.fields.TOKEN.value. This token is valid for use in actions.
	 *
	 * @param array $loop Loop item data.
	 *
	 * @return array List of loopable token patterns found.
	 */
	private static function extract_loopable_token_from_loop( array $loop ): array {
		$tokens = array();

		$iterable_expression = $loop['iterable_expression'] ?? array();

		// Only token-type loops have a loopable token.
		if ( ( $iterable_expression['type'] ?? '' ) !== 'token' ) {
			return $tokens;
		}

		// Fields can be a JSON string or array.
		$fields = $iterable_expression['fields'] ?? '';

		if ( is_string( $fields ) ) {
			$fields = json_decode( $fields, true );
			if ( ! is_array( $fields ) ) {
				return $tokens;
			}
		}

		// Extract the TOKEN field value which contains the loopable token.
		$token_field = $fields['TOKEN'] ?? array();
		$token_value = '';

		if ( is_array( $token_field ) && isset( $token_field['value'] ) ) {
			$token_value = $token_field['value'];
		} elseif ( is_string( $token_field ) ) {
			$token_value = $token_field;
		}

		// Extract the token pattern from the value (e.g., {{TOKEN_EXTENDED:...}}).
		if ( ! empty( $token_value ) && preg_match( self::TOKEN_PATTERN, $token_value, $matches ) ) {
			$tokens[] = $matches[0];
		}

		return $tokens;
	}

	/**
	 * Get common/static tokens available in all recipes.
	 *
	 * @return array List of common token usage patterns.
	 */
	private static function get_common_tokens(): array {
		return array(
			// Site tokens.
			'{{admin_email}}',
			'{{recipe_id}}',
			'{{recipe_name}}',
			'{{reset_pass_link}}',
			'{{site_name}}',
			'{{site_tagline}}',
			'{{site_url}}',
			// User tokens.
			'{{user_displayname}}',
			'{{user_email}}',
			'{{user_firstname}}',
			'{{user_id}}',
			'{{user_ip_address}}',
			'{{user_lastname}}',
			'{{user_locale}}',
			'{{user_reset_pass_url}}',
			'{{user_role}}',
			'{{user_username}}',
			'{{user_registered}}',
			// Date/time tokens.
			'{{current_date_and_time}}',
			'{{current_unix_timestamp}}',
			'{{current_date}}',
			'{{current_time}}',
			'{{current_timestamp}}',
			'{{current_month}}',
			'{{current_month_numeric}}',
			'{{current_month_numeric_leading_zero}}',
			'{{current_day_of_month}}',
			'{{current_day_of_week}}',
		);
	}

	/**
	 * Get tokens from integration registry (Universal Tokens + Loopable Tokens).
	 *
	 * @return array List of token patterns.
	 */
	private static function get_registry_tokens(): array {
		$tokens = array();

		try {
			$registry   = new \Uncanny_Automator\Api\Components\Token\Integration\Registry\WP_Integration_Token_Registry();
			$all_tokens = $registry->get_available_tokens();

			foreach ( $all_tokens as $token_id => $token_data ) {
				$token_type = $token_data['type'] ?? '';

				// Include Universal Tokens (UT:) and Loopable Tokens (TOKEN_EXTENDED:).
				if ( strpos( $token_id, 'UT:' ) === 0 || $token_type === 'loopable' ) {
					$tokens[] = '{{' . $token_id . '}}';
				}
			}
		} catch ( \Exception ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
			// Fail gracefully - return empty if registry fails.
			return array();
		}

		return $tokens;
	}

	/**
	 * Find tokens that are not in the valid set.
	 *
	 * Handles:
	 * - Universal Tokens (UT:) - prefix matching
	 * - Loop tokens (TOKEN_EXTENDED:LOOP_TOKEN:) - exact matching
	 * - Loopable Tokens (TOKEN_EXTENDED:DATA_TOKEN:) - exact matching
	 * - Regular tokens - exact matching
	 *
	 * @param array $used_tokens  Tokens used in fields.
	 * @param array $valid_tokens Valid tokens from recipe.
	 *
	 * @return array Invalid tokens.
	 */
	private static function find_invalid_tokens( array $used_tokens, array $valid_tokens ): array {
		$invalid = array();

		// Separate token types for different matching strategies.
		$ut_bases = array();  // Universal Tokens use prefix matching.
		$regular  = array();  // Regular and loopable tokens use exact matching.

		foreach ( $valid_tokens as $token ) {
			if ( strpos( $token, '{{UT:' ) === 0 ) {
				// Extract base pattern: {{UT:INTEGRATION:CODE}} from {{UT:INTEGRATION:CODE:...}}
				$base = self::get_universal_token_base( $token );
				if ( $base ) {
					$ut_bases[ $base ] = true;
				}
			} else {
				// Regular tokens, loopable tokens (TOKEN_EXTENDED:), etc.
				$regular[ $token ] = true;
			}
		}

		foreach ( $used_tokens as $token ) {
			if ( strpos( $token, '{{UT:' ) === 0 ) {
				// Universal token - use prefix matching.
				$base = self::get_universal_token_base( $token );
				if ( ! $base || ! isset( $ut_bases[ $base ] ) ) {
					$invalid[] = $token;
				}
			} else {
				// Regular and loopable tokens - use exact matching.
				if ( ! isset( $regular[ $token ] ) ) {
					$invalid[] = $token;
				}
			}
		}

		return $invalid;
	}

	/**
	 * Extract base pattern from a Universal Token.
	 *
	 * Universal tokens: {{UT:{Integration}:{TokenCode}:{DynamicParts}}}
	 * Base pattern: {{UT:{Integration}:{TokenCode}}}
	 *
	 * @param string $token Full universal token.
	 *
	 * @return string|null Base pattern or null if invalid.
	 */
	private static function get_universal_token_base( string $token ): ?string {
		if ( preg_match( '/^\{\{(UT:[^:]+:[^:}]+)/', $token, $matches ) ) {
			return '{{' . $matches[1] . '}}';
		}
		return null;
	}

	/**
	 * Build user-friendly error message with prioritized token suggestions.
	 *
	 * Prioritizes showing relevant tokens (loop, trigger, action) over common tokens
	 * to help AI agents recover from validation errors.
	 *
	 * @param array $invalid_tokens Invalid tokens found.
	 * @param array $valid_tokens   Valid tokens available.
	 *
	 * @return string Error message.
	 */
	private static function build_error_message( array $invalid_tokens, array $valid_tokens ): string {
		$invalid_list = implode( ', ', $invalid_tokens );

		// Categorize valid tokens by relevance (most relevant first).
		$loop_tokens    = array();
		$trigger_tokens = array();
		$action_tokens  = array();
		$common_tokens  = array();
		$other_tokens   = array();

		foreach ( $valid_tokens as $token ) {
			if ( strpos( $token, 'TOKEN_EXTENDED:LOOP_TOKEN:' ) !== false
				|| strpos( $token, 'TOKEN_EXTENDED:DATA_TOKEN_CHILDREN' ) !== false ) {
				$loop_tokens[] = $token;
			} elseif ( preg_match( '/^\{\{\d+:/', $token ) ) {
				// Trigger tokens format: {{123:TRIGGER_CODE:FIELD}}
				$trigger_tokens[] = $token;
			} elseif ( strpos( $token, 'ACTION_FIELD:' ) !== false || strpos( $token, 'ACTION_META:' ) !== false ) {
				$action_tokens[] = $token;
			} elseif ( preg_match( '/^\{\{[a-z_]+\}\}$/', $token ) ) {
				// Common tokens: simple lowercase with underscores like {{admin_email}}.
				$common_tokens[] = $token;
			} else {
				$other_tokens[] = $token;
			}
		}

		// Build prioritized list: loop > trigger > action > other > common (common last since they're always available).
		$prioritized = array_merge( $loop_tokens, $trigger_tokens, $action_tokens, $other_tokens, $common_tokens );

		// No truncation - show all tokens so AI can find the right one.
		$valid_list = implode( ', ', $prioritized );

		$message = sprintf(
			'Invalid tokens found: %s. These tokens do not exist in this recipe. Use get_recipe_tokens tool to see all available tokens. Available tokens: %s',
			$invalid_list,
			$valid_list
		);

		return $message;
	}
}
