<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Recipe_Builder\Recipe\Services;

use Uncanny_Automator\App\Infrastructure\Database\Database;
use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Action_Store;
use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Recipe_Trigger_Store;
use Uncanny_Automator\App\Integration_Catalog\Integration\Enums\Block_Modifier_Types;
use Uncanny_Automator\App\Integration_Catalog\Services\Integration_Query_Service;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Schema\Recipe_Item_Digest;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Schema\Recipe_Schema_Reader;

/**
 * A recipe's integrations and items, projected from its canonical tree.
 *
 * One read per call: the tree through Recipe_Schema_Reader, flattened by
 * Recipe_Item_Digest. Nothing here walks storage or the tree shape, so an
 * item nested at any depth in a filter, delay or loop block counts like one
 * at the recipe root.
 */
final class Recipe_Integrations_Service {

	/**
	 * Digest entities whose integration a recipe is said to use.
	 *
	 *
	 * @var string[]
	 */
	private const COUNTED_ENTITIES = array(
		'trigger',
		'action',
		Block_Modifier_Types::FILTER_CONDITION,
		Block_Modifier_Types::LOOP_FILTER,
	);

	/**
	 * Tree read seam.
	 *
	 * @var Recipe_Schema_Reader
	 */
	private Recipe_Schema_Reader $reader;

	/**
	 * Trigger posts, for the title fallback.
	 *
	 * @var Recipe_Trigger_Store
	 */
	private Recipe_Trigger_Store $triggers;

	/**
	 * Action posts, for the title fallback.
	 *
	 * @var Action_Store
	 */
	private Action_Store $actions;

	/**
	 * The integration catalog, for the item name.
	 *
	 * Built from the catalog feed merged with registrations, so an item whose
	 * integration is not loaded on this request (demand-driven admin screens)
	 * still has its name.
	 *
	 * @var Integration_Query_Service
	 */
	private Integration_Query_Service $catalog;

	/**
	 * Wire the tree reader, the item stores and the catalog the label chain reads.
	 *
	 * @param Recipe_Schema_Reader|null      $reader   Tree reader; defaults to the shared instance.
	 * @param Recipe_Trigger_Store|null      $triggers Trigger store; defaults to the production one.
	 * @param Action_Store|null              $actions  Action store; defaults to the production one.
	 * @param Integration_Query_Service|null $catalog  Integration catalog; defaults to the shared instance.
	 */
	public function __construct( ?Recipe_Schema_Reader $reader = null, ?Recipe_Trigger_Store $triggers = null, ?Action_Store $actions = null, ?Integration_Query_Service $catalog = null ) {
		$this->reader   = $reader ?? Recipe_Schema_Reader::instance();
		$this->triggers = $triggers ?? Database::get_recipe_trigger_store();
		$this->actions  = $actions ?? Database::get_action_store();
		$this->catalog  = $catalog ?? Integration_Query_Service::get_instance();
	}

	/**
	 * Integration codes of the recipe's on items: triggers, actions, filter conditions and loop filters.
	 *
	 * An item counts only if the runner would execute it: a trigger or action whose
	 * `is_item_on` is false does not; blocks and modifiers carry no on/off state.
	 *
	 * @param int $recipe_id Recipe post id.
	 * @return string[] Unique codes, tree order.
	 * @throws \Uncanny_Automator\App\Recipe_Builder\Block\Migration\Legacy_Storage_Migration_Exception When the legacy migration fails.
	 */
	public function integration_codes( int $recipe_id ): array {
		$codes = array();

		foreach ( $this->rows( $recipe_id ) as $row ) {
			if ( ! in_array( $row['entity'], self::COUNTED_ENTITIES, true ) || true !== $row['is_on'] || '' === $row['integration_code'] ) {
				continue;
			}
			$codes[] = $row['integration_code'];
		}

		return array_values( array_unique( $codes ) );
	}

	/**
	 * Every trigger and action in the recipe, on or off, with its label.
	 *
	 * Label: the author's custom name, else the catalog item name (the short
	 * name the builder's item picker shows, and what 7.x stored as the title),
	 * else the post title, else the code.
	 *
	 * @param int $recipe_id Recipe post id.
	 * @return list<array{entity: string, post_id: int, is_on: bool, label: string}> Tree order.
	 * @throws \Uncanny_Automator\App\Recipe_Builder\Block\Migration\Legacy_Storage_Migration_Exception When the legacy migration fails.
	 */
	public function items( int $recipe_id ): array {
		$items = array();

		foreach ( $this->rows( $recipe_id ) as $row ) {
			if ( 'trigger' !== $row['entity'] && 'action' !== $row['entity'] ) {
				continue;
			}
			$items[] = array(
				'entity'  => $row['entity'],
				'post_id' => $row['post_id'],
				'is_on'   => $row['is_on'],
				'label'   => $this->label( $row ),
			);
		}

		return $items;
	}

	/**
	 * Resolve an item row's label: custom name, else catalog name, else post title, else code.
	 *
	 * @param array<string, mixed> $row Trigger or action digest row.
	 * @return string
	 */
	private function label( array $row ): string {
		$custom = trim( (string) ( $row['custom_name'] ?? '' ) );
		if ( '' !== $custom ) {
			return $custom;
		}

		$item = $this->catalog->get_item( $row['integration_code'], $row['code'], $row['entity'] );
		$name = null === $item ? '' : trim( (string) ( $item->to_array()['sentence']['short'] ?? '' ) );
		if ( '' !== $name ) {
			return $name;
		}

		$store = 'trigger' === $row['entity'] ? $this->triggers : $this->actions;
		$post  = $store->get_wp_post( $row['post_id'] );
		$title = null === $post ? '' : trim( (string) $post->post_title );

		return '' !== $title ? $title : $row['code'];
	}

	/**
	 * The recipe's digest rows, or none when the id is not a recipe.
	 *
	 * @param int $recipe_id Recipe post id.
	 * @return list<array<string, mixed>>
	 * @throws \Uncanny_Automator\App\Recipe_Builder\Block\Migration\Legacy_Storage_Migration_Exception When the legacy migration fails.
	 */
	private function rows( int $recipe_id ): array {
		$tree = $this->reader->get_or_build( $recipe_id );

		return null === $tree ? array() : Recipe_Item_Digest::from_tree( $tree );
	}
}
