<?php
/**
 * User selector advisor for anonymous recipes.
 *
 * Detects when an anonymous recipe contains user-type actions or
 * user-requiring tokens but lacks a user selector, and returns
 * error/warning messages that guide the AI agent to configure one.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Recipe_Builder\User_Selector\Services;

use Uncanny_Automator\App\Bridge\Action_Definition_Bridge;
use Uncanny_Automator\App\Bridge\Automator_Action_Definition_Bridge;
use Uncanny_Automator\App\Recipe_Builder\Token\Services\Common_Token_Registry;
use Uncanny_Automator\App\Recipe_Builder\Token\Integration\Registry\WP_Integration_Token_Registry;
use Uncanny_Automator\App\Infrastructure\Database\Database;
use Uncanny_Automator\App\Recipe_Builder\Action\Enums\Action_Status;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Settings\Services\Recipe_Settings_Service;

/**
 * User_Selector_Advisor
 */
class User_Selector_Advisor {

	/**
	 * User selector explanation appended to all messages.
	 *
	 * The source values are the ones save_recipe's user_selector.source accepts
	 * (existingUser, newUser), not the stored kebab-case ones.
	 *
	 * @var string
	 */
	private const EXPLANATION = 'A user selector tells the recipe which WordPress user the actions should run for. '
		. 'Call save_recipe with the user_selector parameter to configure it. '
		. 'The unique_field_value supports any token — common ({{user_email}}), universal, or trigger tokens all work. '
		. 'Example: user_selector={"source":"existingUser","unique_field":"email","unique_field_value":"{{user_email}}","fallback":"do-nothing"}. '
		. 'Options: source="existingUser" (find by email/id/username) or source="newUser" (create user with user_data). '
		. 'For existingUser with create fallback: add "fallback":"create-new-user" and include "user_data". '
		. 'For newUser with duplicate check: add "fallback":"select-existing-user" and "prioritized_field":"email". '
		. 'After configuring, advise the user to refresh the recipe editor page to see the user selector panel.';

	/**
	 * Cached token patterns that require user context.
	 *
	 * @var string[]|null
	 */
	private $user_token_patterns = null;

	/**
	 * Every registered action definition, whichever framework registered it.
	 *
	 * @var Action_Definition_Bridge
	 */
	private Action_Definition_Bridge $action_definitions;

	/**
	 * Constructor.
	 *
	 * @since 8.0.0
	 *
	 * @param Action_Definition_Bridge|null $action_definitions Registered action definitions; defaults to Automator's registry.
	 */
	public function __construct( ?Action_Definition_Bridge $action_definitions = null ) {
		$this->action_definitions = $action_definitions ?? new Automator_Action_Definition_Bridge();
	}

	/**
	 * Check after adding an action to an anonymous recipe.
	 *
	 * Returns a warning string (non-blocking) so the action still saves,
	 * but the AI agent is told to configure a user selector next.
	 *
	 * @param int    $recipe_id   Recipe post ID.
	 * @param string $action_code Action code just added.
	 * @param array  $fields      Action field values (to scan for user tokens).
	 *
	 * @return string|null Warning message or null if no warning needed.
	 */
	public function check_after_action_add( int $recipe_id, string $action_code, array $fields = array() ): ?string {

		$recipe_type = Recipe_Settings_Service::instance()->get_recipe_type( $recipe_id );
		if ( 'anonymous' !== $recipe_type ) {
			return null;
		}

		if ( $this->recipe_has_user_selector( $recipe_id ) ) {
			return null;
		}

		$reasons = array();

		if ( $this->action_requires_user( $action_code ) ) {
			$reasons[] = sprintf( 'the action "%s" requires a logged-in user to run', $action_code );
		}

		$user_tokens = $this->find_user_tokens_in_fields( $fields );
		if ( ! empty( $user_tokens ) ) {
			$reasons[] = sprintf( 'the fields contain tokens that need a user (%s)', implode( ', ', $user_tokens ) );
		}

		if ( empty( $reasons ) ) {
			return null;
		}

		return sprintf(
			'IMPORTANT: %s, but this is an anonymous recipe with no user selector configured. '
			. 'You must configure a user selector before publishing this recipe, otherwise it will fail at runtime. '
			. '%s',
			implode( '; and ', $reasons ),
			self::EXPLANATION
		);
	}

	/**
	 * Check on recipe publish — this is a BLOCKING error.
	 *
	 * Returns an error string that should prevent publish if the recipe
	 * has user-requiring actions or tokens but no user selector.
	 *
	 * @param int $recipe_id Recipe post ID.
	 *
	 * @return string|null Error message or null if safe to publish.
	 */
	public function check_on_publish( int $recipe_id ): ?string {

		$recipe_type = Recipe_Settings_Service::instance()->get_recipe_type( $recipe_id );
		if ( 'anonymous' !== $recipe_type ) {
			return null;
		}

		if ( $this->recipe_has_user_selector( $recipe_id ) ) {
			return null;
		}

		$user_requiring_codes = $this->get_user_requiring_action_codes( $recipe_id );
		$user_tokens_found    = $this->scan_recipe_for_user_tokens( $recipe_id );

		if ( empty( $user_requiring_codes ) && empty( $user_tokens_found ) ) {
			return null;
		}

		$parts = array();

		if ( ! empty( $user_requiring_codes ) ) {
			$parts[] = sprintf(
				'%d action(s) that require a logged-in user (%s)',
				count( $user_requiring_codes ),
				implode( ', ', $user_requiring_codes )
			);
		}

		if ( ! empty( $user_tokens_found ) ) {
			$parts[] = sprintf(
				'tokens that require a user (%s)',
				implode( ', ', array_unique( $user_tokens_found ) )
			);
		}

		return sprintf(
			'Cannot publish: this anonymous recipe has %s, '
			. 'but no user selector is configured. Without a user selector, the recipe won\'t know '
			. 'which user to resolve these for and will fail at runtime. %s',
			implode( '; and ', $parts ),
			self::EXPLANATION
		);
	}

	/**
	 * Get action codes in a recipe that require user context.
	 *
	 * @param int $recipe_id Recipe post ID.
	 *
	 * @return string[] Unique action codes requiring user context.
	 */
	private function get_user_requiring_action_codes( int $recipe_id ): array {

		$codes = Database::get_action_store()->get_recipe_action_codes(
			$recipe_id,
			array( Action_Status::DRAFT, Action_Status::PUBLISH )
		);

		$user_requiring_codes = array();
		foreach ( $codes as $code ) {
			if ( ! empty( $code ) && $this->action_requires_user( $code ) ) {
				$user_requiring_codes[] = $code;
			}
		}

		return array_unique( $user_requiring_codes );
	}

	/**
	 * Scan all action field values in a recipe for user-requiring tokens.
	 *
	 * @param int $recipe_id Recipe post ID.
	 *
	 * @return string[] User tokens found across all action fields.
	 */
	private function scan_recipe_for_user_tokens( int $recipe_id ): array {

		$action_ids = $this->get_all_action_ids( $recipe_id );
		$found      = array();
		$store      = Database::get_action_store();

		foreach ( $action_ids as $action_id ) {
			$field_values = $store->get_action_field_values( $action_id );
			if ( empty( $field_values ) ) {
				continue;
			}

			$found = array_merge( $found, $this->find_user_tokens_in_fields( $field_values ) );
		}

		return array_unique( $found );
	}

	/**
	 * Get all action post IDs for a recipe (direct + inside loops).
	 *
	 * @param int $recipe_id Recipe post ID.
	 *
	 * @return int[] Action post IDs.
	 */
	private function get_all_action_ids( int $recipe_id ): array {
		return Database::get_action_store()->get_recipe_action_ids(
			$recipe_id,
			array( Action_Status::DRAFT, Action_Status::PUBLISH )
		);
	}

	/**
	 * Find user-requiring tokens in field values.
	 *
	 * Loads the recipe's available tokens (same data the frontend receives)
	 * and checks their requiresUser flag — single source of truth.
	 *
	 * @param array $fields Field key => value pairs.
	 *
	 * @return string[] User tokens found in field values (deduplicated).
	 */
	private function find_user_tokens_in_fields( array $fields ): array {

		$all_values = implode( ' ', $this->field_text( $fields ) );

		// No tokens in any field value — skip the lookup entirely.
		if ( false === strpos( $all_values, '{{' ) ) {
			return array();
		}

		$patterns = $this->get_user_token_patterns();
		$found    = array();

		foreach ( $patterns as $pattern ) {
			if ( false !== strpos( $all_values, $pattern ) ) {
				// Normalize display: ensure pattern ends with }}.
				$display = ( '}}' === substr( $pattern, -2 ) ) ? $pattern : $pattern . '...}}';
				$found[] = $display;
			}
		}

		return array_unique( $found );
	}

	/**
	 * The text of every field value, reading each repeater row's cells as values too.
	 *
	 * A repeater field arrives as an array of rows. The publish check reads the same rows
	 * saved as text, so a token in a row's cell counts at save and at publish alike.
	 *
	 * @since 8.0.0
	 *
	 * @param array $values Field key => value pairs, or one repeater row.
	 *
	 * @return string[] Each scalar value as text.
	 */
	private function field_text( array $values ): array {

		$text = array();

		foreach ( $values as $value ) {
			if ( is_array( $value ) ) {
				$text = array_merge( $text, $this->field_text( $value ) );
				continue;
			}

			if ( is_scalar( $value ) ) {
				$text[] = (string) $value;
			}
		}

		return $text;
	}

	/**
	 * Build search patterns for tokens that have requiresUser=true.
	 *
	 * Loads from the same sources the get_tokens MCP tool uses:
	 * 1. Common tokens (via Common_Token_Registry -- single source of truth)
	 * 2. Universal tokens from Integration_Token_Registry
	 *
	 * @since 7.1.0
	 *
	 * @return string[] Token substrings to search for (e.g. "{{user_email}}").
	 */
	private function get_user_token_patterns(): array {

		if ( null !== $this->user_token_patterns ) {
			return $this->user_token_patterns;
		}

		// 1. Common tokens -- delegated to Common_Token_Registry.
		$registry = new Common_Token_Registry();
		$patterns = $registry->get_user_requiring_patterns();

		// 2. Universal tokens from integration registry.
		if ( class_exists( WP_Integration_Token_Registry::class ) ) {
			try {
				$token_registry = new WP_Integration_Token_Registry();
				foreach ( $token_registry->get_available_tokens() as $token ) {
					if ( empty( $token['requiresUser'] ) ) {
						continue;
					}
					$id = $token['id'] ?? '';
					// Only process UT: prefixed tokens -- common tokens are handled above.
					if ( ! empty( $id ) && 0 === strpos( $id, 'UT:' ) ) {
						$patterns[] = '{{' . $id;
					}
				}
			} catch ( \Exception $e ) {
				// Registry unavailable -- common tokens still checked.
				unset( $e );
			}
		}

		$this->user_token_patterns = array_unique( $patterns );

		return $this->user_token_patterns;
	}

	/**
	 * Check if an action code requires user context.
	 *
	 * @param string $action_code Action code.
	 *
	 * @return bool True if action requires user.
	 */
	private function action_requires_user( string $action_code ): bool {

		// Read the registry itself: trait-based actions such as SENDEMAIL
		// register directly, never through the automator_actions filter.
		$definition = $this->action_definitions->get_action_definition( $action_code );

		return null === $definition || (bool) ( $definition['requires_user'] ?? true );
	}

	/**
	 * Check if recipe has a user selector configured.
	 *
	 * @param int $recipe_id Recipe post ID.
	 *
	 * @return bool True if configured.
	 */
	private function recipe_has_user_selector( int $recipe_id ): bool {
		return Database::get_user_selector_store()->exists_for_recipe( $recipe_id );
	}
}
