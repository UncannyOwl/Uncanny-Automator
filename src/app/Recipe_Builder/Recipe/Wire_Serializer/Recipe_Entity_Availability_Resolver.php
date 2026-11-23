<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Recipe_Builder\Recipe\Wire_Serializer;

use Uncanny_Automator\App\Infrastructure\License\License_Manager;
use Uncanny_Automator\App\Infrastructure\License\License_Provider_Interface;
use Uncanny_Automator\App\Integration_Catalog\Services\Integration_Query_Service;
use Uncanny_Automator\App\Integration_Catalog\Services\Item_Availability_Provider;
use Uncanny_Automator\App\Integration_Catalog\Dependency\Dependencies_Resolver;
use Uncanny_Automator\App\Integration_Catalog\Dependency\Dependency_Context;
use Uncanny_Automator\App\Integration_Catalog\Dependency\Dependency;
use Uncanny_Automator\App\Integration_Catalog\Scope_Tag\Scope_Tag_Resolver;
use Uncanny_Automator\App\Integration_Catalog\Integration\Integration;
use Uncanny_Automator\App\Integration_Catalog\Integration\Enums\Block_Modifier_Types;
use Uncanny_Automator\App\Integration_Catalog\Integration\Value_Objects\Integration_Block_Modifier;
use Uncanny_Automator\App\Integration_Catalog\Integration\Value_Objects\Integration_Item;
use Uncanny_Automator\App\Recipe_Builder\Block\Registry\WP_Block_Registry;
use Uncanny_Automator\App\Recipe_Builder\Block\Block;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Wire_Serializer\Enums\Availability_Status;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Wire_Serializer\Enums\Wire_Key;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Wire_Serializer\Enums\Wire_Node_Type;

use function Uncanny_Automator\App\Infrastructure\automator_license_manager;

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
	 * The license whose key an app's request carries to the platform.
	 *
	 * @var License_Provider_Interface
	 */
	private License_Provider_Interface $license;

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
	 * @param License_Provider_Interface|null                  $license
	 */
	public function __construct(
		?Integration_Query_Service $query = null,
		?Dependencies_Resolver $deps = null,
		?Scope_Tag_Resolver $tags = null,
		?Recipe_Entity_Availability_Message_Provider $messages = null,
		?WP_Block_Registry $block_registry = null,
		?License_Provider_Interface $license = null
	) {
		$this->query          = $query ?? Integration_Query_Service::get_instance();
		$this->deps           = $deps ?? new Dependencies_Resolver( new Dependency_Context() );
		$this->tags           = $tags ?? new Scope_Tag_Resolver();
		$this->messages       = $messages ?? new Recipe_Entity_Availability_Message_Provider();
		$this->block_registry = $block_registry ?? new WP_Block_Registry();
		$this->license        = $license ?? automator_license_manager() ?? new License_Manager();
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

		return $this->resolve_entry( $integration, $int_code, $item_code, $entity_type, $this->query->get_item( $int_code, $item_code, $entity_type ) );
	}

	/**
	 * Resolve an item or block modifier from its store entry.
	 *
	 * Not in the catalog yet stored as unavailable: an item whose requirements are not met and that declares nothing
	 * (the catalog lists a blocked item only when it states a plugin, another integration or a setup step; such an
	 * item resolves below, with its rows). Not usable, told in its own sentence (decision A4).
	 *
	 * @param Integration                                      $integration The owning integration.
	 * @param string                                           $int_code    Integration code.
	 * @param string                                           $code        Item or modifier code.
	 * @param string                                           $entity_type The item or modifier type.
	 * @param Integration_Item|Integration_Block_Modifier|null $entry       Its store entry; null when the catalog lacks it.
	 * @return array{availability: array, tags: array}
	 */
	private function resolve_entry( Integration $integration, string $int_code, string $code, string $entity_type, $entry ): array {
		$unavailable = null === $entry ? Item_Availability_Provider::instance()->unavailable_definition( $entity_type, $int_code, $code ) : null;
		if ( null !== $unavailable ) {
			return $this->hidden( (array) ( $unavailable['requirements'] ?? array() ), $entity_type );
		}

		$item = null !== $entry ? $entry->to_array() : array( 'code' => $code );

		return $this->resolve_from_item_array( $integration, $int_code, $item, $entity_type, null !== $entry ? $entry->get_requirements() : null );
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
	 * can't find them. Look the entry up there, then resolve it as an item is.
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

		return $this->resolve_entry( $integration, $int_code, $code, $entity_type, $this->modifier_entry( $integration, $entity_type, $code ) );
	}

	/**
	 * Resolve `{ availability, tags }` from a catalog item/modifier array under
	 * a known integration. Shared by the item and modifier paths.
	 *
	 * @param Integration $integration The owning integration.
	 * @param string      $int_code    Integration code (memo key).
	 * @param array       $item        Catalog item/modifier row (carries required_tier).
	 * @param string      $entity_type The node kind, for the message noun.
	 * @param array|null  $requirements The item's stored requirements, from its store entry; null when none.
	 * @return array{availability: array, tags: array}
	 */
	private function resolve_from_item_array( Integration $integration, string $int_code, array $item, string $entity_type, ?array $requirements = null ): array {
		$int_deps  = $this->integration_deps( $integration, $int_code );
		$item_deps = $this->deps->resolve_item_dependencies( $item, $integration, $int_deps, $requirements );

		// Not met, yet no row of its own or its integration's to show it locked: read as a blocked item that declares nothing.
		if ( $this->deps->is_unmet_without_a_row( $requirements, $item_deps, $int_deps ) ) {
			return $this->hidden( (array) $requirements, $entity_type );
		}

		// An item whose own requirements are not met is not registered; an app's request is refused without a license key.
		$never_runs = false === ( $requirements['is_met'] ?? null ) || ( $integration->is_app() && '' === $this->license->get_key() );

		return array(
			Wire_Key::AVAILABILITY => $this->availability( $int_deps, $item_deps, $entity_type, $never_runs ),
			Wire_Key::TAGS         => $this->tags->resolve_item_tags( $item, $integration, $int_deps, $item_deps )->to_array(),
		);
	}

	/**
	 * An item that is not usable and shows no row: unavailable, in its own sentence (decision A4), or the generic one
	 * when it gives none.
	 *
	 * @since 8.1.0
	 *
	 * @param array  $requirements The item's stored requirements.
	 * @param string $entity_type  The node kind, for the generic sentence.
	 * @return array{availability: array, tags: array}
	 */
	private function hidden( array $requirements, string $entity_type ): array {
		return array(
			Wire_Key::AVAILABILITY => array(
				Wire_Key::STATUS  => Availability_Status::UNAVAILABLE,
				Wire_Key::MESSAGE => $this->messages->hidden( (string) ( $requirements['message'] ?? '' ), $entity_type ),
			),
			Wire_Key::TAGS         => array(),
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
	 * Find a modifier's store entry (by code) in the integration's block_modifiers slot.
	 *
	 * @param Integration $integration   The owning integration.
	 * @param string      $modifier_type loop_filter or filter_condition.
	 * @param string      $code          Modifier code.
	 * @return Integration_Block_Modifier|null Null when the catalog lacks it.
	 */
	private function modifier_entry( Integration $integration, string $modifier_type, string $code ): ?Integration_Block_Modifier {
		$modifiers = Block_Modifier_Types::LOOP_FILTER === $modifier_type
			? $integration->get_block_modifiers()->get_loop_filters()
			: $integration->get_block_modifiers()->get_filter_conditions();

		return $modifiers[ $code ] ?? null;
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
	 * @param bool       $never_runs  Whether the node does not run whatever the license says: an item whose own stored
	 *                                requirements are not met, or an app's node on a site with no license key to send.
	 * @return array{status:string, message?:string}
	 */
	private function availability( array $primary, ?array $secondary, string $entity_type, bool $never_runs = false ): array {
		$all_met = ( $primary['all_met'] ?? true ) && ( null === $secondary || ( $secondary['all_met'] ?? true ) );
		if ( $all_met ) {
			return array( Wire_Key::STATUS => Availability_Status::USABLE );
		}

		return array(
			Wire_Key::STATUS  => Availability_Status::UNAVAILABLE,
			Wire_Key::MESSAGE => $this->messages->message( $this->unmet_dependencies( $primary, $secondary ), $entity_type, $never_runs ),
		);
	}

	/**
	 * Every unmet dependency, integration/block deps before item deps. The first
	 * one's authoritative copy drives the unavailable message.
	 *
	 * @since 8.1.0
	 *
	 * @param array      $primary   Integration (or block) deps.
	 * @param array|null $secondary Item deps, or null.
	 * @return Dependency[]
	 */
	private function unmet_dependencies( array $primary, ?array $secondary ): array {
		$unmet = array();

		foreach ( array( $primary, $secondary ) as $result ) {
			if ( ! is_array( $result ) ) {
				continue;
			}

			foreach ( (array) ( $result['items'] ?? array() ) as $dep ) {
				if ( $dep instanceof Dependency && ! $dep->is_met() ) {
					$unmet[] = $dep;
				}
			}
		}

		return $unmet;
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
				Wire_Key::MESSAGE => $this->messages->message( array(), $entity_type ),
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
