<?php
namespace Uncanny_Automator\Services\Recipe\Action\Token;

class Parser {

	/**
	 * The action token meta_value are fetched with matching specific meta_key.
	 *
	 * @var string The meta_key value used in `uap_action_log_meta` table.
	 */
	private $meta_key = 'action_tokens';

	/**
	 * Matches the given context with arguments and replaces the text according to value given. This is an internal method.
	 *
	 * Callback method to `automator_action_token_input_parser_text_field_text` action hook.
	 *
	 * ! @wp_hook automator_action_token_input_parser_text_field_text - See Registry@register_hooks.
	 *
	 * @param string $field_text The context to replace (e.g. email body).
	 * @param array $args The array containing necessary items.
	 * @param array $trigger_args The trigger args.
	 *
	 * @return string The final string after find and replace.
	 */
	public function replace_key_value_pairs( $field_text, $args, $trigger_args ) {

		if ( 'automator_action_token_input_parser_text_field_text' !== current_action() ) {
			_doing_it_wrong( __FUNCTION__, 'Function must be called in the context of filter `automator_action_token_input_parser_text_field_text`.', '6.0' );
		}

		$parser = new Parser();

		return $parser->parse( $field_text, $args, $trigger_args );

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

				$value = $this->get_meta_value( $action_log_id, $meta_key, $args, $process_args, $action_id );

				$replaceables[ $raw ] = $value;

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
	 * @param string $field_text
	 * @param mixed[] $args
	 * @param mixed[] $trigger_args
	 *
	 * @return mixed
	 */
	public function parse( $field_text, $args, $trigger_args ) {

		// Max depth depth of 5.
		$max_iteration = apply_filters( 'automator_action_tokens_interpolate_tokens_with_values_max_iteration', 5 );

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

		$meta_values = Automator()->db->action->get_multiple_meta( $action_log_id, $this->meta_key );

		// Combine the tokens.
		$combined_meta_valued = self::merge_meta_values( $meta_values );

		$action_meta_token = $this->stringify( apply_filters( 'automator_action_tokens_meta_token_value', $combined_meta_valued, $action_id, $process_args ) );

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
	 * Merges meta_value fields from an array into a single JSON object.
	 *
	 * @param array $meta_values Array of meta_value fields to merge.
	 * @return string A JSON object string.
	 */
	public static function merge_meta_values( $meta_values ) {
		// Initialize an empty array to store the merged data.
		$merged_data = array();

		// Loop through the array and decode each 'meta_value' JSON string into an array.
		foreach ( $meta_values as $item ) {
			$meta_value_array = json_decode( $item['meta_value'], true );
			if ( is_array( $meta_value_array ) ) {
				// Merge the current array into the merged data array.
				$merged_data = array_merge( $merged_data, $meta_value_array );
			}
		}

		// Encode the merged array back into a JSON string.
		return wp_json_encode( $merged_data );
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
	public function get_action_log_id( $action_id, $recipe_log_id ) {

		return absint( Automator()->db->action->get_action_log_id( $action_id, $recipe_log_id ) );

	}

	/**
	 * Checks if the currently detected tokens is an action token or not.
	 *
	 * @param array $token_pieces The token pieces.
	 *
	 * @return bool True if action token. Otherwise, false.
	 */
	public function is_action_token( $token_pieces = array() ) {

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
			$value = $this->handle_custom_value( $value, $action_meta );
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
	 * Handle automator_custom_value_input.
	 *
	 * @param  object $value
	 * @return object
	 */
	public function handle_custom_value( $value, $action_meta ) {

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
