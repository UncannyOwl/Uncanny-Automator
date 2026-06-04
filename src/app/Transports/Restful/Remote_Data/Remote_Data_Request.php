<?php
/**
 * Remote Data Request DTO.
 *
 * Typed wrapper around WP_REST_Request for remote-data handlers.
 * Provides clean accessors for every field the recipe builder sends.
 *
 * @package Uncanny_Automator
 *
 * @since 7.3
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Restful\Remote_Data;

use WP_REST_Request;

/**
 * Remote Data Request.
 */
final class Remote_Data_Request {

	/**
	 * The underlying REST request.
	 *
	 * @var WP_REST_Request
	 */
	private $request;

	/**
	 * Constructor.
	 *
	 * @param WP_REST_Request $request The REST request.
	 */
	public function __construct( WP_REST_Request $request ) {
		$this->request = $request;
	}

	/**
	 * Get all field values from the request.
	 *
	 * Values are not individually sanitized. Use get_field_value() for
	 * single-key access with sanitization applied.
	 *
	 * Loop-filter and condition forms POST values as field-state envelopes
	 * ({type, value, readable, backup}); trigger/action/closure forms POST
	 * flat scalars. We unwrap envelopes here so handlers see one shape.
	 *
	 * @return array The values object, with field-state envelopes flattened.
	 */
	public function get_values(): array {
		$values = $this->request->get_param( 'values' );

		if ( ! is_array( $values ) ) {
			return array();
		}

		return array_map( array( $this, 'unwrap_field_value' ), $values );
	}

	/**
	 * Collapse a field-state envelope into its scalar value.
	 *
	 * The envelope signature is `array_key_exists('type') && array_key_exists('value')`.
	 * Repeater rows and non-envelope arrays pass through untouched.
	 *
	 * @param mixed $value Raw value from the request.
	 *
	 * @return mixed The unwrapped scalar, or the original value if not an envelope.
	 */
	private function unwrap_field_value( $value ) {
		if ( is_array( $value ) && array_key_exists( 'type', $value ) && array_key_exists( 'value', $value ) ) {
			return $value['value'];
		}

		return $value;
	}

	/**
	 * Get a single sanitized field value from the values object.
	 *
	 * @param string $key The field key.
	 *
	 * @return string The sanitized value or empty string.
	 */
	public function get_field_value( string $key ): string {
		$values = $this->get_values();
		return isset( $values[ $key ] )
			? sanitize_text_field( wp_unslash( $values[ $key ] ) )
			: '';
	}

	/**
	 * Get the recipe ID.
	 *
	 * @return int
	 */
	public function get_recipe_id(): int {
		return absint( $this->request->get_param( 'recipe_id' ) );
	}

	/**
	 * Get the item ID (trigger or action post ID).
	 *
	 * @return int
	 */
	public function get_item_id(): int {
		return absint( $this->request->get_param( 'item_id' ) );
	}

	/**
	 * Get the parent item ID.
	 *
	 * @return int
	 */
	public function get_parent_id(): int {
		return absint( $this->request->get_param( 'parent_id' ) );
	}

	/**
	 * Get the field ID (option code).
	 *
	 * @return string
	 */
	public function get_field_id(): string {
		return sanitize_text_field( (string) $this->request->get_param( 'field_id' ) );
	}

	/**
	 * Get the group ID.
	 *
	 * @return string
	 */
	public function get_group_id(): string {
		return sanitize_text_field( (string) $this->request->get_param( 'group_id' ) );
	}

	/**
	 * Get the field code that triggered this request.
	 *
	 * @return string
	 */
	public function get_triggered_by(): string {
		return sanitize_text_field( (string) $this->request->get_param( 'triggered_by' ) );
	}

	/**
	 * Get the request context.
	 *
	 * @return string One of: 'on-load', 'parent-field-changed', 'refresh-button'.
	 */
	public function get_context(): string {
		return sanitize_text_field( (string) $this->request->get_param( 'context' ) );
	}

	/**
	 * Check if this is a refresh button request.
	 *
	 * @return bool
	 */
	public function is_refresh(): bool {
		return 'refresh-button' === $this->get_context();
	}

	/**
	 * Get the search query for search_options fields.
	 *
	 * @return string
	 */
	public function get_search_query(): string {
		return sanitize_text_field( (string) $this->request->get_param( 'q' ) );
	}

	/**
	 * Get the underlying WP_REST_Request.
	 *
	 * Escape hatch for edge cases where typed accessors are insufficient.
	 *
	 * @return WP_REST_Request
	 */
	public function get_request(): WP_REST_Request {
		return $this->request;
	}
}
