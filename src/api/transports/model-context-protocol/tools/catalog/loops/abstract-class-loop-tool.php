<?php
/**
 * Abstract base class for MCP loop tools.
 *
 * Provides shared functionality for loop add/update operations.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog\Loops;

use Uncanny_Automator\Api\Presentation\Loop\Loop_Token_Sentence_Composer;
use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Abstract_MCP_Tool;
use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog\Services\Loopable_Token_Collector;

/**
 * Abstract Loop Tool.
 *
 * Base class for loop CRUD operations via MCP.
 * Handles shared logic for token enumeration, JSON parsing, and backup building.
 *
 * @since 7.0.0
 */
abstract class Abstract_Loop_Tool extends Abstract_MCP_Tool {

	/**
	 * Get available loopable tokens for schema enum.
	 *
	 * Collects all loopable tokens from the system for use in JSON schema enum.
	 *
	 * @return array Array of loopable token IDs.
	 */
	protected function get_loopable_token_enum(): array {
		$collector  = new Loopable_Token_Collector();
		$collection = $collector->collect_loopable_tokens( '' );

		if ( $collection->is_empty() ) {
			return array();
		}

		// Return just the IDs for the enum.
		return array_map(
			fn( $item ) => $item['id'],
			$collection->to_array()
		);
	}

	/**
	 * Build backup structure for token loops.
	 *
	 * Creates the sentence_html that displays the token name in the UI.
	 * Includes icon from token or integration for visual display.
	 *
	 * @param string $token_id The loopable token ID (e.g., TOKEN_EXTENDED:DATA_TOKEN_ACTIVE_SUBSCRIPTION:...).
	 * @return array Backup structure with sentence and sentence_html.
	 */
	protected function build_token_backup( string $token_id ): array {
		// Parse token ID: TOKEN_EXTENDED:DATA_TOKEN_{ID}:{TYPE}:{INTEGRATION}:{TOKEN_ID}
		$parts = explode( ':', $token_id );

		if ( count( $parts ) < 5 ) {
			return array();
		}

		$integration_code = $parts[3] ?? '';
		$loopable_id      = $parts[4] ?? '';

		// Get token info from collector.
		$collector  = new Loopable_Token_Collector();
		$collection = $collector->collect_loopable_tokens( '' );
		$tokens     = $collection->to_array();

		$token_name = $loopable_id; // Fallback to ID.
		$icon_url   = '';

		// Find matching token.
		foreach ( $tokens as $token ) {
			if ( ( $token['id'] ?? '' ) === $token_id ) {
				$token_name = $token['name'] ?? $loopable_id;
				$icon_url   = $token['icon'] ?? '';
				break;
			}
		}

		// Get integration icon if token icon not available.
		if ( empty( $icon_url ) && function_exists( 'Automator' ) ) {
			$integration = Automator()->get_integration( $integration_code );
			$icon_url    = $integration['icon_svg'] ?? $integration['icon'] ?? '';
		}

		$composer = new Loop_Token_Sentence_Composer();

		return array(
			'sentence'      => '{{Token:TOKEN}}',
			'sentence_html' => $composer->compose( $token_name, $icon_url ),
		);
	}

	/**
	 * Parse JSON parameter that might be a string.
	 *
	 * @param mixed $param Parameter value.
	 * @return array Parsed array.
	 */
	protected function parse_json_param( $param ): array {
		if ( is_string( $param ) ) {
			$decoded = json_decode( $param, true );
			return ( json_last_error() === JSON_ERROR_NONE && is_array( $decoded ) ) ? $decoded : array();
		}
		return is_array( $param ) ? $param : array();
	}
}
