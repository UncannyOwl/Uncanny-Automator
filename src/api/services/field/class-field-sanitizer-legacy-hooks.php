<?php
/**
 * Legacy Field Code Hooks for Field Sanitizer.
 *
 * Registers field-code-specific hooks to maintain backwards compatibility
 * with legacy automator_sanitize_array() behavior.
 *
 * @since 7.0
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\Api\Services\Field;

use Uncanny_Automator\Api\Components\Field\Field;

/**
 * Field Sanitizer Legacy Hooks Class.
 *
 * Provides backwards compatibility for field-code-specific sanitization
 * behaviors from the legacy automator_sanitize_array() function.
 *
 * Legacy field codes handled:
 * - EMAILFROM, EMAILTO, EMAILCC, EMAILBCC: sanitize_text_field() to preserve tokens
 * - WPCPOSTAUTHOR: sanitize_text_field() to preserve tokens
 * - EMAILBODY: pass-through (no sanitization)
 * - WPCPOSTCONTENT: conditional wp_kses_post() and wp_slash() via filters
 *
 * @since 7.0
 */
class Field_Sanitizer_Legacy_Hooks {

	/**
	 * Register all legacy field code hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		// Email fields: use text sanitization to preserve tokens.
		// Legacy behavior: sanitize_text_field() instead of sanitize_email().
		add_filter( 'automator_field_type_EMAILFROM', array( $this, 'use_text_type' ) );
		add_filter( 'automator_field_type_EMAILTO', array( $this, 'use_text_type' ) );
		add_filter( 'automator_field_type_EMAILCC', array( $this, 'use_text_type' ) );
		add_filter( 'automator_field_type_EMAILBCC', array( $this, 'use_text_type' ) );
		add_filter( 'automator_field_type_WPCPOSTAUTHOR', array( $this, 'use_text_type' ) );

		// Body fields: skip sanitization (HTML pass-through).
		// Legacy behavior: value returned as-is.
		add_filter( 'automator_field_type_EMAILBODY', array( $this, 'use_html_type' ) );
		add_filter( 'automator_field_type_WPCPOSTCONTENT', array( $this, 'use_html_type' ) );

		// WPCPOSTCONTENT: conditional post-sanitization via legacy filters.
		add_filter( 'automator_field_value_sanitized_WPCPOSTCONTENT', array( $this, 'handle_wpcpostcontent' ), 10, 5 );
	}

	/**
	 * Override field type to 'text' for sanitize_text_field() behavior.
	 *
	 * This preserves tokens in email-related fields where the default
	 * sanitize_email() would destroy {{token}} patterns.
	 *
	 * @return string The text field type.
	 */
	public function use_text_type(): string {
		return 'text';
	}

	/**
	 * Override field type to 'html' for pass-through behavior.
	 *
	 * HTML type skips sanitization, allowing rich content to pass through
	 * unchanged. This matches the legacy behavior for body fields.
	 *
	 * @return string The html field type.
	 */
	public function use_html_type(): string {
		return 'html';
	}

	/**
	 * Handle WPCPOSTCONTENT legacy filter compatibility.
	 *
	 * Applies the legacy conditional filters:
	 * - automator_wpcpostcontent_should_sanitize: applies wp_kses_post()
	 * - automator_wpcpostcontent_should_wp_slash: applies wp_slash()
	 *
	 * @param mixed  $sanitized The sanitized value.
	 * @param mixed  $original  The original value before sanitization.
	 * @param string $type      The field type.
	 * @param string $transport The transport identifier.
	 * @param Field  $field     The field object.
	 *
	 * @return mixed Modified value.
	 */
	public function handle_wpcpostcontent( $sanitized, $original, string $type, string $transport, Field $field ) {
		// Build the data array in legacy format for filter compatibility.
		$data = array( 'WPCPOSTCONTENT' => $sanitized );

		/**
		 * Filters whether to sanitize WPCPOSTCONTENT with wp_kses_post().
		 *
		 * @since Legacy
		 *
		 * @param bool  $should_sanitize Whether to apply wp_kses_post(). Default false.
		 * @param array $data            The field data array.
		 */
		if ( apply_filters( 'automator_wpcpostcontent_should_sanitize', false, $data ) ) {
			$sanitized = wp_kses_post( $sanitized );
		}

		/**
		 * Filters whether to apply wp_slash() to WPCPOSTCONTENT.
		 *
		 * @since Legacy
		 *
		 * @param bool  $should_slash Whether to apply wp_slash(). Default false.
		 * @param array $data         The field data array.
		 */
		if ( apply_filters( 'automator_wpcpostcontent_should_wp_slash', false, $data ) ) {
			$sanitized = wp_slash( $sanitized );
		}

		return $sanitized;
	}
}
