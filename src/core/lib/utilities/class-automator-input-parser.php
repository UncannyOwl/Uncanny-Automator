<?php

namespace Uncanny_Automator;

use Uncanny_Automator\Services\Loopable\Data_Integrations\Array_Group_Classifier;
use Uncanny_Automator\Traits\Singleton;
use WP_User;

/**
 * Class Automator_Input_Parser
 *
 * This class is responsible for parsing various tokens used throughout the automation processes.
 * **Always run unit tests** before making modifications to ensure stability and avoid regressions.
 *
 * @test Automator_Input_Parser
 *
 * @since 6.2
 *  - Added check to skip the replacing of double brackets causing the loopable JSON to break.
 *
 * @since 6.1
 *  - Refactored and covered with unit tests to ensure long-term maintainability and reliability.
 *  - Also fixes the reset password URL where the static instance holds the url value but returns empty string.
 *  - Removed contradicting logic: if ( is_null( $user_id ) && 0 !== absint( $user_id ) ) { $user_id = wp_get_current_user()->ID; }
 *
 * @since 5.3
 *  - Added support for 3rd-party token parsing within loop-based actions.
 *
 * @since 5.0.1
 *  - Addressed rare instances where action tokens are not parsed immediately, and trigger tokens attempt to parse them.
 *  - This occurs when tokens are nested (i.e., a token within another token).
 *
 * @since 4.3
 *  - Previously located in `src/core/lib/process/class-automator-recipe-process-complete.php`, before the `$this->complete_actions()` call (authored by Saad).
 *  - Introduced the `automator_parse_token_for_trigger_{{integration}}_{{trigger_code}}` filter for trigger-specific token parsing.
 *
 * @since 4.2.1
 *  - Sanitizes token pieces to prevent invalid placeholders in SQL queries. Removes any occurrences of `{` or `}` from each piece to avoid malformed queries.
 *
 * @since 4.0
 *  - Added fallback logic for the `NUMTIMES` token when it is not set.
 *  - Refer to issue #1914: Trigger support for any page and any post (authored by Saad).
 *
 * @since 3.5
 *  - Implemented post meta parsing functionality.
 *
 * @since 3.0
 *  - Fixed a fatal error when token data resolves to an array.
 *  - Added a recommendation to use `do_shortcode()` on fields containing shortcodes. Ticket #2255.
 *
 * @package Uncanny_Automator
 */
class Automator_Input_Parser {

	use Singleton;

	/**
	 * @var array|mixed|void
	 */
	public $defined_tokens = array();

	/**
	 * @var Automator_Functions|null
	 */
	protected $automator_functions = null;

	/**
	 * @var array{0: 'recipe_total_run', 1: 'recipe_run'}
	 */
	protected $default_defined_tokens = array(
		'recipe_total_run',
		'recipe_run',
	);

	/**
	 * Initiates common tokens, and registers several filter.
	 *
	 * @return void
	 */
	public function __construct() {

		$this->defined_tokens = (array) apply_filters(
			'automator_pre_defined_tokens',
			$this->default_defined_tokens
		);

		// Hooks into general parser to parse the post meta tokens.
		//add_filter( 'automator_maybe_parse_token', array( $this, 'parse_postmeta_token' ), 10, 6 );

		// Attach the new trigger tokens arch for actions that are scheduled.
		add_filter( 'automator_pro_before_async_action_executed', array( $this, 'attach_trigger_tokens_hook' ), 10, 1 );

	}

	/**
	 * @param Automator_Functions $automator_functions
	 * @return void
	 */
	public function set_dependencies( Automator_Functions $automator_functions ) {
		$this->automator_functions = $automator_functions;
	}

	/**
	 * @return Automator_Functions
	 */
	public function automator_functions() {
		if ( is_null( $this->automator_functions ) ) {
			return Automator();
		}
		return $this->automator_functions;
	}

	/**
	 * Extracts an integer value from a specific key from an assocative array.
	 *
	 * @param string $key
	 * @param array $from_array
	 *
	 * @return int|null
	 */
	public static function extract_int( string $key, array $from_array ) {
		$value = $from_array[ $key ] ?? null;
		return absint( $value );
	}

	/**
	 * @param $args
	 * @param $trigger_id
	 *
	 * @return int|null
	 */
	public static function get_trigger_log_id( $args, $trigger_id ) {

		if ( ! isset( $args['recipe_triggers'] ) ) {
			return self::extract_int( 'trigger_log_id', $args );
		}

		if ( ! isset( $args['recipe_triggers'][ $trigger_id ] ) || ! isset( $args['recipe_triggers'][ $trigger_id ]['trigger_log_id'] ) ) {
			return self::extract_int( 'trigger_log_id', $args );
		}

		return absint( $args['recipe_triggers'][ $trigger_id ]['trigger_log_id'] );
	}

	/**
	 * Parse field text by replacing variable with real data
	 *
	 * @param $args
	 * @param $trigger_args
	 *
	 * @return string
	 */
	public function parse_vars( $args, $trigger_args = array() ) {

		$field_text = isset( $args['field_text'] ) ? $args['field_text'] : '';
		if ( empty( $field_text ) || ! is_string( $field_text ) ) {
			return $field_text;
		}

		$field_text = $args['field_text'];

		$field_text = $this->parse_recursively( $field_text, $args, $trigger_args );

		return $field_text;
	}

	/**
	 * parse_recursively
	 *
	 * @param $field_text
	 * @param $args
	 * @param $trigger_args
	 * @return string
	 */
	public function parse_recursively( $field_text, $args, $trigger_args = array() ) {

		$user_id    = isset( $trigger_args['user_id'] ) ? $trigger_args['user_id'] : null;
		$trigger_id = self::extract_int( 'trigger_id', $args );

		$context = array(
			'recipe_id'      => self::extract_int( 'recipe_id', $args ),
			'recipe_log_id'  => self::extract_int( 'recipe_log_id', $args ),
			'run_number'     => self::extract_int( 'run_number', $args ),
			'trigger_log_id' => self::get_trigger_log_id( $args, $trigger_id ),
			'trigger_id'     => $trigger_id,
			'user_id'        => $user_id,
		);

		// Look for text wrapped in double curly brackets: {{ ... }}.
		// If none are found, remove any stray '{{' or '}}' from the string.
		// Do not remove the brackets if its a valid JSON.
		if ( 0 === preg_match_all( '/{{((?>[^{}]+|(?R))*)}}/', $field_text, $matches ) && ! Automator()->utilities->is_json_string( $field_text ) ) {
			// Example: '{{Hello}} World' becomes 'Hello World'.
			return str_replace( array( '{{', '}}' ), '', $field_text );
		}

		$key_value_pairs = array();

		$parsed_tokens_record = $this->automator_functions()->parsed_token_records();

		foreach ( $matches[1] as $match ) {

			$token_with_brackets = '{{' . $match . '}}';

			$match = $this->parse_recursively( $match, $args, $trigger_args );

			$replaceable = '';

			// Check if the matching text contains a colon, which indicates a complex token. Note: there are tokens without ':' on it.
			$matching_text_has_colon = false !== strpos( $match, ':' );
			if ( $matching_text_has_colon ) {
				$replaceable = $this->parse_colon_delimited_tokens( $match, $args, $trigger_args, $context );
			}

			// Predefined tokens.
			$is_predefined_token = in_array( $match, $this->defined_tokens, true );
			if ( $is_predefined_token ) {
				$replaceable = $this->parse_defined_tokens_default( $match, $args, $context );
			}

			$replaceable = apply_filters( "automator_maybe_parse_{$match}", $replaceable, $field_text, $match, $user_id, $args );
			$replaceable = apply_filters( 'automator_maybe_parse_replaceable', $replaceable );

			// Added fallback fix for action token meta in case it was not recorded earlier.
			if ( false === strpos( $match, 'ACTION_META' ) ) {
				// Record the token raw vs replaceable with respect to $args for log details consumption.
				$parsed_tokens_record->record_token( '{{' . $match . '}}', $replaceable, $args );
			}

			$field_text = apply_filters( 'automator_maybe_parse_field_text', $field_text, $match, $replaceable, $args );

			// For Loops.
			if ( str_starts_with( $match, 'TOKEN_EXTENDED' ) ) {
				// Each token parts is separated by a ':' colon.
				$token_parts = (array) explode( ':', strtolower( $match ) );
				// We need to extract the first and second argument.
				list( $extended_flag, $extension_identifier ) = $token_parts;
				// Then use it as a filter so we dont have to check it. It is also safer.
				$field_text = apply_filters( "automator_token_parser_extended_{$extension_identifier}", $field_text, $match, $args, $trigger_args );
			}

			$key_value_pairs[ $token_with_brackets ] = $replaceable;

		} // End foreach.

		$field_text = strtr( $field_text, $key_value_pairs );

		// Only replace curly brackets if they follow the {{TOKEN}} pattern.
		// This prevents accidentally removing valid JSON closing brackets.
		//
		// Example:
		//    a:2:{i:0;s:12:"Sample array";i:1;a:2:{i:0;s:5:"Apple";i:1;s:6:"Orange";}}
		// becomes:
		//    a:2:{i:0;s:12:"Sample array";i:1;a:2:{i:0;s:5:"Apple";i:1;s:6:"Orange";
		return preg_replace( '/({{(.+?)}})/', '$2', $field_text );
	}

	/**
	 * @param string $match
	 * @param mixed[] $args
	 * @param mixed[] $trigger_args
	 * @param mixed[] $context
	 *
	 * @return string
	 */
	public function parse_colon_delimited_tokens( $match, $args, $trigger_args, $context ) {

		/**
		 * This section handles Trigger tokens.
		 */
		$replaceable = $this->get_parsed_data_value( $match, $context );

		if ( empty( $replaceable ) ) {
			return $this->process_trigger_token( $match, $args, $trigger_args, $context );
		}

		return $replaceable;

	}

	/**
	 * Get parsed data value from the database, if available.
	 *
	 * @param string $match The token match.
	 * @param array  $context The context of the current execution.
	 * @return string|null The parsed data value or null.
	 */
	public function get_parsed_data_value( $match, $context ) {

		$parse_args = array(
			'user_id'        => $context['user_id'],
			'trigger_id'     => $context['trigger_id'],
			'trigger_log_id' => $context['trigger_log_id'],
			'run_number'     => $context['run_number'],
		);

		$parsed_data = $this->automator_functions()->db->token->get( 'parsed_data', $parse_args );

		if ( ! empty( $parsed_data ) ) {

			$parsed_data = maybe_unserialize( $parsed_data );
			$token_key   = '{{' . $match . '}}';

			if ( isset( $parsed_data[ $token_key ] ) && ! empty( $parsed_data[ $token_key ] ) ) {
				return $parsed_data[ $token_key ];
			}
		}

		return null;
	}

	/**
	 * Process the trigger token if no parsed data is available.
	 *
	 * @param string $match The token match.
	 * @param array  $args The arguments for the current execution.
	 * @param array  $trigger_args The trigger-specific arguments.
	 * @param array  $context The context of the current execution.
	 * @return string The replaceable value.
	 */
	public function process_trigger_token( $match, $args, $trigger_args, $context ) {

		$pieces = explode( ':', $match );

		$replace_args = array(
			'pieces'          => (array) $pieces,
			'recipe_id'       => $context['recipe_id'] ?? null,
			'recipe_log_id'   => $context['recipe_log_id'] ?? null,
			'trigger_id'      => $context['trigger_id'] ?? null,
			'trigger_log_id'  => $context['trigger_log_id'] ?? null,
			'run_number'      => $context['run_number'] ?? null,
			'user_id'         => $context['user_id'] ?? null,
			'recipe_triggers' => $args['recipe_triggers'] ?? array(),
			'loop'            => $trigger_args['loop'] ?? array(),
		);

		$trigger_id = $context['trigger_id'] ?? null;

		return $this->replace_recipe_variables( $replace_args, $trigger_args, $trigger_id );

	}


	/**
	 * Parses defined tokens with default logic.
	 *
	 * Handles specific token replacements based on the `$match` parameter.
	 * If no predefined match is found, it applies a filter for customization.
	 *
	 * @param string $match   The token to be matched and replaced.
	 * @param mixed  $args    Arguments for token parsing. Should include 'user_id' and 'field_text'.
	 * @param mixed  $context Context data, expected to contain 'recipe_id' and 'run_number'.
	 *
	 * @return string Parsed token value or an empty string if no match is found.
	 */
	public function parse_defined_tokens_default( $match, $args, $context ) {

		// Get the user object based on the provided 'user_id' or default to the current user.
		$current_user = isset( $args['user_id'] )
			? get_user_by( 'ID', $args['user_id'] )
			: wp_get_current_user();

		// Handles recipe total run.
		if ( $match === 'recipe_total_run' && isset( $context['recipe_id'] ) ) {
			return Automator()->get->recipe_completed_times( $context['recipe_id'] );
		}

		// Handle recipe run.
		if ( $match === 'recipe_run' && isset( $context['run_number'] ) ) {
			return $context['run_number'];
		}

		// Apply a filter to allow further parsing customization.
		return apply_filters(
			"automator_maybe_parse_{$match}",
			'',
			isset( $args['field_text'] ) ? $args['field_text'] : '',
			$match,
			$current_user,
			$args
		);

	}

	/**
	 * @param $replace_args
	 * @param array $args
	 * @param int $source_trigger_id
	 *
	 * @return string
	 */
	public function replace_recipe_variables( $replace_args, $args = array(), $source_trigger_id = 0 ) {

		$pieces    = $this->sanitize_token_pieces( $this->parse_inner_token( $replace_args['pieces'], $replace_args ) );
		$recipe_id = self::extract_int( 'recipe_id', $args );

		/**
		 * Global tokens do not have Trigger ID. (e.g. POSTMETA:<POST_ID>:<POST_META>)
		 **/
		$trigger_id = absint( $pieces[0] );
		/**
		 * Skips processing for recipe trigger logic: `any` if the source trigger ID does not match the token's ID
		 *
		 * Continues the process when the token has no trigger ID.
		 */
		if ( $this->should_bail_for_logic_any( $trigger_id, $source_trigger_id ) ) {
			return null;
		}

		$trigger_log_id = self::get_trigger_log_id( $replace_args, $trigger_id );
		$run_number     = self::extract_int( 'run_number', $replace_args );
		$user_id        = $replace_args['user_id'] ?? null;
		$trigger        = $this->automator_functions()->get_trigger_data( $recipe_id, $trigger_id );
		$trigger_data   = array( $trigger );
		$retval         = '';

		// save trigger ID in the $replace_args
		$replace_args['trigger_id']     = $trigger_id;
		$replace_args['trigger_log_id'] = $trigger_log_id;

		foreach ( $pieces as $piece ) {

			$sub_piece         = null;
			$is_relevant_token = self::is_relevant_token( $piece );

			// Skip if trigger meta don't exists.
			if ( ! isset( $trigger['meta'] ) ) {
				continue;
			}

			// Modify the piece so it contains the first part of substring if its a relevant token.
			if ( $is_relevant_token ) {
				$sub_piece = explode( '_', $piece, 2 );
				$piece     = $sub_piece[0];
			}

			// Skip if the piece does not exists as key in the trigger meta array and if the piece is not equals 'NUMTIMES'.
			if ( ! key_exists( $piece, $trigger['meta'] ) && 'NUMTIMES' !== $piece ) {
				continue;
			}
			// Return the number of times the recipe has run if the pience equals 'NUMTIMES'.
			if ( 'NUMTIMES' === $piece ) {
				$retval = isset( $trigger['meta'][ $piece ] ) && ! empty( $trigger['meta'][ $piece ] ) ? $trigger['meta'][ $piece ] : 1;
			}

			// Return the post type label if the piece equals 'WPPOSTTYPES'.
			if ( 'WPPOSTTYPES' === $piece ) {
				$retval = $args['post_type_label'] ?? '';
			}

			// Skip processing at this point if the piece's value from the trigger meta is not numeric.
			if ( ! is_numeric( $trigger['meta'][ $piece ] ) ) {
				continue;
			}

			$post_id = $trigger['meta'][ $piece ];

			if ( intval( '-1' ) === intval( $trigger['meta'][ $piece ] ) ) {
				$post_id = $this->automator_functions()->db->trigger->get_token_meta( $piece, $replace_args );
			}

			$retval = self::extract_token_value_from_trigger( $piece, $sub_piece, $trigger, $post_id, $is_relevant_token );

		}

		$retval = $this->v3_parser( $retval, $replace_args, $args );

		if ( isset( $args['loop'] ) && is_array( $args['loop'] ) ) {
			$replace_args['loop'] = $args['loop'];
		}

		$retval = apply_filters( 'automator_maybe_parse_token', $retval, $pieces, $recipe_id, $trigger_data, $user_id, $replace_args );

		if ( ! empty( $trigger['meta']['code'] ) && ! empty( $trigger['meta']['integration'] ) ) {
			$filter = strtr(
				'automator_parse_token_for_trigger_{{integration}}_{{trigger_code}}',
				array(
					'{{integration}}'  => strtolower( $trigger['meta']['integration'] ),
					'{{trigger_code}}' => strtolower( $trigger['meta']['code'] ),
				)
			);
			$retval = apply_filters( $filter, $retval, $pieces, $recipe_id, $trigger_data, $user_id, $replace_args );
		}

		if ( ! is_array( $retval ) ) {

			if ( apply_filters(
				'automator_replace_recipe_variables_do_shortcode',
				true,
				$retval,
				$pieces,
				$recipe_id,
				$trigger_data,
				$user_id,
				$replace_args
			)
			) {
				$retval = do_shortcode( $retval );
			}

			return $retval;
		}

		// Handle Array if in case the data ends up being an Array (Edge cases)
		return join( ', ', $retval );
	}

	/**
	 * Extracts the token value from the trigger.
	 *
	 * @param string $piece The type of token (e.g., 'WPPOST', 'NUMTIMES').
	 * @param string $subpiece A part of the token, if available (e.g., specific post meta key).
	 * @param array  $trigger The trigger data containing meta information.
	 * @param int    $post_id The ID of the related post.
	 * @param bool   $is_relevant_token Whether the token is relevant for further processing.
	 *
	 * @return string|null The extracted value or null if not found.
	 */
	public static function extract_token_value_from_trigger( $piece, $subpiece, $trigger, $post_id, $is_relevant_token ) {

		$token_id   = isset( $subpiece[1] ) ? $subpiece[1] : ''; // Fallback for PHP 7 compatibility.
		$meta_value = isset( $trigger['meta'][ $piece ] ) ? $trigger['meta'][ $piece ] : '';

		// Handle post and page tokens.
		if ( $piece === 'WPPOST' || $piece === 'WPPAGE' ) {
			return self::identify_post_value_from_substr( $token_id, $post_id );
		}

		// Handle the 'NUMTIMES' token, returning 1 if not set.
		if ( $piece === 'NUMTIMES' ) {
			return ! empty( $meta_value ) ? $meta_value : '1';
		}

		// Handle user-related tokens.
		if ( $piece === 'WPUSER' ) {
			return self::get_user_email_by_id( $meta_value );
		}

		// Default handling for other tokens especially for the legacy internal integrations such as WPForms, GF, etc.
		return self::handle_trigger_tokens_common( $piece, $token_id, $meta_value, $post_id, $is_relevant_token );

	}

	/**
	 * Retrieves the email of a user by their ID.
	 *
	 * @param mixed $user_id The user ID.
	 * @return string|null The user's email or null if the user is not found.
	 */
	private static function get_user_email_by_id( $user_id ) {
		$user = get_user_by( 'ID', absint( $user_id ) );
		return $user ? $user->user_email : null;
	}

	/**
	 * Handles general cases for tokens.
	 *
	 * @param string $piece The type of token.
	 * @param string $token_id The token identifier.
	 * @param string $meta_value The meta value from the trigger.
	 * @param int    $post_id The related post ID.
	 * @param bool   $is_relevant_token Whether the token is relevant.
	 *
	 * @return string The extracted value or null.
	 */
	public static function handle_trigger_tokens_common( $piece, $token_id, $meta_value, $post_id, $is_relevant_token ) {

		$is_any_value = intval( $meta_value ) === -1;

		// If value is 'Any', post ID is numeric, and the token is relevant.
		if ( $is_any_value && is_numeric( $post_id ) && $is_relevant_token ) {
			return self::identify_post_value_from_substr( $token_id, $post_id );
		}

		// If the token is relevant and does not contain 'ANON', return the identified value.
		if ( $is_relevant_token && ! preg_match( '/ANON/', $piece ) ) {
			return self::identify_post_value_from_substr( $token_id, $meta_value );
		}

		// If the token is not a relevant token and is user token (not anon) and is any.
		if ( ! $is_relevant_token && ! preg_match( '/ANON/', $piece ) && $is_any_value ) {
			return html_entity_decode( get_the_title( $post_id ), ENT_QUOTES, 'UTF-8' );
		}

		// If the token is not a relevant token and is user token (not anon) regardless of the selected value.
		if ( ! $is_relevant_token && ! preg_match( '/ANON/', $piece ) ) {
			return html_entity_decode( get_the_title( $meta_value ), ENT_QUOTES, 'UTF-8' );
		}

		// Return empty string if no matching conditions were met.
		return '';
	}

	/**
	 * Determines if the piece is a relevant token or not.
	 *
	 * @param mixed $piece
	 * @return bool
	 */
	public static function is_relevant_token( $piece ) {
		return (bool) preg_match( '/_(ID|URL|EXCERPT|THUMB_URL|THUMB_ID)$/', $piece );
	}

	/**
	 * @param string $str The piece of the token.
	 * @param int $post_id
	 *
	 * @return string
	 */
	public static function identify_post_value_from_substr( $str, $post_id ) {

		$post_id = absint( $post_id );

		// Use anonymous functions for lazy evaluation.
		$map = array(
			'ID'        => function() use ( $post_id ) {
				return $post_id; },
			'URL'       => function() use ( $post_id ) {
				return get_permalink( $post_id ); },
			'THUMB_URL' => function() use ( $post_id ) {
				return get_the_post_thumbnail_url( $post_id, 'full' ); },
			'THUMB_ID'  => function() use ( $post_id ) {
				return get_post_thumbnail_id( $post_id ); },
			'EXCERPT'   => function() use ( $post_id ) {
				return Automator()->utilities->automator_get_the_excerpt( $post_id ); },
		);

		// Check if the key exists and call the corresponding function, otherwise return an empty string.
		return isset( $map[ $str ] ) ? $map[ $str ]() : '';

	}

	/**
	 * Sanitize pieces. No piece should contain {{ or }} in the value to avoid
	 * following situation
	 * SELECT meta_value FROM wp_uap_trigger_log_meta
	 * WHERE meta_key = 'ANONWPFFFORMS' AND automator_trigger_log_id = 1009
	 * AND automator_trigger_id = {{8347
	 * LIMIT 0, 1
	 *
	 * @since v4.2.1+
	 */
	public function sanitize_token_pieces( $pieces = array() ) {

		$pieces = array_map(
			function ( $piece ) {
				return str_replace( array( '{', '}' ), '', $piece );
			},
			$pieces
		);

		return $pieces;

	}

	/**
	 * @param $retval
	 * @param $replace_args
	 * @param array $args
	 *
	 * @return false|mixed
	 */
	public function v3_parser( $retval, $replace_args, $args = array() ) {

		$pieces     = $this->parse_inner_token( $replace_args['pieces'], $args );
		$recipe_id  = $replace_args['recipe_id'];
		$trigger_id = absint( $pieces[0] );
		$trigger    = $this->automator_functions()->get_trigger_data( $recipe_id, $trigger_id );

		if ( empty( $trigger ) ) {
			return $retval;
		}

		$trigger_code = $trigger['meta']['code'] ?? '';

		// Set the parser to null.
		$token_parser = null;

		// If the trigger code is set. Try using it as a callback method to retrieve the token value.
		if ( ! empty( $trigger_code ) ) {
			$token_parser = $this->automator_functions()->get->value_from_trigger_meta( $trigger['meta']['code'], 'token_parser' );
		}

		if ( ! empty( $token_parser ) ) {

			$token_args = array(
				'trigger_code' => $trigger_code,
				'replace_args' => $replace_args,
				'args'         => $args,
			);

			// @todo: Add function_exists and method_exists? Is it necessary?
			return call_user_func( $token_parser, $retval, $token_args );

		}

		return $retval;
	}

	/**
	 * Generates reset password URL from token.
	 *
	 * @param int $user_id
	 *
	 * @return string Returns empty string if provided $user_id is not found.
	 */
	public function reset_password_url_token( $user_id = 0 ) {

		// Singleton instance.
		static $reset_password = null;

		$user = get_user_by( 'ID', $user_id );

		if ( ! $user || ! $user instanceof \WP_User ) {
			return ''; // Returns empty string if user is not found.
		}

		// Convert to array if null.
		$reset_password = is_null( $reset_password ) ? array() : $reset_password;

		// Generate reset password URL for the user.
		if ( ! isset( $reset_password[ $user_id ] ) ) {
			$reset_password[ $user_id ] = add_query_arg(
				array(
					'action' => 'rp',
					'key'    => get_password_reset_key( $user ),
					'login'  => $user->user_login,
				),
				wp_login_url()
			);
		}

		return $reset_password[ $user_id ];
	}

	/**
	 * Generates reset password HTML link.
	 *
	 * @param int $user_id
	 *
	 * @return string
	 * @see automator_token_reset_password_link_html
	 */
	public function generate_reset_token( $user_id = 0 ) {

		$text = esc_attr_x( 'Click here to reset your password.', 'Reset password token text', 'uncanny-automator' );

		$reset_pw_url = $this->reset_password_url_token( $user_id );

		return apply_filters(
			'automator_token_reset_password_link_html',
			'<a href="' . esc_url( $reset_pw_url ) . '" title="' . esc_attr( $text ) . '">'
			. esc_html( $text )
			. '</a>',
			$user_id
		);

	}

	/**
	 * @param null $field_text
	 * @param null $recipe_id
	 * @param null $user_id
	 *
	 * @param null $args
	 *
	 * @return null|string
	 */
	public function text( $field_text = null, $recipe_id = null, $user_id = null, $trigger_args = null ) {

		// Early return if null.
		if ( is_null( $field_text ) ) {
			return null;
		}

		// Early return if empty array.
		if ( is_array( $field_text ) && empty( $field_text ) ) {
			return '';
		}

		// Prepare arguments with optional values.
		$args = array(
			'field_text'      => $field_text,
			'meta_key'        => null,
			'user_id'         => $user_id,
			'action_data'     => $trigger_args['action_meta'] ?? null,
			'recipe_id'       => $recipe_id,
			'trigger_log_id'  => $trigger_args['trigger_log_id'] ?? null,
			'run_number'      => $trigger_args['run_number'] ?? null,
			'recipe_log_id'   => $trigger_args['recipe_log_id'] ?? null,
			'trigger_id'      => $trigger_args['trigger_id'] ?? null,
			'recipe_triggers' => $trigger_args['recipe_triggers'] ?? array(),
			'action_meta'     => $trigger_args['action_meta'] ?? null,
		);

		// Apply filter for field text.
		$args['field_text'] = apply_filters(
			'automator_action_token_input_parser_text_field_text',
			$args['field_text'],
			$args,
			$trigger_args
		);

		// Parse field text with variables.
		$field_text = apply_filters(
			'automator_text_field_parsed',
			$this->parse_vars( $args, $trigger_args ),
			$args
		);

		// Parse shortcodes if necessary and return the result.
		return $this->maybe_parse_shortcodes_in_fields( $field_text, $recipe_id, $user_id, $args );

	}

	/**
	 * @param $field_text
	 * @param $recipe_id
	 * @param $user_id
	 * @param $args
	 *
	 * @return mixed|string|null
	 */
	public function maybe_parse_shortcodes_in_fields( $field_text, $recipe_id = null, $user_id = null, $args = array() ) {

		$skip_do_shortcode_actions = apply_filters(
			'automator_skip_do_shortcode_parse_in_fields',
			array(
				'CREATEPOST',
				'BULKUPDATE_CODE',
			)
		);

		$action_meta_code = isset( $args['action_meta'] )
			&& isset( $args['action_meta']['code'] ) ?
			$args['action_meta']['code'] : '';

		if ( true === apply_filters( 'automator_skip_cslashing_value', false, $field_text, $action_meta_code, $recipe_id, $args ) ) {
			return $field_text;
		}

		// If filter is set to true OR action meta matches
		if ( in_array( $action_meta_code, $skip_do_shortcode_actions, true )
			|| true === apply_filters( 'automator_skip_do_action_field_parsing', $field_text, $recipe_id, $user_id, $args ) ) {
			// The function stripcslashes preserves the \a, \b, \f, \n, \r, \t and \v characters.
			$text = apply_filters( 'automator_parse_token_parse_text', $this->stripcslashes( $field_text ), $field_text, $args );
			return $text;
		}

		/**
		 * May be run a do_shortcode on the field itself if it contains a shortcode?
		 * Ticket# 22255
		 *
		 * @since 3.0
		 */
		return do_shortcode( apply_filters( 'automator_parse_token_parse_text', $this->stripcslashes( $field_text ), $field_text, $args ) );
	}

	/**
	 * Conditionally runs the text against stripcslashes.
	 *
	 * @param string $text
	 *
	 * @return string
	 */
	public function stripcslashes( $text ) {

		if ( ! is_string( $text ) ) {
			return $text;
		}

		$autostripcslashes = apply_filters( 'automator_parse_token_parse_text_autostripcslashes', true, $text );

		if ( true === $autostripcslashes ) {
			return stripcslashes( $text );
		}

		return $text;

	}

	/**
	 * This function parses inner token(s) {{POSTMETA:[[TOKEN]]:meta_key}} and
	 * replace its value in actual token
	 *
	 * @param $pieces
	 * @param $args
	 *
	 * @return mixed
	 * @sinc 3.5
	 */
	public function parse_inner_token( $pieces, $args ) {
		if ( empty( $pieces ) ) {
			return $pieces;
		}
		$pieces = $this->parse_inner_token_post_id_part( $pieces, $args );
		$pieces = $this->parse_inner_token_meta_key_part( $pieces, $args );

		return $pieces;
	}

	/**
	 * This function parses "post ID" part of inner token
	 * {{POSTMETA:[[TOKEN]]:[[meta_key]]}} and replace its value in actual
	 * token
	 *
	 * @param $pieces
	 * @param $args
	 *
	 * @return mixed
	 */
	public function parse_inner_token_post_id_part( $pieces, $args ) {

		if ( ! array_key_exists( 1, $pieces ) ) {
			return $pieces;
		}

		if ( ! preg_match( '/\[\[(.+)\]\]/', $pieces[1], $arr ) ) {
			return $pieces;
		}

		$recipe_id    = $args['recipe_id'];
		$user_id      = $args['user_id'];
		$trigger_args = $args;

		unset( $trigger_args['pieces'] );

		$token = str_replace(
			array( '[', ']', ';' ),
			array( '{', '}', ':' ),
			$arr[0]
		);

		$parsed = $this->text( $token, $recipe_id, $user_id, $trigger_args );

		$pieces[1] = apply_filters( 'automator_parse_inner_token', $parsed, $token, $pieces, $args );

		return $pieces;
	}

	/**
	 * This function parses "meta_key" part of inner token
	 * {{POSTMETA:[[TOKEN]]:[[meta_key]]}} and replace its value in actual
	 * token
	 *
	 * @param $pieces
	 * @param $args
	 *
	 * @return mixed
	 */
	public function parse_inner_token_meta_key_part( $pieces, $args ) {
		if ( ! array_key_exists( 2, $pieces ) ) {
			return $pieces;
		}
		if ( ! preg_match( '/\[\[(.+)\]\]/', $pieces[2], $arr ) ) {
			return $pieces;
		}
		$recipe_id    = $args['recipe_id'];
		$user_id      = $args['user_id'];
		$trigger_args = $args;
		unset( $trigger_args['pieces'] );
		$token     = str_replace(
			array( '[', ']', ';' ),
			array(
				'{',
				'}',
				':',
			),
			$arr[0]
		);
		$parsed    = $this->text( $token, $recipe_id, $user_id, $trigger_args );
		$pieces[2] = apply_filters( 'automator_parse_inner_token', $parsed, $token, $pieces, $args );

		return $pieces;
	}

	/**
	 * Parses a postmeta token and retrieves the meta value.
	 *
	 * @param mixed  $default_value Default value to return if parsing fails.
	 * @param array  $pieces        Array containing the token parts.
	 * @param int    $recipe_id     The recipe ID for context.
	 * @param array  $trigger_data  Trigger data for context.
	 * @param int    $user_id       The user ID for context.
	 * @param array  $replace_args  Arguments used for token replacement.
	 *
	 * @return mixed The parsed postmeta value or the default value.
	 */
	public function parse_postmeta_token( $default_value, $pieces, $recipe_id, $trigger_data, $user_id, $replace_args ) {

		// Ensure the token relates to postmeta.
		if ( ! in_array( 'POSTMETA', $pieces, true ) ) {
			return $default_value;
		}

		// Parse the inner token.
		$parsed_pieces = $this->parse_inner_token( $pieces, $replace_args );

		// Retrieve the post ID and meta key.
		$post_id  = isset( $parsed_pieces[1] ) ? absint( $parsed_pieces[1] ) : 0;
		$meta_key = $parsed_pieces[2] ?? '';

		// Retrieve the post meta value.
		$retval = get_post_meta( $post_id, $meta_key, true );

		// Handle the meta value if it's an array.
		if ( is_array( $retval ) ) {
			$retval = $this->handle_array_values( $retval );
		}

		// Apply filters with contextual data and return the result.
		return apply_filters(
			'automator_postmeta_token_parsed',
			$retval,
			$post_id,
			$meta_key,
			array(
				'value'        => $retval,
				'pieces'       => $parsed_pieces,
				'recipe_id'    => $recipe_id,
				'trigger_data' => $trigger_data,
				'user_id'      => $user_id,
				'replace_args' => $replace_args,
			)
		);
	}

	/**
	 * Handles array values.
	 *
	 * @param array $value Array value to process.
	 * @return string The processed array value.
	 */
	public function handle_array_values( array $value ) {

		$classification = Array_Group_Classifier::classify_array( $value );

		// Return a comma-separated string for 'g2b' classification.
		if ( 'g2b' === $classification ) {
			return implode( ', ', $value );
		}

		// Return the JSON-encoded array.
		return wp_json_encode( $value );
	}


	/**
	 * Determines whether should bail processing for `any` logic types of triggers.
	 *
	 * @param int $trigger_id The token's trigger ID.
	 * @param int $source_trigger_id The token's trigger `source`. The ID of the trigger where the token is processed for.
	 *
	 * @return boolean True if should bail. Otherwise, false.
	 */
	public function should_bail_for_logic_any( $trigger_id = 0, $source_trigger_id = 0 ) {

		/**
		 * Account for tokens that has no Trigger ID. (e.g POSTMETA:<POST_ID>:<META_KEY>)
		 *
		 * Global tokens should be parsed regardless whether they are coming from Trigger or not,
		 * and they should be parse regardless of Any or And.
		 *
		 * Return false immediately if that is the case.
		 */
		if ( 0 === $trigger_id ) {
			return false;
		}

		// Determines whether the token's trigger ID matches the 'source' trigger ID.
		$is_source_trigger_matches_token_trigger = ( $trigger_id === $source_trigger_id );

		// Determines whether the recipe trigger token matches 'any'.
		$is_recipe_logic_any = 'any' === Automator()->db->trigger->get_recipe_triggers_logic_by_child_id( $source_trigger_id );

		// Return true if source trigger does not match the token's trigger ID `and` recipe logic is equals to `any`.
		return ! $is_source_trigger_matches_token_trigger && $is_recipe_logic_any;

	}

	/**
	 * Attach the trigger token hooks.
	 *
	 * @param array $action The action array.
	 *
	 * @return void.
	 */
	public function attach_trigger_tokens_hook( $action ) {

		$code = isset( $action['args']['code'] ) ? $action['args']['code'] : '';

		if ( empty( $code ) ) {
			return;
		}

		$filter = strtr(
			'automator_parse_token_for_trigger_{{integration}}_{{trigger_code}}',
			array(
				'{{integration}}'  => strtolower( Automator()->get->value_from_trigger_meta( $code, 'integration' ) ),
				'{{trigger_code}}' => strtolower( $code ),
			)
		);

		// Get the token value when `automator_parse_token_for_trigger_{{integration}}_{{trigger_code}}`.
		add_filter( $filter, array( $this, 'fetch_trigger_tokens' ), 20, 6 );

		return $action;

	}

	/**
	 * Fetches trigger tokens from the database.
	 *
	 * @param mixed  $value        Default value to return if no token is found.
	 * @param array  $pieces       Array containing recipe ID, token identifier, and token ID.
	 * @param int    $recipe_id    Recipe ID (currently unused, but retained for consistency).
	 * @param array  $trigger_data Trigger data (currently unused).
	 * @param int    $user_id      User ID (currently unused, but retained for consistency).
	 * @param mixed  $replace_arg  Optional argument for token lookup.
	 *
	 * @return mixed The token value or the default value.
	 */
	public function fetch_trigger_tokens( $value, $pieces, $recipe_id, $trigger_data, $user_id, $replace_arg ) {

		// Validate the $pieces array early to avoid warnings.
		if ( ! $this->is_valid_pieces( $pieces ) ) {
			return $value;
		}

		list( , $token_identifier, $token_id ) = $pieces;

		// Fetch the token data and decode if necessary.
		$data = $this->automator_functions()->db->token->get( $token_identifier, $replace_arg );
		$data = $this->decode_data( $data );

		// Return the token value if found; otherwise, return the default value.
		return isset( $data[ $token_id ] ) ? $data[ $token_id ] : $value;
	}

	/**
	 * Validates the structure of the $pieces array.
	 *
	 * @param array $pieces The input pieces array.
	 * @return bool True if valid, false otherwise.
	 */
	private function is_valid_pieces( $pieces ) {
		return is_array( $pieces ) && isset( $pieces[1], $pieces[2] );
	}

	/**
	 * Decodes data if it's not already an array.
	 *
	 * @param mixed $data The data to decode.
	 * @return array Decoded data as an array.
	 */
	private function decode_data( $data ) {
		return is_array( $data ) ? $data : json_decode( $data, true );
	}

}
