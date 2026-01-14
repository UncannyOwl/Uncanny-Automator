<?php
declare(strict_types=1);

namespace Uncanny_Automator\Api\Transports\Restful\Recipe\Items\Traits\Hooks;

use WP_REST_Request;

/**
 * Trait for deprecated update operation hooks.
 *
 * Consolidates all backwards compatibility for deprecated hooks.
 * Remove this trait when dropping support for deprecated hooks.
 *
 * @since 7.0
 */
trait Deprecated_Hook_Update_Operations {

	/**
	 * The primary meta key for this item type.
	 *
	 * @var string
	 */
	private string $primary_meta_key = '';

	/**
	 * The value of the primary meta key before update.
	 *
	 * @var mixed
	 */
	private $before_update_value = '';

	/**
	 * The generated deprecated update request.
	 *
	 * @var WP_REST_Request|null
	 */
	private ?WP_REST_Request $deprecated_update_request = null;

	/**
	 * Prepare deprecated hooks context and dispatch before update hooks.
	 *
	 * @param string $primary_meta_key The primary meta key for this item type.
	 *
	 * @return void
	 */
	protected function dispatch_deprecated_before_update_hooks( string $primary_meta_key ): void {
		$this->primary_meta_key = $primary_meta_key;

		$item_id = $this->get_item_id();
		if ( ! empty( $item_id ) && ! empty( $primary_meta_key ) ) {
			$this->before_update_value = get_post_meta( (int) $item_id, $primary_meta_key, true );
		}

		$item = $this->get_item_post();
		if ( null === $item ) {
			return;
		}

		$legacy_request = $this->get_deprecated_update_request();

		// Note - we're calling later in the process because we need validated data to generate the request in legacy format.
		do_action_deprecated(
			'automator_recipe_before_options_update',
			array( $legacy_request ),
			'7.0',
			'automator_recipe_item_update_pre_save',
			esc_html( $this->get_deprecated_hook_rest_message() )
		);

		do_action_deprecated(
			'automator_recipe_before_update',
			array( $item, $legacy_request ),
			'7.0',
			'automator_recipe_item_update_pre_save',
			esc_html( $this->get_deprecated_hook_rest_message() )
		);
	}

	/**
	 * Dispatch deprecated option updated hooks.
	 *
	 * @param array $config The config array that was saved.
	 *
	 * @return void
	 */
	protected function dispatch_deprecated_option_updated_hooks( array $config ): void {
		$item_post  = $this->get_item_post();
		$recipe_id  = $this->get_recipe_id();
		$meta_key   = $this->primary_meta_key;
		$meta_value = $config[ $meta_key ] ?? '';

		do_action_deprecated(
			'automator_recipe_option_updated_before_cache_is_cleared',
			array( $item_post, $recipe_id ),
			'7.0',
			'automator_recipe_item_option_updated_before_cache_cleared',
			esc_html( $this->get_deprecated_hook_rest_message() )
		);

		$response = array(
			'message'        => 'Option updated!',
			'success'        => true,
			'action'         => 'updated_option',
			'data'           => array( $item_post, $meta_key, $meta_value ),
			'recipes_object' => $this->get_recipe_object_legacy(),
			'_recipe'        => $this->get_recipe_object(),
		);

		do_action_deprecated(
			'automator_recipe_option_updated',
			array(
				$item_post,
				$meta_key,
				$meta_value,
				$this->before_update_value,
				$recipe_id,
				$response,
			),
			'7.0',
			'automator_recipe_item_option_updated_before_cache_cleared',
			esc_html( $this->get_deprecated_hook_rest_message() )
		);

		apply_filters_deprecated(
			'automator_option_updated',
			array( $response, $item_post, $meta_key, $meta_value ),
			'7.0',
			'automator_recipe_item_option_updated_before_cache_cleared',
			esc_html_x(
				'The automator_option_updated filter return structure has changed. Use automator_recipe_item_option_updated_before_cache_cleared action instead.',
				'Restful API',
				'uncanny-automator'
			)
		);
	}

	/**
	 * Get the deprecated hook rest message.
	 *
	 * @return string
	 */
	private function get_deprecated_hook_rest_message(): string {
		return esc_html_x(
			'The REST request structure for recipe items has changed significantly and no longer matches the previous format.',
			'Restful API',
			'uncanny-automator'
		);
	}

	/**
	 * Get the deprecated update request.
	 *
	 * @return WP_REST_Request
	 */
	private function get_deprecated_update_request(): WP_REST_Request {
		if ( null === $this->deprecated_update_request ) {
			$this->deprecated_update_request = $this->build_deprecated_update_request();
		}

		return $this->deprecated_update_request;
	}

	/**
	 * Build the deprecated update request.
	 *
	 * @return WP_REST_Request
	 */
	private function build_deprecated_update_request(): WP_REST_Request {
		$request     = $this->get_request();
		$item_type   = $this->get_item_type();
		$option_code = $this->primary_meta_key;

		$fields = $request->get_param( 'fields' ) ?? array();
		if ( is_string( $fields ) ) {
			$fields = json_decode( $fields, true ) ?? array();
		}

		$legacy_request = new WP_REST_Request( $request->get_method(), $request->get_route() );

		$legacy_request->set_param( 'recipe_id', $this->get_recipe_id() );
		$legacy_request->set_param( 'itemId', $this->get_item_id() );
		$legacy_request->set_param( 'optionCode', $option_code );
		$legacy_request->set_param( 'doing_rest', 1 );
		$legacy_request->set_param( 'optionValue', $this->build_legacy_option_value( $fields ) );
		$legacy_request->set_param( 'options', $this->build_legacy_options_for_deprecated_hook( $fields, $option_code ) );

		if ( 'trigger' === $item_type ) {
			$definition = $this->get_item_definition();
			if ( null !== $definition ) {
				$legacy_request->set_param( 'trigger_item_code', $definition->to_array()['code'] ?? '' );
			}
		}

		return $legacy_request;
	}

	/**
	 * Build the legacy option value.
	 *
	 * @param array $fields
	 *
	 * @return array
	 */
	private function build_legacy_option_value( array $fields ): array {
		$option_value = array();

		foreach ( $fields as $field_code => $field_data ) {
			if ( ! is_array( $field_data ) ) {
				continue;
			}

			$value    = $field_data['value'] ?? '';
			$readable = $field_data['readable'] ?? '';
			$type     = $field_data['type'] ?? 'text';

			if ( ! empty( $readable ) ) {
				$option_value[ $field_code . '_readable' ] = $readable;
			}

			if ( 'repeater' === $type && is_array( $value ) ) {
				$option_value[ $field_code ] = wp_json_encode( $value );
				continue;
			}

			$option_value[ $field_code ] = $value;
		}

		return $option_value;
	}

	/**
	 * Build the legacy options for deprecated hooks.
	 *
	 * Uses the shared Legacy_Options_Builder to build the options structure
	 * matching what the frontend sends via $request->get_param('options').
	 *
	 * @param array  $fields      Fields from REST request.
	 * @param string $option_code The option code (groupCode for legacy format).
	 *
	 * @return array Legacy options with groupCode and fields.
	 */
	private function build_legacy_options_for_deprecated_hook( array $fields, string $option_code ): array {
		// Ensure item_code is populated from meta if not already set.
		if ( empty( $this->get_item_code() ) && ! empty( $this->get_item_id() ) ) {
			$this->set_item_code_from_meta();
		}

		$item_type = $this->get_item_type();
		$item_code = $this->get_item_code();

		// Use the shared legacy options builder with groupCode.
		if ( ! empty( $item_type ) && ! empty( $item_code ) ) {
			return $this->build_legacy_options_from_fields( $fields, $item_type, $item_code, $option_code );
		}

		// Fallback: build basic structure without definition flags.
		$legacy_fields = array();

		foreach ( $fields as $field_code => $field_data ) {
			if ( ! is_array( $field_data ) ) {
				continue;
			}

			$legacy_field = array(
				'type'  => $field_data['type'] ?? 'text',
				'value' => $field_data['value'] ?? '',
			);

			if ( ! empty( $field_data['readable'] ) ) {
				$legacy_field['value_readable'] = $field_data['readable'];
			}

			$legacy_fields[ $field_code ] = $legacy_field;
		}

		return array(
			'groupCode' => $option_code,
			'fields'    => $legacy_fields,
		);
	}
}
