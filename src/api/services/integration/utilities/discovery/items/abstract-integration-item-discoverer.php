<?php
/**
 * Integration Item Discoverer
 *
 * Base class for discovering integration items (triggers, actions, conditions, loop filters).
 *
 * @package Uncanny_Automator\Api\Services\Integration\Utilities\Discovery\Items
 * @since 7.0.0
 */

namespace Uncanny_Automator\Api\Services\Integration\Utilities\Discovery\Items;

/**
 * Base class for discovering integration items.
 *
 * Provides common functionality for discovering triggers, actions, conditions, and loop filters.
 *
 * @since 7.0.0
 */
abstract class Integration_Item_Discoverer {

	/**
	 * Current integration code being discovered.
	 *
	 * @var string
	 */
	protected $current_integration_code = '';

	/**
	 * Current integration name.
	 *
	 * @var string
	 */
	protected $current_integration_name = '';

	/**
	 * Discover items for integration.
	 *
	 * @param string $code Integration code
	 * @param string $name Integration name
	 *
	 * @return array Item data
	 */
	abstract public function discover( string $code, string $name ): array;

	/**
	 * Normalize item data structure.
	 *
	 * Converts registry data to standardized format.
	 *
	 * @param array $item Raw item data from registry
	 * @param string $type Item type ('trigger', 'action', 'filter_condition', 'loop_filter')
	 *
	 * @return array Normalized item data
	 */
	protected function normalize_item( array $item, string $type ): array {
		$normalized = array(
			'code'               => $this->get_item_code( $item ),
			'meta'               => $this->get_item_meta( $item, $type ),
			'type'               => $type,
			'framework'          => $this->get_framework( $item ),
			'is_deprecated'      => $this->is_deprecated( $item, $type ),
			'sentence'           => $this->get_sentence( $item, $type ),
			'description'        => $this->get_description( $item, $type ),
			'required_tier'      => $this->get_required_tier( $item, $type ),
			'requires_user_data' => $this->requires_user_data( $item, $type ),
		);

		return $normalized;
	}

	/**
	 * Get item code.
	 *
	 * @param array $item Item data
	 *
	 * @return string Item code
	 */
	abstract protected function get_item_code( array $item ): string;

	/**
	 * Get item meta.
	 *
	 * @param array $item Item data
	 * @param string $type Item type
	 *
	 * @return string Item meta
	 */
	abstract protected function get_item_meta( array $item, string $type ): string;

	/**
	 * Get framework (modern or legacy).
	 *
	 * @param array $item Item data
	 *
	 * @return string Framework ('modern' or 'legacy')
	 */
	protected function get_framework( array $item ): string {
		return ! empty( $item['uses_abstract_class'] ) ? 'modern' : 'legacy';
	}

	/**
	 * Check if item is deprecated.
	 *
	 * @param array $item Item data
	 * @param string $type Item type
	 *
	 * @return bool True if deprecated
	 */
	abstract protected function is_deprecated( array $item, string $type ): bool;

	/**
	 * Get sentence data.
	 *
	 * @param array $item Item data
	 * @param string $type Item type
	 *
	 * @return array Sentence data with 'short' and 'dynamic' keys
	 */
	abstract protected function get_sentence( array $item, string $type ): array;

	/**
	 * Get description data.
	 *
	 * @param array $item Item data
	 * @param string $type Item type
	 *
	 * @return array Description data with 'readable' and 'mcp' keys
	 */
	protected function get_description( array $item, string $type ): array {
		$manifest             = $item['manifest'] ?? array();
		$readable_description = $manifest['readable_description'] ?? '';
		$mcp_description      = $manifest['mcp_description'] ?? '';

		// Fallback to formatted description from subclass.
		if ( empty( $readable_description ) ) {
			$readable_description = $this->get_fallback_description( $item, $type );
		}

		// Fallback to readable description.
		if ( empty( $mcp_description ) ) {
			$mcp_description = $readable_description;
		}

		return array(
			'readable' => $readable_description,
			'mcp'      => $mcp_description,
		);
	}

	/**
	 * Get fallback description from item data.
	 *
	 * Subclasses should override this to provide type-specific fallback source.
	 * The base class will handle formatting (stripping brackets, adding prefixes).
	 *
	 * @param array $item Item data
	 * @return string Raw fallback description (before formatting)
	 */
	abstract protected function get_fallback_description_raw( array $item ): string;

	/**
	 * Get fallback description with formatting.
	 *
	 * Formats the raw fallback description by:
	 * - Stripping brackets
	 * - Adding integration name and item type prefix if needed
	 *
	 * @param array $item Item data
	 * @param string $type Item type
	 *
	 * @return string Formatted fallback description
	 */
	protected function get_fallback_description( array $item, string $type ): string {
		$raw_description = $this->get_fallback_description_raw( $item );
		$description     = $this->strip_brackets( $raw_description );

		if ( empty( $description ) ) {
			return '';
		}

		return $this->format_fallback_description( $description, $type );
	}

	/**
	 * Format fallback description with integration name and item type prefix.
	 *
	 * @param string $description Raw description text
	 * @param string $type Item type
	 *
	 * @return string Formatted description
	 */
	protected function format_fallback_description( string $description, string $type ): string {
		$integration_name = $this->get_integration_name();
		if ( empty( $integration_name ) ) {
			return $description;
		}

		$item_type_label      = $this->get_item_type_label( $type );
		$has_integration_name = stripos( $description, $integration_name ) !== false;

		if ( $has_integration_name ) {
			return sprintf( '%s: %s', $item_type_label, $description );
		}

		return sprintf( '%s - %s: %s', $integration_name, $item_type_label, $description );
	}

	/**
	 * Get integration name from integration code.
	 *
	 * @return string Integration name or empty string
	 */
	protected function get_integration_name(): string {
		return $this->current_integration_name;
	}

	/**
	 * Get human-readable item type label.
	 *
	 * @param string $type Item type
	 *
	 * @return string Item type label
	 */
	protected function get_item_type_label( string $type ): string {
		$labels = array(
			'trigger'          => 'Trigger',
			'action'           => 'Action',
			'filter_condition' => 'Filter Condition',
			'loop_filter'      => 'Loop Filter',
		);

		return $labels[ $type ];
	}

	/**
	 * Strip brackets from readable sentence.
	 *
	 * Removes {{}} brackets and their contents from descriptions.
	 * Example: "Send an {{email}} to user" => "Send an email to user"
	 *
	 * @param string $text Text with brackets
	 *
	 * @return string Text without brackets
	 */
	protected function strip_brackets( string $text ): string {
		// Remove {{text}} brackets but keep the text inside
		// Pattern: {{anything}} or {{anything:CODE}}
		$text = preg_replace( '/\{\{([^:}]+)(?::[^}]+)?\}\}/', '$1', $text );
		return trim( $text );
	}

	/**
	 * Get required tier.
	 *
	 * @param array $item Item data
	 * @param string $type Item type
	 *
	 * @return string Required tier ('lite', 'pro-basic', 'pro-plus', 'pro-elite')
	 */
	abstract protected function get_required_tier( array $item, string $type ): string;

	/**
	 * Check if item requires user data.
	 *
	 * @param array $item Item data
	 * @param string $type Item type
	 *
	 * @return bool True if requires user data
	 */
	abstract protected function requires_user_data( array $item, string $type ): bool;
}
