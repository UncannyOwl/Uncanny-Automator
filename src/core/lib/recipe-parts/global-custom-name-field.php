<?php
/**
 * Add a global "Custom name" field to all triggers and actions.
 *
 * This allows users to optionally name recipe steps for easier management without affecting functionality.
 *
 * @package Uncanny_Automator
 */

namespace Uncanny_Automator;

/**
 * Class Global_Custom_Name_Field
 *
 * Adds a custom name field to triggers and actions (conditions and loop filters are Pro-only).
 */
class Global_Custom_Name_Field {


	/**
	 * The option code for the custom name field.
	 *
	 * @var string
	 */
	const FIELD_CODE = '_automator_custom_item_name_';

	/**
	 * Initialize the class and register hooks.
	 *
	 * @return void
	 */
	public function __construct() {
		// Rearrange triggers with options to options_group structure (runs before add_custom_name_field)
		add_filter( 'automator_options_callback_response', array( $this, 'rearrange_trigger_options' ), 599, 5 );
		// Add custom name field to triggers and actions via options callback response
		add_filter( 'automator_options_callback_response', array( $this, 'add_custom_name_field' ), 999, 5 );
		// Add token parser for custom name field
		add_filter( 'automator_maybe_parse_token', array( $this, 'parse_custom_name_token' ), PHP_INT_MAX, 6 );
	}

	/**
	 * Rearrange triggers and actions that have options but not options_group.
	 *
	 * Converts the options array to options_group structure using trigger_meta or action_meta as the key.
	 *
	 * @param array    $callback_response The response from the options callback.
	 * @param callable $callback          The original callback function.
	 * @param array    $item              The item (trigger/action) data.
	 * @param array    $recipe            The recipe data.
	 * @param string   $type              The type of item ('triggers' or 'actions').
	 *
	 * @return array Modified callback response with options rearranged to options_group.
	 */
	public function rearrange_trigger_options( $callback_response, $callback, $item, $recipe, $type ) {

		// Only process triggers and actions
		if ( ! in_array( $type, array( 'triggers', 'actions' ), true ) ) {
			return $callback_response;
		}

		// Only process if options exist but options_group doesn't
		if ( ! isset( $callback_response['options'] ) || ! is_array( $callback_response['options'] ) ) {
			return $callback_response;
		}

		if ( isset( $callback_response['options_group'] ) && is_array( $callback_response['options_group'] ) ) {
			return $callback_response;
		}

		// Get meta key (trigger_meta or action_meta) from item
		$meta_key = $this->get_item_meta_from_item( $item, $callback_response, $type );

		if ( empty( $meta_key ) ) {
			return $callback_response;
		}

		// Convert options to options_group structure
		$callback_response['options_group'] = array(
			$meta_key => $callback_response['options'],
		);

		return $callback_response;
	}

	/**
	 * Get trigger_meta or action_meta from item or definition.
	 *
	 * @param array  $item              The item (trigger/action) data.
	 * @param array  $callback_response The callback response to check for existing structure.
	 * @param string $type              The type of item ('triggers' or 'actions').
	 *
	 * @return string|null The meta key (trigger_meta or action_meta) or null if not found.
	 */
	private function get_item_meta_from_item( $item, $callback_response, $type ) {

		$item_code = $item['meta']['code'] ?? '';

		if ( empty( $item_code ) ) {
			return null;
		}

		// Try to get from definition's existing options_group
		if ( 'triggers' === $type ) {
			$definition = Automator()->get_trigger( $item_code );
		} else {
			$definition = Automator()->get_action( $item_code );
		}

		if ( ! empty( $definition['options_group'] ) && is_array( $definition['options_group'] ) ) {
			$keys = array_keys( $definition['options_group'] );
			if ( ! empty( $keys ) ) {
				return $keys[0];
			}
		}

		// Fallback: try to get from first field's option_code if it looks like a meta key
		if ( ! empty( $callback_response['options'] ) && is_array( $callback_response['options'] ) ) {
			$first_field = $callback_response['options'][0] ?? null;
			if ( ! empty( $first_field['option_code'] ) ) {
				// This is a placeholder - user will provide the correct method
				return $first_field['option_code'];
			}
		}

		return null;
	}

	/**
	 * Add custom name field to triggers and actions.
	 *
	 * This filter is called when loading extra options for triggers and actions.
	 *
	 * @param array    $callback_response The response from the options callback.
	 * @param callable $callback          The original callback function.
	 * @param array    $item              The item (trigger/action) data.
	 * @param array    $recipe            The recipe data.
	 * @param string   $type              The type of item ('triggers' or 'actions').
	 *
	 * @return array Modified callback response with custom name field added.
	 */
	public function add_custom_name_field( $callback_response, $callback, $item, $recipe, $type ) {

		// Only process triggers and actions
		if ( ! in_array( $type, array( 'triggers', 'actions' ), true ) ) {
			return $callback_response;
		}

		// Determine the appropriate label based on type
		$label = 'triggers' === $type
		? esc_html_x( 'Custom trigger label', 'Custom name field', 'uncanny-automator' )
		: esc_html_x( 'Custom action label', 'Custom name field', 'uncanny-automator' );

		// Create the custom name field
		$custom_name_field = array(
			'option_code'     => self::FIELD_CODE,
			'label'           => $label,
			'input_type'      => 'text',
			'required'        => false,
			'description'     => esc_html_x( "Optionally, name this step to quickly recognize it in your recipe. It won't change functionality but makes automation easier to manage.", 'Custom name field', 'uncanny-automator' ),
			'placeholder'     => '',
			'supports_tokens' => false,
			'default_value'   => '',
		);

		// Add the field to the response
		// Always add to the fields array (used by JS)
		if ( ! isset( $callback_response['fields'] ) || ! is_array( $callback_response['fields'] ) ) {
			$callback_response['fields'] = array();
		}
		$callback_response['fields'][] = $custom_name_field;

		// Handle different response structures
		if ( isset( $callback_response['options_group'] ) && is_array( $callback_response['options_group'] ) ) {

			$last_group_key = $this->get_last_group_key( $callback_response['options_group'] );

			if ( null !== $last_group_key ) {
				if ( ! isset( $callback_response['options_group'][ $last_group_key ] ) || ! is_array( $callback_response['options_group'][ $last_group_key ] ) ) {
					$callback_response['options_group'][ $last_group_key ] = array();
				}
				$callback_response['options_group'][ $last_group_key ][] = $custom_name_field;
				return $callback_response;
			}

			// No groups exist, create a default one and return
			$callback_response['options_group']['default'] = array( $custom_name_field );
			return $callback_response;
		}

		if ( isset( $callback_response['options'] ) && is_array( $callback_response['options'] ) ) {
			$callback_response['options'][] = $custom_name_field;
			return $callback_response;
		}

		// No options structure exists; create a default options_group and return
		$callback_response['options_group'] = array(
			'default' => array( $custom_name_field ),
		);

		return $callback_response;
	}

	/**
	 * Get the last group key from options_group array.
	 *
	 * @param array $options_group The options group array.
	 *
	 * @return string|int|null The last group key or null if empty.
	 */
	private function get_last_group_key( $options_group ) {
		if ( empty( $options_group ) || ! is_array( $options_group ) ) {
			return null;
		}

		// Get all keys
		$keys = array_keys( $options_group );

		// Return the last key
		return end( $keys );
	}

	/**
	 * Parse the custom name field token.
	 *
	 * @param mixed  $value        The current token value.
	 * @param array  $pieces       Token pieces (integration, trigger/action code, token).
	 * @param int    $recipe_id    The recipe ID.
	 * @param array  $trigger_data The trigger data.
	 * @param int    $user_id      The user ID.
	 * @param array  $replace_args Additional replacement arguments.
	 *
	 * @return mixed The parsed token value or original value if not applicable.
	 */
	public function parse_custom_name_token( $value, $pieces, $recipe_id, $trigger_data, $user_id, $replace_args ) {

		// Check if this is our custom name field token
		if ( ! is_array( $pieces ) || ! in_array( self::FIELD_CODE, $pieces, true ) ) {
			return $value;
		}

		// Get the item ID from pieces (trigger or action ID)
		$item_id = isset( $pieces[0] ) ? absint( $pieces[0] ) : 0;

		if ( empty( $item_id ) ) {
			return '';
		}

		// Get the custom name from post meta
		$custom_name = get_post_meta( $item_id, self::FIELD_CODE, true );

		// Return the custom name if it exists, otherwise return empty string
		return ! empty( $custom_name ) ? $custom_name : '';
	}
}

// Initialize the class
new Global_Custom_Name_Field();
