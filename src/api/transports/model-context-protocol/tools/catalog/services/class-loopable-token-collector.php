<?php
/**
 * Loopable Token Collector Service.
 *
 * Handles collection of loopable token components for the Automator Explorer.
 * Retrieves tokens from the Integration Token Registry and filters by type='loopable'.
 *
 * @package Uncanny_Automator
 * @since 7.0.0
 */

declare(strict_types=1);

namespace Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog\Services;

use Uncanny_Automator\Api\Components\Search\Shared\Component_Availability;
use Uncanny_Automator\Api\Components\Search\Loopable_Token\Loopable_Token_Search_Result;
use Uncanny_Automator\Api\Components\Search\Loopable_Token\Loopable_Token_Search_Result_Collection;
use Uncanny_Automator\Api\Components\Token\Integration\Registry\WP_Integration_Token_Registry;

/**
 * Service for collecting loopable token search results.
 */
class Loopable_Token_Collector {

	/**
	 * Token registry.
	 *
	 * @var WP_Integration_Token_Registry
	 */
	private WP_Integration_Token_Registry $registry;

	/**
	 * Cached integration names.
	 *
	 * @var array<string, string>|null
	 */
	private ?array $integration_names_cache = null;

	/**
	 * Constructor.
	 *
	 * @param WP_Integration_Token_Registry|null $registry Optional registry instance.
	 */
	public function __construct( ?WP_Integration_Token_Registry $registry = null ) {
		$this->registry = $registry ?? new WP_Integration_Token_Registry();
	}

	/**
	 * Collect loopable token candidates for a search query.
	 *
	 * Performs basic text matching on token names since loopable tokens
	 * are not indexed in the RAG system.
	 *
	 * @param string $query Search term.
	 * @return Loopable_Token_Search_Result_Collection Collection of loopable token results.
	 */
	public function collect_loopable_tokens( string $query ): Loopable_Token_Search_Result_Collection {
		$all_tokens = $this->get_all_loopable_tokens();

		if ( empty( $all_tokens ) ) {
			return Loopable_Token_Search_Result_Collection::empty();
		}

		// Filter by query if provided.
		if ( ! empty( $query ) ) {
			$query_lower = strtolower( $query );
			$all_tokens  = array_filter(
				$all_tokens,
				function ( $token ) use ( $query_lower ) {
					$name        = strtolower( $token['name'] ?? '' );
					$integration = strtolower( $token['integration'] ?? '' );
					return strpos( $name, $query_lower ) !== false
						|| strpos( $integration, $query_lower ) !== false;
				}
			);
		}

		if ( empty( $all_tokens ) ) {
			return Loopable_Token_Search_Result_Collection::empty();
		}

		$items = $this->build_search_results( $all_tokens );

		return new Loopable_Token_Search_Result_Collection( $items, count( $all_tokens ) );
	}

	/**
	 * Collect all loopable tokens by integration.
	 *
	 * @param string $integration Integration code.
	 * @param int    $limit       Max results.
	 * @return Loopable_Token_Search_Result_Collection Collection of loopable token results.
	 */
	public function collect_loopable_tokens_by_integration( string $integration, int $limit = 50 ): Loopable_Token_Search_Result_Collection {
		$tokens = $this->registry->get_tokens_by_integration( $integration );

		// Filter to loopable tokens only.
		$loopable_tokens = array_filter(
			$tokens,
			fn( $token ) => ( $token['type'] ?? '' ) === 'loopable'
		);

		if ( empty( $loopable_tokens ) ) {
			return Loopable_Token_Search_Result_Collection::empty();
		}

		// Apply limit.
		$loopable_tokens = array_slice( $loopable_tokens, 0, $limit );

		$items = $this->build_search_results( $loopable_tokens );

		return new Loopable_Token_Search_Result_Collection( $items, count( $loopable_tokens ) );
	}

	/**
	 * Get all loopable tokens from registry.
	 *
	 * @return array Array of loopable token data.
	 */
	private function get_all_loopable_tokens(): array {
		$all_tokens = $this->registry->get_available_tokens();

		return array_filter(
			$all_tokens,
			fn( $token ) => ( $token['type'] ?? '' ) === 'loopable'
		);
	}

	/**
	 * Build search result value objects from raw token data.
	 *
	 * @param array $tokens Raw token data from registry.
	 * @return Loopable_Token_Search_Result[] Array of search result value objects.
	 */
	private function build_search_results( array $tokens ): array {
		$items = array();

		// Get integration names for enrichment.
		$integration_names = $this->get_integration_names();

		foreach ( $tokens as $token ) {
			try {
				$integration_code          = $token['integration'] ?? '';
				$token['integration_name'] = $integration_names[ $integration_code ] ?? $integration_code;

				$availability = $this->get_token_availability( $token );
				$items[]      = Loopable_Token_Search_Result::from_registry_data( $token, $availability );
			} catch ( \InvalidArgumentException $e ) {
				// Skip items with invalid data.
				continue;
			}
		}

		return $items;
	}

	/**
	 * Build token availability info.
	 *
	 * @param array $token Token data.
	 * @return Component_Availability Availability value object.
	 */
	private function get_token_availability( array $token ): Component_Availability {
		// Loopable tokens are available if registered (integration is active).
		return Component_Availability::from_array(
			array(
				'available' => true,
				'reason'    => null,
			)
		);
	}

	/**
	 * Get integration names keyed by code.
	 *
	 * @return array<string, string> Integration names.
	 */
	private function get_integration_names(): array {
		if ( null !== $this->integration_names_cache ) {
			return $this->integration_names_cache;
		}

		if ( ! function_exists( 'Automator' ) ) {
			$this->integration_names_cache = array();
			return $this->integration_names_cache;
		}

		$integrations = Automator()->get_integrations();
		$names        = array();

		foreach ( $integrations as $code => $integration ) {
			$names[ $code ] = $integration['name'] ?? $code;
		}

		$this->integration_names_cache = $names;

		return $this->integration_names_cache;
	}
}
