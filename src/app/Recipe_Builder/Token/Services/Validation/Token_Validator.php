<?php
/**
 * Token Validator Service
 *
 * Validates that tokens used in recipe components (actions, conditions, loops)
 * are actually available in the recipe context.
 *
 * @package Uncanny_Automator\App\Recipe_Builder\Token\Services\Validation
 * @since 7.0.0
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Recipe_Builder\Token\Services\Validation;

use Uncanny_Automator\App\Integration_Catalog\Integration\Enums\Integration_Item_Types;
use Uncanny_Automator\App\Recipe_Builder\Block\Enums\Block_Type;
use Uncanny_Automator\App\Recipe_Builder\Block\Migration\Legacy_Storage_Migration_Exception;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Wire_Serializer\Enums\Wire_Key;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Wire_Serializer\Enums\Wire_Node_Type;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Wire_Serializer\Flow_Walker;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Wire_Serializer\Recipe_Wire_Service;
use Uncanny_Automator\App\Recipe_Builder\Token\Integration\Registry\WP_Integration_Token_Registry;

/**
 * Token validation service.
 *
 * Extracts tokens from field values and validates them against
 * the recipe's available tokens (source of truth).
 *
 * Reads the recipe through `Recipe_Wire_Service`; the valid-token set is every token a node on the wire publishes.
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
	 * Cache-first wire read of the recipe tree.
	 *
	 * @var Recipe_Wire_Service
	 */
	private Recipe_Wire_Service $wire;

	/**
	 * Lazy-default DI: production reads the shared wire service; tests inject one over a fixture tree.
	 *
	 * @param Recipe_Wire_Service|null $wire Wire read seam.
	 */
	public function __construct( ?Recipe_Wire_Service $wire = null ) {
		$this->wire = $wire ?? Recipe_Wire_Service::instance();
	}

	/**
	 * Validate tokens in fields against recipe's available tokens.
	 *
	 * @param int   $recipe_id Recipe ID to validate against.
	 * @param array $fields    Field values that may contain tokens.
	 *
	 * @return array{valid: bool, invalid_tokens: array, message: string}
	 */
	public function validate( int $recipe_id, array $fields ): array {
		// Extract tokens from fields
		$used_tokens = $this->extract_tokens_from_fields( $fields );

		if ( empty( $used_tokens ) ) {
			return array(
				'valid'          => true,
				'invalid_tokens' => array(),
				'message'        => '',
			);
		}

		// Get valid tokens for this recipe
		$recipe       = $this->collect_recipe_tokens( $recipe_id );
		$valid_tokens = $this->get_valid_tokens( $recipe );

		// Find invalid tokens
		$invalid_tokens = $this->find_invalid_tokens( $used_tokens, $valid_tokens );

		if ( empty( $invalid_tokens ) ) {
			return array(
				'valid'          => true,
				'invalid_tokens' => array(),
				'message'        => '',
			);
		}

		// A token the static sets cannot vouch for, on a recipe that could not be
		// read, is unproven rather than nonexistent: report the read failure.
		$message = '' !== $recipe['read_error']
			? $recipe['read_error']
			: $this->build_error_message( $invalid_tokens, $valid_tokens );

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
	public function get_token_diagnostics( int $recipe_id ): array {
		$common_tokens   = $this->get_common_tokens();
		$registry_tokens = $this->get_registry_tokens();
		$recipe          = $this->collect_recipe_tokens( $recipe_id );

		$trigger_tokens = array_values( array_unique( $recipe['trigger'] ) );
		$action_tokens  = array_values( array_unique( $recipe['action'] ) );
		$loop_tokens    = array_values( array_unique( $recipe['loop'] ) );
		$has_recipe     = $recipe['has_recipe'];

		return array(
			'recipe_id'             => $recipe_id,
			'common_count'          => count( $common_tokens ),
			'registry_count'        => count( $registry_tokens ),
			'trigger_count'         => count( $trigger_tokens ),
			'action_count'          => count( $action_tokens ),
			'loop_count'            => count( $loop_tokens ),
			'total_count'           => count( array_unique( array_merge( $common_tokens, $registry_tokens, $trigger_tokens, $action_tokens, $loop_tokens ) ) ),
			'sample_loop_tokens'    => array_slice( $loop_tokens, 0, 10 ),
			'sample_trigger_tokens' => array_slice( $trigger_tokens, 0, 10 ),
			'has_recipe_object'     => $has_recipe,
		);
	}

	/**
	 * Extract token patterns from field values.
	 *
	 * @param array $fields Field values (can be nested).
	 *
	 * @return array List of unique token patterns found.
	 */
	public function extract_tokens_from_fields( array $fields ): array {
		$tokens = array();

		foreach ( $fields as $value ) {
			if ( is_string( $value ) ) {
				$triple = $this->extract_triple_brace_tokens( $value );
				if ( ! empty( $triple ) ) {
					$tokens = array_merge( $tokens, $triple );
					continue;
				}
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
					$tokens = array_merge( $tokens, $this->extract_tokens_from_fields( $value ) );
				}
			}
		}

		return array_unique( $tokens );
	}

	/**
	 * Extract triple-brace tokens from a string (e.g. {{{user_email}}}).
	 *
	 * These are always invalid — the extra brace produces garbage at runtime.
	 * Returns matched patterns so they surface in validation errors.
	 *
	 * @param string $value Field value to check.
	 *
	 * @return array Matched triple-brace patterns, empty if none.
	 */
	private function extract_triple_brace_tokens( string $value ): array {

		preg_match_all( '/\{\{\{[^{}]+\}\}\}/', $value, $matches );

		return $matches[0] ?? array();
	}

	/**
	 * Get valid tokens for a recipe.
	 *
	 * Collects tokens from:
	 * - Common/static tokens (user_email, site_name, etc.)
	 * - Universal Tokens from integration registry
	 * - Trigger tokens from recipe triggers
	 * - Trigger, action and loop-block tokens from the wire flow at any depth
	 *
	 * @param array{has_recipe: bool, read_error: string, trigger: string[], action: string[], loop: string[]} $recipe What collect_recipe_tokens() returned.
	 *
	 * @return array List of valid token patterns.
	 */
	private function get_valid_tokens( array $recipe ): array {
		// Common tokens are always available.
		$valid_tokens = $this->get_common_tokens();

		// Add Universal Tokens and Loopable Tokens from registry.
		$valid_tokens = array_merge( $valid_tokens, $this->get_registry_tokens() );

		return array_unique( array_merge( $valid_tokens, $recipe['trigger'], $recipe['action'], $recipe['loop'] ) );
	}

	/**
	 * Every token the recipe's nodes publish, split by node kind.
	 *
	 * Triggers, items at any depth and loop blocks each publish `tokens[]` on
	 * the wire; filter and delay blocks never do. A failed read (missing
	 * recipe, failed legacy migration) yields no recipe tokens and the caller
	 * validates against the common and registry sets alone.
	 *
	 * @param int $recipe_id Recipe ID.
	 *
	 * @return array{has_recipe: bool, read_error: string, trigger: string[], action: string[], loop: string[]} read_error is the agent-facing sentence when the tree could not be read, else ''.
	 */
	private function collect_recipe_tokens( int $recipe_id ): array {
		$out = array(
			'has_recipe' => false,
			'read_error' => '',
			'trigger'    => array(),
			'action'     => array(),
			'loop'       => array(),
		);

		try {
			$wire = $this->wire->get_wire_array( $recipe_id );
		} catch ( Legacy_Storage_Migration_Exception $e ) {
			automator_log( sprintf( 'Failed to read recipe %d; validating against common and registry tokens only: %s', $recipe_id, $e->getMessage() ), 'Token_Validator' );
			$out['read_error'] = $e->user_message( $recipe_id );
			return $out;
		} catch ( \Exception $e ) {
			automator_log( sprintf( 'Failed to read recipe %d; validating against common and registry tokens only: %s', $recipe_id, $e->getMessage() ), 'Token_Validator' );
			$out['read_error'] = sprintf( 'Recipe %d could not be read (%s).', $recipe_id, $e->getMessage() );
			return $out;
		}

		if ( null === $wire ) {
			return $out;
		}

		$out['has_recipe'] = true;
		$flow              = is_array( $wire[ Wire_Key::FLOW ] ?? null ) ? $wire[ Wire_Key::FLOW ] : array();

		foreach ( Flow_Walker::nodes( $flow ) as $entry ) {
			$node = $entry['node'];
			$type = $node[ Wire_Key::TYPE ] ?? '';

			if ( Wire_Node_Type::ITEM === $type ) {
				$bucket = Integration_Item_Types::TRIGGER === ( $node[ Wire_Key::ITEM_TYPE ] ?? '' ) ? 'trigger' : 'action';
			} elseif ( Wire_Node_Type::BLOCK === $type && Block_Type::LOOP === ( $node[ Wire_Key::BLOCK_TYPE ] ?? '' ) ) {
				$bucket = 'loop';
			} else {
				continue;
			}

			$out[ $bucket ] = array_merge( $out[ $bucket ], $this->extract_tokens_from_item( $node ) );
		}

		return $out;
	}

	/**
	 * Extract tokens from a single item (trigger, action, loop, filter).
	 *
	 * @param array $item Item data with 'tokens' key.
	 *
	 * @return array List of token usage patterns.
	 */
	private function extract_tokens_from_item( array $item ): array {
		$tokens      = array();
		$item_tokens = is_array( $item[ Wire_Key::TOKENS ] ?? null ) ? $item[ Wire_Key::TOKENS ] : array();

		foreach ( $item_tokens as $token ) {
			if ( is_array( $token ) && isset( $token[ Wire_Key::ID ] ) && '' !== (string) $token[ Wire_Key::ID ] ) {
				$tokens[] = '{{' . $token[ Wire_Key::ID ] . '}}';
			}
		}

		return $tokens;
	}

	/**
	 * Get common/static tokens available in all recipes.
	 *
	 * @return array List of common token usage patterns.
	 */
	private function get_common_tokens(): array {
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
	private function get_registry_tokens(): array {
		$tokens = array();

		try {
			$registry   = new WP_Integration_Token_Registry();
			$all_tokens = $registry->get_available_tokens();

			foreach ( $all_tokens as $token_id => $token_data ) {
				$token_type = $token_data['type'] ?? '';

				// Include Universal Tokens (UT:) and Loopable Tokens (TOKEN_EXTENDED:).
				if ( strpos( $token_id, 'UT:' ) === 0 || 'loopable' === $token_type ) {
					$tokens[] = '{{' . $token_id . '}}';
				}
			}
		} catch ( \Exception $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
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
	private function find_invalid_tokens( array $used_tokens, array $valid_tokens ): array {
		$invalid = array();

		// Separate token types for different matching strategies.
		$ut_bases = array();  // Universal Tokens use prefix matching.
		$regular  = array();  // Regular and loopable tokens use exact matching.

		foreach ( $valid_tokens as $token ) {
			if ( strpos( $token, '{{UT:' ) === 0 ) {
				// Extract base pattern: {{UT:INTEGRATION:CODE}} from {{UT:INTEGRATION:CODE:...}}
				$base = $this->get_universal_token_base( $token );
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
				$base = $this->get_universal_token_base( $token );
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
	private function get_universal_token_base( string $token ): ?string {
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
	private function build_error_message( array $invalid_tokens, array $valid_tokens ): string {
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
			'Invalid tokens found: %s. These tokens do not exist in this recipe. Use get_tokens tool to see all available tokens. Available tokens: %s',
			$invalid_list,
			$valid_list
		);

		return $message;
	}
}
