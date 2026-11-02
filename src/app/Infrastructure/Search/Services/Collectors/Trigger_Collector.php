<?php
/**
 * Trigger Collector Service.
 *
 * Handles collection of trigger components for the Automator Explorer.
 * Returns Trigger_Search_Result_Collection value objects instead of raw arrays.
 *
 * @package Uncanny_Automator
 * @since 7.0.0
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Infrastructure\Search\Services\Collectors;

use Uncanny_Automator\App\Bridge\Automator_Trigger_Definition_Bridge;
use Uncanny_Automator\App\Bridge\Trigger_Definition_Bridge;
use Uncanny_Automator\App\Infrastructure\Search\Shared\Component_Availability;
use Uncanny_Automator\App\Infrastructure\Search\Trigger\Alternative_Triggers_Info;
use Uncanny_Automator\App\Infrastructure\Search\Trigger\Trigger_Search_Result;
use Uncanny_Automator\App\Infrastructure\Search\Trigger\Trigger_Search_Result_Collection;
use Uncanny_Automator\App\Integration_Catalog\Integration\Value_Objects\Integration_Required_Tier;
use Uncanny_Automator\App\Integration_Catalog\Services\Integration_Registry_Service;
use Uncanny_Automator\App\Recipe_Builder\Trigger\Services\Utilities\Trigger_Formatter;
use Uncanny_Automator\App\Recipe_Builder\Trigger\Services\Trigger_Registry_Service;
use Uncanny_Automator\App\Plan\Services\Plan_Service;
use Uncanny_Automator\App\Infrastructure\Search\Services\Rag\Rag_Search_Service;

/**
 * Service for collecting trigger search results.
 */
class Trigger_Collector {

	/**
	 * Trigger formatter.
	 *
	 * @var Trigger_Formatter
	 */
	private Trigger_Formatter $formatter;

	/**
	 * Registered trigger definitions, for the flags the trigger registry drops.
	 *
	 * @var Trigger_Definition_Bridge
	 */
	private Trigger_Definition_Bridge $definitions;

	/**
	 * Constructor.
	 *
	 * @param Trigger_Registry_Service       $registry     Trigger registry service.
	 * @param Plan_Service                   $plan_service Plan service.
	 * @param Trigger_Definition_Bridge|null $definitions  Registered definitions (test seam).
	 */
	public function __construct( Trigger_Registry_Service $registry, Plan_Service $plan_service, ?Trigger_Definition_Bridge $definitions = null ) {
		$this->formatter   = new Trigger_Formatter( $plan_service );
		$this->definitions = $definitions ?? new Automator_Trigger_Definition_Bridge();
	}

	/**
	 * Fetch limit for search results.
	 */
	private const FETCH_LIMIT = 10;

	/**
	 * Collect trigger candidates for a search query.
	 *
	 * @param string      $query     Search term.
	 * @param string|null $user_type User type filter for recipe compatibility ('user', 'anonymous', or null).
	 * @return Trigger_Search_Result_Collection|\WP_Error Collection of trigger search results.
	 */
	public function collect_triggers( string $query, ?string $user_type = null ) {
		$filters = array();
		if ( null !== $user_type ) {
			$filters['user_type'] = $user_type;
		}

		$service = Trigger_Registry_Service::instance();
		$result  = $service->find_triggers( $query, $filters, self::FETCH_LIMIT );

		if ( is_wp_error( $result ) ) {
			// A failed catalog request does not mean the catalog is empty.
			return $result;
		}

		$raw_triggers         = $result['triggers'] ?? array();
		$alternative_triggers = $this->build_alternative_triggers_info( $result['alternative_triggers'] ?? null );

		if ( empty( $raw_triggers ) ) {
			return new Trigger_Search_Result_Collection( array(), 0, $alternative_triggers );
		}

		$items = $this->build_search_results( $raw_triggers );

		return new Trigger_Search_Result_Collection( $items, count( $items ), $alternative_triggers );
	}

	/**
	 * Collect all triggers for a specific integration filtered by user_type.
	 *
	 * Uses RAG pkl file directly (no FAISS semantic search).
	 * Returns triggers regardless of installation status.
	 *
	 * @param string      $integration Integration ID (e.g., "WPFORMS", "GF").
	 * @param string|null $user_type   User type filter ('user' or 'anonymous').
	 * @param int         $limit       Maximum triggers to return.
	 * @return Trigger_Search_Result_Collection|\WP_Error Collection of trigger search results.
	 */
	public function collect_triggers_by_integration( string $integration, ?string $user_type = null, int $limit = 50 ) {
		$rag_service = new Rag_Search_Service();
		$result      = $rag_service->list_by_integration( $integration, 'trigger', $limit );

		if ( is_wp_error( $result ) ) {
			// A failed catalog request does not mean the catalog is empty.
			return $result;
		}

		$raw_triggers = $result['results'] ?? array();

		if ( empty( $raw_triggers ) ) {
			return Trigger_Search_Result_Collection::empty();
		}

		// Filter by user_type if provided.
		// RAG data uses requires_user_data (bool).
		if ( null !== $user_type ) {
			$raw_triggers = array_filter(
				$raw_triggers,
				function ( $trigger ) use ( $user_type ) {
					$requires_user = $trigger['requires_user_data'] ?? true;

					if ( 'user' === $user_type ) {
						return true === $requires_user;
					}

					return false === $requires_user; // anonymous
				}
			);

			$raw_triggers = array_values( $raw_triggers );
		}

		$items = $this->build_search_results( $raw_triggers );

		return new Trigger_Search_Result_Collection( $items, count( $items ) );
	}

	/**
	 * Collect every registered trigger, for the deterministic listing.
	 *
	 * Reads the local registry rather than the hosted index, and returns the
	 * same item as a search, so an Agent reads `code` and `integration_id`
	 * whichever mode found the trigger. The registry's trigger rows carry no
	 * Pro flags, so the tier comes from the registered definition.
	 *
	 * @since 8.0.0
	 *
	 * @return Trigger_Search_Result_Collection|\WP_Error Collection, or the registry's failure.
	 */
	public function collect_registered_triggers() {
		// A limit of 0 lists them all; the service's default is a short sample.
		$result = Trigger_Registry_Service::instance()->list_triggers( array( 'limit' => 0 ) );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$candidates = array();
		foreach ( $result['triggers'] ?? array() as $row ) {
			$code         = (string) ( $row['trigger_code'] ?? '' );
			$integration  = (string) ( $row['integration'] ?? '' );
			$candidates[] = array(
				'code'             => $code,
				// Definitions store their name escaped for HTML; an Agent reads text.
				'sentence'         => wp_specialchars_decode( (string) ( $row['sentence_human_readable'] ?? '' ), ENT_QUOTES ),
				'integration_id'   => $integration,
				'integration_name' => Integration_Registry_Service::get_instance()->get_integration( $integration )['name'] ?? $integration,
				'required_tier'    => Integration_Required_Tier::from_registry_flags( $this->definitions->get_trigger_definition( $code ) ?? array() )->get_value(),
			);
		}

		$items = $this->build_search_results( $candidates );

		return new Trigger_Search_Result_Collection( $items, count( $items ) );
	}

	/**
	 * Build search result value objects from raw trigger data.
	 *
	 * Skips items that fail validation (e.g., empty code, invalid tier).
	 * This is defensive - bad data in RAG index should not break search.
	 *
	 * Skips deprecated triggers too, which are not for new work, checked against
	 * this site's definition because the hosted catalog doesn't know. A recipe
	 * that already uses one still reads its schema through get_component_schema.
	 *
	 * Trade-off: left out rather than flagged, because a flag can be ignored and
	 * the old trigger can rank above its replacement. So an Agent can't find a
	 * deprecated trigger by search; it learns the code from the recipe using it.
	 *
	 * Trade-off: a trigger this site hasn't registered, such as one from an
	 * inactive plugin, has no definition to check. It stays in the results, so an
	 * Agent can say what to activate, even if that trigger is deprecated.
	 *
	 * Trade-off: a refused sentence, such as one over the length limit, is left
	 * out with no error rather than failing the whole list.
	 *
	 * @param array $raw_triggers Raw trigger data from registry/RAG.
	 * @return Trigger_Search_Result[] Array of search result value objects.
	 */
	private function build_search_results( array $raw_triggers ): array {
		$items = array();

		foreach ( $raw_triggers as $trigger ) {
			$definition = $this->definitions->get_trigger_definition( (string) ( $trigger['code'] ?? '' ) );
			if ( ! empty( $definition['is_deprecated'] ) ) {
				continue;
			}

			try {
				$availability = $this->formatter->check_trigger_availability( $trigger );
				$items[]      = Trigger_Search_Result::from_rag_result( $trigger, $availability );
			} catch ( \InvalidArgumentException $e ) {
				// Skip items with invalid data (empty code, bad tier, etc.).
				continue;
			}
		}

		return $items;
	}

	/**
	 * Build Alternative_Triggers_Info value object from array data.
	 *
	 * @param array|null $data Alternative triggers data.
	 * @return Alternative_Triggers_Info|null
	 */
	private function build_alternative_triggers_info( ?array $data ): ?Alternative_Triggers_Info {
		if ( empty( $data ) ) {
			return null;
		}

		return Alternative_Triggers_Info::from_array( $data );
	}
}
