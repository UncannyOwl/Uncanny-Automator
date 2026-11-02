<?php
declare(strict_types=1);
namespace Uncanny_Automator\App\Integration_Catalog\Services\Utilities\Popularity;

use Uncanny_Automator\App\Infrastructure\Exceptions\Store_Exception;
use Uncanny_Automator\App\Integration_Catalog\Integration\Enums\Block_Modifier_Types;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Wire_Serializer\Enums\Wire_Node_Type;

/**
 * Stored site-local usage census per (integration_code, code) for a kind+scope.
 *
 * Each "INTEGRATION:CODE" carries `published` (live entities in published
 * recipes), `all` (every entity in a published or draft recipe, switched-off
 * items included) and `last_used` (GMT unix timestamp of the
 * newest counted entity). Suggestions read the published counts through
 * get_usage_counts(); the scoped catalog reads all + last_used per
 * integration through get_integration_usage().
 *
 * Every scope counts through Scope_Usage_Counts::census(), at any nesting
 * depth. filter_condition unions the new uo-block-modifier
 * store with the draining legacy actions_conditions JSON; the two are
 * disjoint per recipe because the migrator MOVES (not copies) the JSON on
 * migration, so the union is a key-wise sum (no per-recipe dedupe needed).
 * Revisit if migration ever copies.
 *
 * @package Uncanny_Automator\App\Integration_Catalog\Services\Utilities\Popularity
 */
class Entity_Popularity_Calculator {

	/**
	 * Census option prefix; one non-autoloaded option per "kind_scope".
	 *
	 * @var string
	 */
	const OPTION_PREFIX = 'automator_entity_usage_census_';

	/**
	 * Dirty-flag option prefix; flag presence means the scope's counts are
	 * stale and a rebuild is owed. Per-scope flag options avoid the
	 * read-modify-write race a shared array would have under parallel saves.
	 *
	 * @var string
	 */
	const DIRTY_PREFIX = 'automator_entity_usage_counts_dirty_';

	/**
	 * Every kind => scopes pair the cache stores. Write, read, dirty-marking
	 * and rebuild all enumerate from here so the surfaces cannot drift.
	 *
	 * @var array<string,array<int,string>>
	 */
	const SCOPES = array(
		Wire_Node_Type::ITEM           => array( 'trigger', 'action' ),
		Wire_Node_Type::BLOCK_MODIFIER => array( 'loop_filter', 'filter_condition' ),
	);

	/**
	 * In-request memo of census rows keyed by "kind_scope".
	 *
	 * @var array<string,array<string,array{published:int,all:int,last_used:int}>>
	 */
	private $memo = array();

	/**
	 * Whether this instance already registered its shutdown rebuild.
	 *
	 * @var bool
	 */
	private $rebuild_scheduled = false;

	/**
	 * Shared instance.
	 *
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * Shared calculator for the request.
	 *
	 * The shutdown-rebuild guard is per-instance, so callers that construct a
	 * fresh calculator each time register a fresh `shutdown` listener each
	 * time. Going through here keeps that to one per request, which is what
	 * the coalescing relies on. Direct construction stays available for tests
	 * that want an isolated instance.
	 *
	 * @return self
	 */
	public static function instance(): self {
		return self::$instance ??= new self();
	}

	/**
	 * Return published usage counts per "INTEGRATION:CODE" for the given kind and scope.
	 *
	 * Codes with no published use are left out, so a code used only in draft
	 * recipes or by switched-off items never reaches a suggestion.
	 *
	 * @param string $kind  Bucket identifier (e.g. 'item', 'block_modifier').
	 * @param string $scope Scope constant (Integration_Item_Types or Block_Modifier_Types).
	 *
	 * @return array<string,int> "INTEGRATION:CODE" => count
	 */
	public function get_usage_counts( string $kind, string $scope ): array {
		$counts = array();
		foreach ( $this->get_census( $kind, $scope ) as $key => $row ) {
			if ( 0 < $row['published'] ) {
				$counts[ $key ] = $row['published'];
			}
		}
		return $counts;
	}

	/**
	 * Return usage per integration code for the given kind and scope.
	 *
	 * `count` sums every entity in a published or draft recipe; `last_used`
	 * is the newest of them, 0 when only undated legacy rows count.
	 * Integrations with no use are left out.
	 *
	 * @since 8.0.0
	 *
	 * @param string $kind  Bucket identifier (e.g. 'item', 'block_modifier').
	 * @param string $scope Scope constant (Integration_Item_Types or Block_Modifier_Types).
	 *
	 * @return array<string,array{count:int,last_used:int}> Integration code => usage.
	 */
	public function get_integration_usage( string $kind, string $scope ): array {
		$usage = array();
		foreach ( $this->get_census( $kind, $scope ) as $key => $row ) {
			if ( 0 === $row['all'] ) {
				continue;
			}
			$integration           = explode( ':', (string) $key, 2 )[0];
			$current               = $usage[ $integration ] ?? array(
				'count'     => 0,
				'last_used' => 0,
			);
			$usage[ $integration ] = array(
				'count'     => $current['count'] + $row['all'],
				'last_used' => max( $current['last_used'], $row['last_used'] ),
			);
		}
		return $usage;
	}

	/**
	 * Return the stored census rows for the given kind and scope.
	 *
	 * Serve-stale model: reads never run the aggregate queries. A never-built
	 * scope returns empty (curated defaults carry the response) and marks
	 * itself dirty; a dirty scope serves the stored census and re-schedules
	 * the shutdown rebuild as a safety net — the rebuild normally runs on the
	 * shutdown of whichever request marked the scope dirty.
	 *
	 * @since 8.0.0
	 *
	 * @param string $kind  Bucket identifier.
	 * @param string $scope Scope constant.
	 *
	 * @return array<string,array{published:int,all:int,last_used:int}>
	 */
	private function get_census( string $kind, string $scope ): array {
		$key = $kind . '_' . $scope;
		if ( isset( $this->memo[ $key ] ) ) {
			return $this->memo[ $key ];
		}

		$stored = automator_get_option( self::OPTION_PREFIX . $key, false );
		if ( ! is_array( $stored ) ) {
			$this->mark_dirty( $kind, $scope );
			$this->memo[ $key ] = array();
			return array();
		}

		if ( '' !== (string) automator_get_option( self::DIRTY_PREFIX . $key, '' ) ) {
			$this->schedule_rebuild();
		}

		$this->memo[ $key ] = $stored;
		return $stored;
	}

	/**
	 * Flag scope counts as stale and schedule the shutdown rebuild. Stored
	 * counts keep serving until the rebuild swaps them — invalidation never
	 * deletes data.
	 *
	 * filter_condition also clears the legacy tracker option: its counts feed
	 * the union, and a migration moving actions_conditions JSON into modifier
	 * rows surfaces as a modifier save — the tracker must rebuild or the moved
	 * conditions double-count.
	 *
	 * @param string|null $kind  Bucket identifier, or null for every known pair.
	 * @param string|null $scope Scope constant, or null for every known pair.
	 *
	 * @return void
	 */
	public function mark_dirty( ?string $kind = null, ?string $scope = null ): void {
		foreach ( $this->resolve_pairs( $kind, $scope ) as $pair ) {
			automator_update_option( self::DIRTY_PREFIX . $pair[0] . '_' . $pair[1], '1', false );
			unset( $this->memo[ $pair[0] . '_' . $pair[1] ] );

			if ( Block_Modifier_Types::FILTER_CONDITION === $pair[1] ) {
				Filter_Condition_Popularity_Tracker::get_instance()->clear_cache();
			}
		}

		$this->schedule_rebuild();
	}

	/**
	 * Rebuild every scope whose dirty flag is set. Registered on `shutdown`;
	 * idempotent, so duplicate registrations from multiple instances no-op.
	 *
	 * @return void
	 */
	public function rebuild_dirty(): void {
		foreach ( self::SCOPES as $kind => $scopes ) {
			foreach ( $scopes as $scope ) {
				if ( '' !== (string) automator_get_option( self::DIRTY_PREFIX . $kind . '_' . $scope, '' ) ) {
					$this->rebuild_scope( $kind, $scope );
				}
			}
		}
	}

	/**
	 * Run the aggregate query for one scope and swap the stored census.
	 *
	 * filter_condition also carries the draining legacy actions_conditions
	 * JSON. The two sources are disjoint per recipe because the migrator
	 * MOVES the JSON, so the union is a key-wise sum into `published` and
	 * `all`. Legacy rows carry no date, so they leave `last_used` alone.
	 *
	 * A failed query deletes the stored census rather than keep a stale one,
	 * logs the failure, and leaves the scope dirty so a later request retries:
	 * readers see no usage, never wrong usage.
	 *
	 * @param string $kind  Bucket identifier.
	 * @param string $scope Scope constant.
	 *
	 * @return void
	 */
	public function rebuild_scope( string $kind, string $scope ): void {
		try {
			$census = Scope_Usage_Counts::census( $scope );
		} catch ( Store_Exception $e ) {
			automator_delete_option( self::OPTION_PREFIX . $kind . '_' . $scope );
			unset( $this->memo[ $kind . '_' . $scope ] );
			automator_log( sprintf( 'Usage census for %s/%s was not rebuilt: %s', $kind, $scope, $e->getMessage() ), 'Entity_Popularity_Calculator' );
			return;
		}

		if ( Block_Modifier_Types::FILTER_CONDITION === $scope ) {
			foreach ( Filter_Condition_Popularity_Tracker::get_instance()->get_entity_usage_counts() as $key => $n ) {
				$row            = $census[ $key ] ?? array(
					'published' => 0,
					'all'       => 0,
					'last_used' => 0,
				);
				$census[ $key ] = array(
					'published' => $row['published'] + $n,
					'all'       => $row['all'] + $n,
					'last_used' => $row['last_used'],
				);
			}
		}

		automator_update_option( self::OPTION_PREFIX . $kind . '_' . $scope, $census, false );
		automator_delete_option( self::DIRTY_PREFIX . $kind . '_' . $scope );
		$this->memo[ $kind . '_' . $scope ] = $census;
	}

	/**
	 * Hard reset: delete every stored census, dirty flags, memo, and the
	 * tracker options. Manual flush / test isolation — production
	 * invalidation goes through mark_dirty() so reads keep serving.
	 *
	 * @return void
	 */
	public function clear_cache(): void {
		$this->memo = array();
		foreach ( self::SCOPES as $kind => $scopes ) {
			foreach ( $scopes as $scope ) {
				automator_delete_option( self::OPTION_PREFIX . $kind . '_' . $scope );
				automator_delete_option( self::DIRTY_PREFIX . $kind . '_' . $scope );
			}
		}
		Filter_Condition_Popularity_Tracker::get_instance()->clear_cache();
	}

	/**
	 * Expand an optional kind+scope into the list of known pairs it covers.
	 *
	 * @param string|null $kind  Bucket identifier or null.
	 * @param string|null $scope Scope constant or null.
	 *
	 * @return array<int,array{0:string,1:string}>
	 */
	private function resolve_pairs( ?string $kind, ?string $scope ): array {
		$pairs = array();
		foreach ( self::SCOPES as $known_kind => $scopes ) {
			foreach ( $scopes as $known_scope ) {
				if ( ( null === $kind || $kind === $known_kind ) && ( null === $scope || $scope === $known_scope ) ) {
					$pairs[] = array( $known_kind, $known_scope );
				}
			}
		}
		return $pairs;
	}

	/**
	 * Register this instance's shutdown rebuild once.
	 *
	 * @return void
	 */
	private function schedule_rebuild(): void {
		if ( $this->rebuild_scheduled ) {
			return;
		}
		$this->rebuild_scheduled = true;
		add_action( 'shutdown', array( $this, 'rebuild_dirty' ), 100 );
	}
}
