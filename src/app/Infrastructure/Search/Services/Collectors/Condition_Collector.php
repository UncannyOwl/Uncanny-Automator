<?php
/**
 * Condition Collector Service.
 *
 * Handles collection of condition components for the Automator Explorer.
 * Returns Condition_Search_Result_Collection value objects instead of raw arrays.
 *
 * @package Uncanny_Automator
 * @since 7.0.0
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Infrastructure\Search\Services\Collectors;

use Uncanny_Automator\App\Infrastructure\Search\Shared\Component_Availability;
use Uncanny_Automator\App\Infrastructure\Search\Condition\Condition_Search_Result;
use Uncanny_Automator\App\Infrastructure\Search\Condition\Condition_Search_Result_Collection;
use Uncanny_Automator\App\Integration_Catalog\Integration\Value_Objects\Integration_Required_Tier;
use Uncanny_Automator\App\Recipe_Builder\Condition\Services\Condition_Registry_Service;

/**
 * Service for collecting condition search results.
 */
class Condition_Collector {

	/**
	 * Fetch limit for search results.
	 */
	private const FETCH_LIMIT = 10;

	/**
	 * Collect condition candidates for a search query.
	 *
	 * @param string $query Search term.
	 * @return Condition_Search_Result_Collection|\WP_Error Collection of condition search results.
	 */
	public function collect_conditions( string $query ) {
		$service = Condition_Registry_Service::instance();
		$result  = $service->find_conditions( $query, array(), self::FETCH_LIMIT );

		if ( is_wp_error( $result ) ) {
			// A failed condition search does not mean there are no conditions.
			return $result;
		}

		$raw_conditions = $result['conditions'] ?? array();

		if ( empty( $raw_conditions ) ) {
			return Condition_Search_Result_Collection::empty();
		}

		$items = $this->build_search_results( $raw_conditions );

		return new Condition_Search_Result_Collection( $items, count( $items ) );
	}

	/**
	 * Collect every registered condition, for the deterministic listing.
	 *
	 * Reads the local registry rather than the hosted index, and returns the
	 * same item as a search, so an Agent reads `code` and `integration_id`
	 * whichever mode found the condition.
	 *
	 * @since 8.0.0
	 *
	 * @param string|null $integration Integration code to list, or null for all.
	 * @return Condition_Search_Result_Collection|\WP_Error Collection, or the registry's failure.
	 */
	public function collect_registered_conditions( ?string $integration = null ) {
		$filters = null === $integration || '' === $integration ? array() : array( 'integration' => $integration );
		$result  = Condition_Registry_Service::instance()->list_conditions( $filters );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$candidates = array();
		foreach ( $result['conditions'] ?? array() as $row ) {
			$candidates[] = array(
				'code'             => $row['condition_code'] ?? '',
				// Definitions store their name escaped for HTML; an Agent reads text.
				'sentence'         => wp_specialchars_decode( (string) ( $row['name'] ?? '' ), ENT_QUOTES ),
				'integration_id'   => $row['integration_code'] ?? '',
				'integration_name' => $row['integration_name'] ?? $row['integration_code'] ?? '',
				'required_tier'    => Integration_Required_Tier::from_registry_flags( $row )->get_value(),
				'deprecated'       => ! empty( $row['deprecated'] ),
			);
		}

		$items = $this->build_search_results( $candidates );

		return new Condition_Search_Result_Collection( $items, count( $items ) );
	}

	/**
	 * Build search result value objects from raw condition data.
	 *
	 * Skips items that fail validation (e.g., empty code, invalid tier).
	 * This is defensive - bad data in RAG index should not break search.
	 *
	 * Skips deprecated conditions too, which are not for new work. A recipe that
	 * already uses one still reads its schema through get_component_schema.
	 *
	 * Trade-off: left out rather than flagged, because a flag can be ignored and
	 * the old condition can rank above its replacement. So an Agent can't find a
	 * deprecated condition by search; it learns the code from the recipe using it.
	 *
	 * Trade-off: a refused sentence, such as one over the length limit, is left
	 * out with no error rather than failing the whole list.
	 *
	 * @param array $raw_conditions Raw condition data from registry/RAG.
	 * @return Condition_Search_Result[] Array of search result value objects.
	 */
	private function build_search_results( array $raw_conditions ): array {
		$items = array();

		foreach ( $raw_conditions as $condition ) {
			if ( $this->is_deprecated( $condition ) ) {
				continue;
			}

			try {
				$availability = $this->get_condition_availability( $condition );
				$items[]      = Condition_Search_Result::from_rag_result( $condition, $availability );
			} catch ( \InvalidArgumentException $e ) {
				// Skip items with invalid data (empty code, bad tier, etc.).
				continue;
			}
		}

		return $items;
	}

	/**
	 * Whether this condition is deprecated on this site.
	 *
	 * Registry rows say so themselves. The hosted catalog doesn't know, so its
	 * rows are checked against the site's definition.
	 *
	 * Trade-off: a condition this site hasn't registered, such as one from an
	 * inactive plugin, has no definition to check. It stays in the results, so an
	 * Agent can say what to activate, even if that condition is deprecated.
	 *
	 * @param array $condition Condition data.
	 * @return bool
	 */
	private function is_deprecated( array $condition ): bool {
		if ( isset( $condition['deprecated'] ) ) {
			return (bool) $condition['deprecated'];
		}

		$definition = Condition_Registry_Service::instance()->get_condition_definition(
			(string) ( $condition['integration_id'] ?? $condition['integration_code'] ?? '' ),
			(string) ( $condition['code'] ?? $condition['condition_code'] ?? '' )
		);

		return ! is_wp_error( $definition ) && ! empty( $definition['condition']['deprecated'] );
	}

	/**
	 * Build condition availability info.
	 *
	 * @param array $condition Condition data.
	 * @return Component_Availability Availability value object.
	 */
	private function get_condition_availability( array $condition ): Component_Availability {
		$integration   = $condition['integration_id'] ?? $condition['integration_code'] ?? '';
		$code          = $condition['code'] ?? $condition['condition_code'] ?? '';
		$required_tier = $condition['required_tier'] ?? 'lite';

		$registry       = Condition_Registry_Service::instance();
		$condition_data = array(
			'integration_id' => $integration,
			'code'           => $code,
			'required_tier'  => $required_tier,
		);

		$availability_data = $registry->check_condition_integration_availability( $condition_data );

		return Component_Availability::from_array( $availability_data );
	}
}
