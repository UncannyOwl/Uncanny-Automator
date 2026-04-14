<?php
/**
 * Abstract base class for MCP filter tools.
 *
 * Provides shared functionality for filter add/update operations,
 * delegating to domain services for all business logic.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog\Loops;

use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Abstract_MCP_Tool;
use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Json_Rpc_Response;
use Uncanny_Automator\Api\Services\Field\Field_Mcp_Input_Resolver;
use Uncanny_Automator\Api\Services\Field\Utilities\Field_Validator;
use Uncanny_Automator\Api\Services\Loop\Filter\Services\Filter_Registry_Service;
use Uncanny_Automator\Api\Components\Loop\Filter\Services\Field_Normalizer;
use Uncanny_Automator\Api\Presentation\Loop\Filters\Loop_Filter_Sentence_Composer;

/**
 * Abstract Filter Tool.
 *
 * Base class for filter CRUD operations via MCP.
 * Handles field validation and transformation using domain services.
 *
 * @since 7.0.0
 */
abstract class Abstract_Filter_Tool extends Abstract_MCP_Tool {

	/**
	 * Filter registry service.
	 *
	 * @var Filter_Registry_Service
	 */
	protected $registry_service;

	/**
	 * Field normalizer domain service.
	 *
	 * @var Field_Normalizer
	 */
	protected $field_normalizer;

	/**
	 * Sentence composer.
	 *
	 * @var Loop_Filter_Sentence_Composer
	 */
	protected $sentence_composer;

	/**
	 * Validate MCP field structure.
	 *
	 * Ensures each field is an object with 'value' and 'label' properties.
	 *
	 * @param array $fields Fields from AI agent in MCP format.
	 * @return array|null Error response if invalid, null if valid.
	 */
	protected function validate_mcp_field_structure( array $fields ): ?array {
		foreach ( $fields as $code => $field ) {
			if ( ! is_array( $field ) ) {
				return Json_Rpc_Response::create_error_response(
					"Field '{$code}' must be an object with {value: string, label: string}. Example: {\"ROLE\": {value: \"subscriber\", label: \"Subscriber\"}}"
				);
			}

			if ( ! isset( $field['value'] ) || ! isset( $field['label'] ) ) {
				return Json_Rpc_Response::create_error_response(
					"Field '{$code}' must have both 'value' and 'label' properties. Got: " . wp_json_encode( $field )
				);
			}
		}

		return null;
	}

	/**
	 * Extract field values for validation.
	 *
	 * Delegates to Field_Normalizer domain service.
	 *
	 * @param array $mcp_fields Fields from AI agent in MCP format.
	 * @return array Values only (for token validation).
	 */
	protected function extract_values_for_validation( array $mcp_fields ): array {
		// Convert MCP format to nested format Field_Normalizer expects.
		$nested = $this->convert_mcp_to_nested_for_normalizer( $mcp_fields );

		return $this->field_normalizer->extract_values_for_validation( $nested );
	}

	/**
	 * Convert MCP format to nested format for Field_Normalizer.
	 *
	 * MCP: {FIELD: {value, label}}
	 * Nested: {FIELD: {value, readable}}
	 *
	 * @param array $mcp_fields Fields in MCP format.
	 * @return array Fields in nested format.
	 */
	private function convert_mcp_to_nested_for_normalizer( array $mcp_fields ): array {
		$nested = array();

		foreach ( $mcp_fields as $code => $field ) {
			$nested[ $code ] = array(
				'value'    => $field['value'],
				'readable' => $field['label'],
			);
		}

		return $nested;
	}

	/**
	 * Build filter backup with sentence HTML.
	 *
	 * Uses Loop_Filter_Sentence_Composer to generate sentence HTML
	 * matching frontend output exactly.
	 *
	 * @param array $mcp_fields Fields from AI agent in MCP format.
	 * @param array $definition Filter definition from registry.
	 * @return array Backup array with sentence and sentence_html.
	 */
	protected function build_filter_backup( array $mcp_fields, array $definition ): array {
		$sentence = $definition['sentence'] ?? '';

		// If no sentence template, return empty backup.
		if ( empty( $sentence ) ) {
			return array();
		}

		// Build field structure for composer.
		$composer_fields = $this->build_composer_fields( $mcp_fields, $definition );

		// Use Loop_Filter_Sentence_Composer to generate sentence HTML.
		$sentence_html = $this->sentence_composer->compose( $sentence, $composer_fields );

		return array(
			'sentence'      => $sentence,
			'sentence_html' => $sentence_html,
		);
	}

	/**
	 * Validate filter field values against the filter's meta_structure schema.
	 *
	 * Converts meta_structure to WordPress field format and delegates to Field_Validator.
	 * Tokens and custom values are handled by Field_Validator's built-in rules.
	 *
	 * @since 7.2.0
	 *
	 * @param array $definition    Filter definition from registry (must include 'meta_structure').
	 * @param array $field_values  Field values to validate (code => value).
	 * @return bool|\WP_Error True if valid, WP_Error if invalid.
	 */
	protected function validate_filter_fields( array $definition, array $field_values ) {

		$meta_structure = $definition['meta_structure'] ?? array();

		if ( empty( $meta_structure ) ) {
			return true;
		}

		$field_definitions = Field_Validator::convert_meta_structure_to_fields( $meta_structure );
		$filter_code       = $definition['code'] ?? '';

		$validator = new Field_Validator();

		return $validator->validate_fields( $field_definitions, $field_values, $filter_code, 'loop_filter' );
	}

	/**
	 * Build field structure for the sentence composer.
	 *
	 * Converts MCP field structure {value, label} into composer structure
	 * with value, readable (from label), and backup from registry.
	 *
	 * @param array $mcp_fields Fields from AI agent in MCP format.
	 * @param array $definition Filter definition from registry.
	 * @return array Field structure for composer.
	 */
	private function build_composer_fields( array $mcp_fields, array $definition ): array {
		$composer_fields = array();
		$meta_structure  = $definition['meta_structure'] ?? array();

		// Build a map of field codes to their config for easy lookup.
		$field_config_map = array();
		foreach ( $meta_structure as $field_config ) {
			$code = $field_config['code'] ?? '';
			if ( ! empty( $code ) ) {
				$field_config_map[ $code ] = $field_config;
			}
		}

		foreach ( $mcp_fields as $code => $field ) {
			$field_config = $field_config_map[ $code ] ?? array();

			$composer_fields[ $code ] = array(
				'value'    => $field['value'] ?? '',
				'readable' => $field['label'] ?? '',
				'backup'   => array(
					'label'                  => $field_config['label'] ?? $code,
					'show_label_in_sentence' => $field_config['show_label_in_sentence'] ?? true,
				),
			);
		}

		return $composer_fields;
	}
}
