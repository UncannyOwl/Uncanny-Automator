<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Recipe_Builder\Recipe\Wire_Serializer;

use Uncanny_Automator\App\Integration_Catalog\Services\Integration_Query_Service;
use Uncanny_Automator\App\Integration_Catalog\Dependency\Dependencies_Resolver;
use Uncanny_Automator\App\Integration_Catalog\Dependency\Dependency_Context;
use Uncanny_Automator\App\Integration_Catalog\Dependency\Dependency;
use Uncanny_Automator\App\Integration_Catalog\Scope_Tag\Scope_Tag_Resolver;
use Uncanny_Automator\App\Integration_Catalog\Services\Hidden_Items;
use Uncanny_Automator\App\Integration_Catalog\Integration\Integration;
use Uncanny_Automator\App\Recipe_Builder\Block\Registry\WP_Block_Registry;
use Uncanny_Automator\App\Recipe_Builder\Block\Block;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Wire_Serializer\Enums\Availability_Status;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Wire_Serializer\Enums\Wire_Key;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Wire_Serializer\Enums\Wire_Node_Type;

/**
 * Resolves one serialized wire flow-node to `{ availability, tags }` by reusing
 * the Integration_Catalog dependency + scope-tag machinery.
 *
 * Projection-time only. Dependency resolution is memoized per integration (and
 * the block map is built once) per instance — and one instance is used per wire
 * build, so the work happens once per request, not once per node.
 */
final class Recipe_Entity_Availability_Resolver {

	/**
	 * Catalog query service — resolves integrations + items by code.
	 *
	 * @var Integration_Query_Service
	 */
	private Integration_Query_Service $query;

	/**
	 * Resolves an integration's / item's / block's dependencies (met state).
	 *
	 * @var Dependencies_Resolver
	 */
	private Dependencies_Resolver $deps;

	/**
	 * Produces the UI scope tags (license tier + locked) for an entity.
	 *
	 * @var Scope_Tag_Resolver
	 */
	private Scope_Tag_Resolver $tags;

	/**
	 * Composes the `availability.message` markdown for an unavailable node.
	 *
	 * @var Recipe_Entity_Availability_Message_Provider
	 */
	private Recipe_Entity_Availability_Message_Provider $messages;

	/**
	 * Source of catalog Block objects (delay / filter / loop).
	 *
	 * @var WP_Block_Registry
	 */
	private WP_Block_Registry $block_registry;

	/**
	 * Per-request memo of integration-level dependency results, keyed by code.
	 *
	 * @var array<string, array{all_met:bool, items:Dependency[]}>
	 */
	private array $integration_dep_memo = array();

	/**
	 * Per-request map of catalog blocks keyed by block type (built lazily once).
	 *
	 * @var array<string, Block>|null
	 */
	private ?array $blocks_by_type = null;

	/**
	 * Lazy-default DI per Rule 9c — every catalog collaborator is injectable for tests.
	 *
	 * @param Integration_Query_Service|null                   $query
	 * @param Dependencies_Resolver|null                       $deps
	 * @param Scope_Tag_Resolver|null                          $tags
	 * @param Recipe_Entity_Availability_Message_Provider|null $messages
	 * @param WP_Block_Registry|null                           $block_registry
	 */
	public function __construct(
		?Integration_Query_Service $query = null,
		?Dependencies_Resolver $deps = null,
		?Scope_Tag_Resolver $tags = null,
		?Recipe_Entity_Availability_Message_Provider $messages = null,
		?WP_Block_Registry $block_registry = null
	) {
		$this->query          = $query ?? Integration_Query_Service::get_instance();
		$this->deps           = $deps ?? new Dependencies_Resolver( new Dependency_Context() );
		$this->tags           = $tags ?? new Scope_Tag_Resolver();
		$this->messages       = $messages ?? new Recipe_Entity_Availability_Message_Provider();
		$this->block_registry = $block_registry ?? new WP_Block_Registry();
	}

	/**
	 * Resolve a node's `{ availability, tags }`, dispatching on its wire `type`.
	 *
	 * @param array $node A serialized wire node.
	 * @return array{availability: array, tags: array}
	 */
	public function resolve( array $node ): array {
		$type = (string) ( $node[ Wire_Key::TYPE ] ?? '' );

		if ( Wire_Node_Type::ITEM === $type ) {
			return $this->resolve_item(
				(string) ( $node[ Wire_Key::INTEGRATION_CODE ] ?? '' ),
				(string) ( $node[ Wire_Key::CODE ] ?? '' ),
				(string) ( $node[ Wire_Key::ITEM_TYPE ] ?? '' )
			);
		}

		if ( Wire_Node_Type::BLOCK === $type ) {
			return $this->resolve_block( (string) ( $node[ Wire_Key::BLOCK_TYPE ] ?? '' ) );
		}

		if ( Wire_Node_Type::BLOCK_MODIFIER === $type ) {
			return $this->resolve_modifier(
				(string) ( $node[ Wire_Key::INTEGRATION_CODE ] ?? '' ),
				(string) ( $node[ Wire_Key::CODE ] ?? '' ),
				(string) ( $node[ Wire_Key::BLOCK_MODIFIER_TYPE ] ?? '' )
			);
		}

		return $this->unknown( $this->entity_type_of( $node ) );
	}

	/**
	 * Resolve a trigger / action item via its catalog integration + item row.
	 *
	 * @param string $int_code    Integration code.
	 * @param string $item_code   Item code.
	 * @param string $entity_type The item type (trigger / action).
	 * @return array{availability: array, tags: array}
	 */
	private function resolve_item( string $int_code, string $item_code, string $entity_type ): array {
		$integration = $this->query->get_integration( $int_code );
		if ( null === $integration ) {
			return $this->unknown( $entity_type );
		}

		$item_obj = $this->query->get_item( $int_code, $item_code, $entity_type );

		// Not in the catalog because its own requirements hid it on this site
		// (decision A4): not usable, told in its own sentence.
		$hidden = null === $item_obj ? Hidden_Items::reason( $int_code, $entity_type, $item_code ) : null;
		if ( null !== $hidden ) {
			return array(
				Wire_Key::AVAILABILITY => array(
					Wire_Key::STATUS  => Availability_Status::UNAVAILABLE,
					Wire_Key::MESSAGE => $this->messages->hidden( $hidden, $entity_type ),
				),
				Wire_Key::TAGS         => array(),
			);
		}

		$item = null !== $item_obj ? $item_obj->to_array() : array( 'code' => $item_code );

		return $this->resolve_from_item_array( $integration, $int_code, $item, $entity_type );
	}

	/**
	 * Resolve a flow-control block (delay_schedule / filter / loop) via the
	 * block registry + the block dependency/tag resolvers.
	 *
	 * @param string $block_type The block type code.
	 * @return array{availability: array, tags: array}
	 */
	private function resolve_block( string $block_type ): array {
		$block = $this->block( $block_type );
		if ( null === $block ) {
			return $this->unknown( $block_type );
		}

		$block_deps = $this->deps->resolve_block_dependencies( $block );

		return array(
			Wire_Key::AVAILABILITY => $this->availability( $block_deps, null, $block_type ),
			Wire_Key::TAGS         => $this->tags->resolve_block_tags( $block, $block_deps )->to_array(),
		);
	}

	/**
	 * Resolve a block modifier (loop_filter / filter_condition). Modifiers live
	 * in the integration's `block_modifiers` slot, not its items — so `get_item`
	 * can't find them. Look the row up there (for its `required_tier`), then
	 * reuse the shared item resolution.
	 *
	 * @param string $int_code    Integration code.
	 * @param string $code        Modifier code.
	 * @param string $entity_type The modifier type.
	 * @return array{availability: array, tags: array}
	 */
	private function resolve_modifier( string $int_code, string $code, string $entity_type ): array {
		$integration = $this->query->get_integration( $int_code );
		if ( null === $integration ) {
			return $this->unknown( $entity_type );
		}

		$item = $this->modifier_row( $integration, $entity_type, $code ) ?? array( 'code' => $code );

		return $this->resolve_from_item_array( $integration, $int_code, $item, $entity_type );
	}

	/**
	 * Resolve `{ availability, tags }` from a catalog item/modifier array under
	 * a known integration. Shared by the item and modifier paths.
	 *
	 * @param Integration $integration The owning integration.
	 * @param string      $int_code    Integration code (memo key).
	 * @param array       $item        Catalog item/modifier row (carries required_tier).
	 * @param string      $entity_type The node kind, for the message noun.
	 * @return array{availability: array, tags: array}
	 */
	private function resolve_from_item_array( Integration $integration, string $int_code, array $item, string $entity_type ): array {
		$int_deps  = $this->integration_deps( $integration, $int_code );
		$item_deps = $this->deps->resolve_item_dependencies( $item, $integration, $int_deps );

		return array(
			Wire_Key::AVAILABILITY => $this->availability( $int_deps, $item_deps, $entity_type ),
			Wire_Key::TAGS         => $this->tags->resolve_item_tags( $item, $integration, $int_deps, $item_deps )->to_array(),
		);
	}

	/**
	 * The catalog Block for a block type (built/memoized once), or null if the
	 * type is not registered on this site.
	 *
	 * @param string $block_type The block type code.
	 * @return Block|null
	 */
	private function block( string $block_type ): ?Block {
		if ( null === $this->blocks_by_type ) {
			$this->blocks_by_type = array();
			foreach ( $this->block_registry->get_available_blocks() as $block ) {
				$this->blocks_by_type[ $block->get_type() ] = $block;
			}
		}

		return $this->blocks_by_type[ $block_type ] ?? null;
	}

	/**
	 * Find a modifier row (by code) in the integration's block_modifiers slot,
	 * normalised to an array. Null when the row isn't present.
	 *
	 * @param Integration $integration   The owning integration.
	 * @param string      $modifier_type loop_filter or filter_condition.
	 * @param string      $code          Modifier code.
	 * @return array|null
	 */
	private function modifier_row( Integration $integration, string $modifier_type, string $code ): ?array {
		$modifiers = 'loop_filter' === $modifier_type
			? $integration->get_block_modifiers()->get_loop_filters()
			: $integration->get_block_modifiers()->get_filter_conditions();

		$row = $modifiers[ $code ] ?? null;
		if ( null === $row ) {
			return null;
		}

		if ( is_array( $row ) ) {
			return $row;
		}

		return method_exists( $row, 'to_array' ) ? $row->to_array() : array( 'code' => $code );
	}

	/**
	 * Resolve (and memoize) an integration's dependency result, once per code.
	 *
	 * @param Integration $integration The integration.
	 * @param string      $int_code    Integration code (memo key).
	 * @return array{all_met:bool, items:Dependency[]}
	 */
	private function integration_deps( Integration $integration, string $int_code ): array {
		if ( ! isset( $this->integration_dep_memo[ $int_code ] ) ) {
			$this->integration_dep_memo[ $int_code ] = $this->deps->resolve_integration_dependencies( $integration );
		}

		return $this->integration_dep_memo[ $int_code ];
	}

	/**
	 * Build the `availability` object from one or two dependency results.
	 *
	 * @param array      $primary     Integration (or block) deps: { all_met, items }.
	 * @param array|null $secondary   Item deps when present, else null.
	 * @param string     $entity_type The node kind, for the message noun.
	 * @return array{status:string, message?:string}
	 */
	private function availability( array $primary, ?array $secondary, string $entity_type ): array {
		$all_met = ( $primary['all_met'] ?? true ) && ( null === $secondary || ( $secondary['all_met'] ?? true ) );
		if ( $all_met ) {
			return array( Wire_Key::STATUS => Availability_Status::USABLE );
		}

		return array(
			Wire_Key::STATUS  => Availability_Status::UNAVAILABLE,
			Wire_Key::MESSAGE => $this->messages->message( $this->blocking_dependency( $primary, $secondary ), $entity_type ),
		);
	}

	/**
	 * The first unmet dependency — integration/block deps before item deps —
	 * whose authoritative copy drives the unavailable message. Null if none.
	 *
	 * @param array      $primary   Integration (or block) deps.
	 * @param array|null $secondary Item deps, or null.
	 * @return Dependency|null
	 */
	private function blocking_dependency( array $primary, ?array $secondary ): ?Dependency {
		foreach ( array( $primary, $secondary ) as $result ) {
			if ( ! is_array( $result ) ) {
				continue;
			}

			foreach ( (array) ( $result['items'] ?? array() ) as $dep ) {
				if ( $dep instanceof Dependency && ! $dep->is_met() ) {
					return $dep;
				}
			}
		}

		return null;
	}

	/**
	 * The result for a node whose integration/block can't be resolved at all:
	 * unavailable (generic message), no tags.
	 *
	 * @param string $entity_type The node kind, for the message noun.
	 * @return array{availability: array, tags: array}
	 */
	private function unknown( string $entity_type ): array {
		return array(
			Wire_Key::AVAILABILITY => array(
				Wire_Key::STATUS  => Availability_Status::UNAVAILABLE,
				Wire_Key::MESSAGE => $this->messages->message( null, $entity_type ),
			),
			Wire_Key::TAGS         => array(),
		);
	}

	/**
	 * The node's kind discriminator (item_type / block_type / block_modifier_type).
	 *
	 * @param array $node A serialized wire node.
	 * @return string
	 */
	private function entity_type_of( array $node ): string {
		return (string) ( $node[ Wire_Key::ITEM_TYPE ] ?? $node[ Wire_Key::BLOCK_TYPE ] ?? $node[ Wire_Key::BLOCK_MODIFIER_TYPE ] ?? '' );
	}
}
