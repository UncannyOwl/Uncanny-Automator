<?php
namespace Uncanny_Automator\Recipe;

/**
 * Trait Action_Tokens
 *
 * This Trait handles the parsing of action tokens.
 *
 * @since 4.6
 *
 * @version 1.0.0
 */
trait Action_Tokens {

	/**
	 * The priority.
	 *
	 * @var int Used the default filter priority value.
	 */
	private $renderable_priority = 10;

	/**
	 * The number of args.
	 *
	 * @var int This value should reflect the number of arguments accepted by the renderable filter.
	 */
	private $renderable_number_args = 3;

	/**
	 * The action token meta_value are fetched with matching specific meta_key.
	 *
	 * @var string The meta_key value used in `uap_action_log_meta` table.
	 */
	private $meta_key = 'action_tokens';

	protected $hydrated_tokens_replace_pairs = '';

	public function clear_hydrated_tokens_replace_pairs() {

		$this->hydrated_tokens_replace_pairs = '';

	}

	public function get_hydrated_tokens_replace_pairs() {

		return $this->hydrated_tokens_replace_pairs;

	}

	/**
	 * Use this method to set tokens per action.
	 *
	 * @return boolean|Uncanny_Automator\Recipe\Action_Tokens The trait object. Otherwise, false.
	 */
	public function set_action_tokens( $tokens = array(), $action_code = '' ) {

		if ( empty( $action_code ) ) {

			_doing_it_wrong(
				'Uncanny_Automator\Recipe\Action_Tokens::set_action_tokens',
				'Method set_action_tokens must have an action_code',
				'1.0'
			);

			return false;

		}

		$this->register_action_token_hooks();

		$tokens = $this->format_tokens( $tokens, $action_code );

		add_filter(
			'automator_action_' . $action_code . '_tokens_renderable',
			function ( $registered_tokens = array(), $action_id = null, $recipe_id = null ) use ( $tokens ) {
				return $registered_tokens + $tokens;
			},
			$this->renderable_priority,
			$this->renderable_number_args
		);

		return $this;
	}

	private function format_tokens( $tokens = array(), $action_code = '' ) {

		$formatted_tokens = array();

		foreach ( $tokens as $key => $props ) {

			if ( empty( $props['name'] ) || empty( $key ) ) {

				_doing_it_wrong(
					'Uncanny_Automator\Recipe\Action_Tokens::format_tokens',
					'Method format_tokens called from set_tokens must have key and name',
					'1.0'
				);

				continue;

			}

			$formatted_tokens[] = array(
				'tokenId'     => $key,
				'tokenParent' => $action_code,
				'tokenName'   => $props['name'],
				'tokenType'   => ! empty( $props['type'] ) ? $props['type'] : 'int',
			);

		}

		return $formatted_tokens;

	}

	/**
	 * Use this method to hydrate the tokens.
	 *
	 * This method simply registers a new method to `automator_action_tokens`.
	 *
	 * @param array $args The token args.
	 *
	 * @return Uncanny_Automator\Recipe\Action_Tokens The trait object.
	 */
	public function hydrate_tokens( $args = array() ) {

		// Have a reference to the actual value.
		$this->hydrated_tokens_replace_pairs = wp_json_encode( $args );

		// Persists the token value by registering a hook to automator_action_created.
		add_action( 'automator_action_created', array( $this, 'persist_token_value' ), 10, 1 );
		add_action( 'automator_pro_async_action_after_run_execution', array( $this, 'persist_token_value' ), 10, 1 );

		return $this;

	}

	/**
	 * Register the action hooks required for action tokens to work.
	 *
	 * @return void|boolean Sets some required action hooks. Otherwise, false if action hooks are already loaded.
	 */
	private function register_action_token_hooks() {

		// Automatically register the action hooks once an action has set a token.
		if ( did_action( 'automator_action_tokens_parser_loaded' ) ) {
			return false;
		}

		// Manually parse the action tokens.
		add_filter(
			'automator_action_token_input_parser_text_field_text',
			array(
				$this,
				'interpolate_tokens_with_values',
			),
			10,
			3
		);

		do_action( 'automator_action_tokens_parser_loaded' );

	}

	/**
	 * Persist the token value into the database. This is an internal method.
	 *
	 * Callback method to `automator_action_created`.
	 *
	 * @param array $args The accepted parameters from `automator_action_created`
	 *
	 * @return bool|int False if db insert is not successul. Otherwise, the last inserted ID (int).
	 */
	public function persist_token_value( $args ) {

		$supported_hooks = array(
			'automator_action_created',
			'automator_pro_async_action_after_run_execution',
		);

		if ( ! in_array( current_action(), $supported_hooks, true ) ) {

			_doing_it_wrong(
				'Uncanny_Automator\Recipe\Action_Tokens::persist_token_value',
				'This trait method is not intended to be called directly',
				'1.0'
			);

			return false;

		}

		$action_token = array(
			'should_skip_add_meta' => false,
			'value'                => $this->get_hydrated_tokens_replace_pairs(),
		);

		$action_token = apply_filters( 'automator_action_tokens_hydrated_tokens', $action_token, $args, $this );

		// Clear the tokens so actions that inherits the Traits properties dont get this value.
		$this->clear_hydrated_tokens_replace_pairs();

		// Allows custom flows to skip adding entry to action meta.
		if ( true === $action_token['should_skip_add_meta'] ) {
			return false;
		}

		// Dont allow empty string values.
		if ( '' === $action_token['value'] ) {
			return false;
		}

		$user_id       = $args['user_id'];
		$action_log_id = isset( $args['action_log_id'] ) ? $args['action_log_id'] : null;
		$action_id     = isset( $args['action_id'] ) ? $args['action_id'] : null;

		if ( 'automator_pro_async_action_after_run_execution' === current_action() ) {
			$action_data   = $args['action_data'];
			$user_id       = $args['user_id'];
			$action_log_id = $action_data['action_log_id'];
			$action_id     = $action_data['ID'];
		}

		$meta_added = Automator()->db->action->add_meta(
			$user_id,
			$action_log_id,
			$action_id,
			$this->meta_key,
			$action_token['value']
		);

		return $meta_added;

	}

	/**
	 * Matches the given context with arguments and replaces the text according to value given. This is an internal method.
	 *
	 * Callback method to `__automator_action_token_input_parser_text_field_text___` action hook.
	 *
	 * @param string $field_text The context to replace (e.g. email body).
	 * @param array $args The array containing necessary items.
	 * @param array $trigger_args The trigger args.
	 *
	 * @return string The final string after find and replace.
	 */
	public function interpolate_tokens_with_values( $field_text, $args, $trigger_args ) {

		// Max depth depth of 10.
		$max_iteration = apply_filters( 'automator_action_tokens_interpolate_tokens_with_values_max_iteration', 10 );

		// Initiate to 0.
		$count_iteration = 0;

		$replaceables = $this->get_replace_pairs( $field_text, $trigger_args, $args );

		if ( false === $replaceables ) {
			return $field_text;
		}

		if ( ! empty( $replaceables ) ) {

			// The strtr array format is '[ {{{ACTION_FIELD: % d: % s}}} ] => $actual_value'.
			$field_text = strtr( $args['field_text'], $replaceables );

			// Do recursive magic ➰ for either of these action tokens.
			$do_iterate = true;

			while ( $do_iterate && ( strpos( $field_text, '{{ACTION_FIELD' ) || strpos( $field_text, '{{ACTION_META' ) ) ) {

				$count_iteration ++;

				// Terminate safely, in case for some reason, unexpected input turns into infinite loop.
				if ( $count_iteration >= $max_iteration ) {
					$do_iterate = false;
				}

				$field_text = strtr( $field_text, $this->get_replace_pairs( $field_text, $trigger_args, $args ) );

			}
		}

		return $field_text;

	}

	/**
	 * Iterate through string the match the action tokens and return the replace vars list.
	 *
	 * @param string $field_text The haystack.
	 * @param array $process_args The process args.
	 *
	 * @return array The collection of replace vars.
	 */
	private function get_replace_pairs( $field_text, $process_args, $args ) {

		$replaceables = array();

		// Only process tokens that have `ACTION` as prefix. It could either be 'FIELD' or 'META'.
		// Ensures it doesn't conflict with existing tokens .
		preg_match_all( '/{{ACTION_*\s*(.*?)\s*}}/', $field_text, $matches );

		if ( empty( $matches[1] ) ) {

			return false;

		}

		foreach ( $matches[1] as $index => $meta ) {

			$token_pieces = explode( ':', $meta );

			// Making sure that this is an action token that we are processing.
			if ( ! $this->is_action_token( $token_pieces ) ) {
				continue; // Skip;
			}

			list ( $type, $action_id, $parent, $meta_key ) = $token_pieces;

			$action_log_id = $this->get_action_log_id( $action_id, $process_args['recipe_log_id'] );

			// Action meta type.
			if ( 'META' === $type ) {

				$raw = sprintf( '{{ACTION_META:%d:%s:%s}}', $action_id, $parent, $meta_key );

				$replaceables[ $raw ] = $this->get_meta_value( $action_log_id, $meta_key, $args, $process_args, $action_id );

			}

			// Action field type.
			if ( 'FIELD' === $type ) {

				$raw = sprintf( '{{ACTION_FIELD:%d:%s:%s}}', $action_id, $parent, $meta_key );

				$replaceables[ $raw ] = $this->get_field_value( $action_log_id, $meta_key, $args, $process_args, $action_id );

			}

			$parsed_tokens_record = Automator()->parsed_token_records();

			$parsed_tokens_record->record_token( $raw, $replaceables[ $raw ], $args );

		}

		return $replaceables;

	}

	/**
	 * Get the field value from the db using action log id and action meta key.
	 *
	 * @param int $action_log_id The action log ID.
	 * @param string $action_meta_key The meta key.
	 *
	 * @return string The field value, if available. Otherwise, empty string.
	 */
	private function get_field_value( $action_log_id = 0, $action_meta_key = '', $args = array(), $process_args = array(), $action_id = null ) {

		$value = '';

		$db_action_meta = Automator()->db->action->get_meta( $action_log_id, 'metas' );

		$action_meta = apply_filters( 'automator_action_tokens_field_token_value', (array) maybe_unserialize( $db_action_meta ), $action_id, $process_args );

		// Decide whether to split the meta key with parts or go with meta key. Supports repeater field.
		$is_4th_part_correctly_separated = $this->is_correctly_separated( explode( '|', $action_meta_key ) );

		// Assume, it's a repeater field.
		if ( $is_4th_part_correctly_separated ) {

			list( $option_code, $index, $field_code ) = explode( '|', $action_meta_key );

			// Since $action_meta_key is now something like `GF_FIELDS|1GF_COLUMN_NAME`.
			// We should revert it back to actual $option_code.
			$action_meta_key = $option_code;

		}

		$value = $this->find_token_value( $action_meta_key, $action_meta );

		if ( false !== $value ) {
			$value = $this->maybe_handle_custom_value( $value, $action_meta );
		}

		// If its a repeater field and the value is JSON, get the requested field code from index.
		$meta_value = isset( $value->meta_value ) ? $value->meta_value : null;

		$repeater_fields = is_string( $meta_value ) ? json_decode( $meta_value, true ) : $meta_value;

		if ( $is_4th_part_correctly_separated && ! empty( $repeater_fields ) ) {

			return isset( $repeater_fields[ $index ][ $field_code ] ) ? $repeater_fields[ $index ][ $field_code ] : '';

		}

		// Handle non-repeater JSON values from field.
		if ( Automator()->utilities->is_json_string( $meta_value ) ) {
			return join( ', ', json_decode( $meta_value ) );
		}

		return ! empty( $meta_value ) ? $meta_value : '';

	}

	/**
	 * find_token_value
	 *
	 * @param  string $token
	 * @param  array $action_meta
	 * @return object
	 */
	public function find_token_value( $token, $action_meta ) {

		$found_key = array_search( $token, array_column( $action_meta, 'meta_key' ), true );

		if ( false === $found_key ) {
			return false;
		}

		$value = $action_meta[ $found_key ];

		return $value;
	}

	/**
	 * maybe_handle_custom_value
	 *
	 * @param  object $value
	 * @return object
	 */
	public function maybe_handle_custom_value( $value, $action_meta ) {

		if ( 'automator_custom_value' !== $value->meta_value ) {
			return $value;
		}

		$automator_custom_value = $this->find_token_value( $value->meta_key . '_custom', $action_meta );

		if ( false === $automator_custom_value ) {
			return $value;
		}

		return $automator_custom_value;
	}

	/**
	 * Get the meta value from the db using action log id and action meta key.
	 *
	 * @param int $action_log_id The action log ID.
	 * @param string $action_meta_key The meta key.
	 * @param mixed[] $process_args The process args.
	 * @param int $action_id The reference action ID. Not the action ID that consumes the token.
	 *
	 * @return string The meta value, if available. Otherwise, empty string.
	 */
	private function get_meta_value( $action_log_id = 0, $action_meta_key = '', $args = array(), $process_args = array(), $action_id = null ) {

		$action_meta_token = Automator()->db->action->get_meta( $action_log_id, $this->meta_key );

		$action_meta_token = $this->stringify( apply_filters( 'automator_action_tokens_meta_token_value', $action_meta_token, $action_id, $process_args ) );

		$tokens = (array) json_decode( $action_meta_token, true );

		$token_value = isset( $tokens[ $action_meta_key ] ) ? $tokens[ $action_meta_key ] : '';

		if ( ! empty( $args ) && isset( $args['action_data']['should_apply_extra_formatting'] ) ) {

			if ( true === $args['action_data']['should_apply_extra_formatting'] ) {

				// Standardize newline characters to "\n".
				$token_value = str_replace( array( "\r\n", "\r" ), "\n", $token_value );

				// Remove more than two contiguous line breaks.
				$token_value = preg_replace( "/\n\n+/", "\n\n", $token_value );

				// Split up the contents into an array of strings, separated by double line breaks.
				$paragraphs = preg_split( '/\n\s*\n/', $token_value, - 1, PREG_SPLIT_NO_EMPTY );

				// Only apply automatic formatting on the value if it's a paragraph.
				if ( count( $paragraphs ) > 1 ) {

					$token_value = apply_filters(
						'automator_action_tokens_apply_auto_formatting',
						wpautop( $token_value ),
						$token_value,
						$action_meta_key,
						$action_log_id,
						$args,
						$this
					);

				}
			}
		}

		return $token_value;

	}

	/**
	 * Converts the given string to its string value.
	 *
	 * @param string $string
	 *
	 * @return string
	 */
	private function stringify( $string = '' ) {

		if ( ! is_scalar( $string ) || empty( $string ) ) {
			return '';
		}

		return (string) $string;
	}

	/**
	 * Retrieves the latest action log id from uap_action_log with respect to recipe log id.
	 *
	 * @param int $action_id The action ID.
	 * @param int $recipe_log_id The recipe log ID.
	 *
	 * @return int The action log ID.
	 */
	private function get_action_log_id( $action_id, $recipe_log_id ) {

		global $wpdb;

		$action_log_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->prefix}uap_action_log
				WHERE automator_action_id = %d
				AND automator_recipe_log_id = %d
				ORDER BY ID DESC",
				$action_id,
				$recipe_log_id
			)
		);

		return ! empty( $action_log_id ) ? absint( $action_log_id ) : 0;

	}

	/**
	 * Checks if the currently detected tokens is an action token or not.
	 *
	 * @param array $token_pieces The token pieces.
	 *
	 * @return bool True if action token. Otherwise, false.
	 */
	private function is_action_token( $token_pieces = array() ) {

		$token_pieces_count = 4; // For readability, since Traits can't have constants.

		// Make sure tokens have 4 parts.
		$has_four_parts = count( $token_pieces ) === $token_pieces_count;

		// That second arg is numeric.
		$second_arg_is_numeric = is_numeric( $token_pieces[1] );

		// Begins with either ACTION_META or ACTION_FIELD (case-sensitive).
		$has_correct_prefix = in_array( $token_pieces[0], array( 'META', 'FIELD' ), true );

		return $has_four_parts && $second_arg_is_numeric && $has_correct_prefix;

	}

	/**
	 * Check whether the 4th part of the token is correctly separated or not.
	 *
	 * @param $_4th_level_token_args The exploded 4th part of the token args.
	 *
	 * @return boolean True if the 4th part has 3 parts and the second arg is numeric. Otherwise, false.
	 */
	private function is_correctly_separated( $_4th_level_token_args = array() ) {

		// Required. Use isset, empty can return false if args = '0'.
		if ( ! isset( $_4th_level_token_args[1] ) ) {
			return false;
		}

		// Must be numeric.
		if ( ! is_numeric( $_4th_level_token_args[1] ) ) {
			return false;
		}

		// The 4th level token arguments must return 3 parts.
		if ( 3 !== count( $_4th_level_token_args ) ) {
			return false;
		}

		return true;

	}
}
