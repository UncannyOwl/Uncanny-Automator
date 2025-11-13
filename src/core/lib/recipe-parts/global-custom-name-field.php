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
		// Add custom name field to triggers and actions via options callback response
		add_filter( 'automator_options_callback_response', array( $this, 'add_custom_name_field' ), 999, 5 );
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
}

// Initialize the class
new Global_Custom_Name_Field();
