<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Events;

use Uncanny_Automator\App\Events\Dtos\Recipe_Changed;
use Uncanny_Automator\App\Infrastructure\Database\Stores\WP_Action_Store;
use Uncanny_Automator\App\Infrastructure\Database\Stores\WP_Block_Modifier_Store;
use Uncanny_Automator\App\Infrastructure\Database\Stores\WP_Block_Store;
use Uncanny_Automator\App\Infrastructure\Database\Stores\WP_Recipe_Store;
use Uncanny_Automator\App\Infrastructure\Database\Stores\WP_Recipe_Trigger_Store;
use Uncanny_Automator\App\Integration_Catalog\Integration\Enums\Integration_Item_Types;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Wire_Serializer\Enums\Wire_Node_Type;
use WP_Post;

/**
 * What the open recipe write operation changed, for the fan-out.
 *
 * Only writes inside a `Recipe_Change_Events::operation()` are recorded: a status
 * written by wp-admin, WP-CLI or another plugin is never reported. The stores note
 * the field keys an update wrote; WordPress's `transition_post_status` notes inserts
 * and status changes. The operation takes the entries for the recipes it touched
 * when it ends, so a nested write's changes (a trigger update's status switch, a
 * recipe drafted because its last action went) report with the write the caller
 * made. One entry per item: keys merge, and a status reports its net change.
 *
 * An item inserted by the operation reports `created` and nothing else: its first
 * field values and its first switch-on are part of creating it.
 *
 * @since 8.0.0
 */
final class Recipe_Change_Record {

	/**
	 * Field entries keyed by `item_type:item_id`.
	 *
	 * @var array<string, array{item_type: string, item_id: int, recipe_id: int, keys: array<string, true>, integration_code: string, item_code: string}>
	 */
	private static array $fields = array();

	/**
	 * Status entries keyed by `item_type:item_id`.
	 *
	 * @var array<string, array{item_type: string, item_id: int, recipe_id: int, old_status: string, new_status: string}>
	 */
	private static array $statuses = array();

	/**
	 * Items the operation inserted, keyed by `item_type:item_id`.
	 *
	 * @var array<string, array{item_type: string, item_id: int, recipe_id: int}>
	 */
	private static array $inserted = array();

	/**
	 * Note the meta keys a trigger or action update wrote.
	 *
	 * The keys are filtered to the item's declared field codes only when a listener
	 * asks, so the definition lookup is not paid on every save.
	 *
	 * @param string   $item_type        `Integration_Item_Types::TRIGGER` or `::ACTION`.
	 * @param int      $item_id          Item post id.
	 * @param int      $recipe_id        Recipe id.
	 * @param string   $integration_code The item's integration.
	 * @param string   $item_code        The item's code.
	 * @param string[] $keys             Meta keys whose stored value changed, first writes included.
	 *
	 * @return void
	 */
	public static function note_item_fields( string $item_type, int $item_id, int $recipe_id, string $integration_code, string $item_code, array $keys ): void {
		self::note_fields( $item_type, $item_id, $recipe_id, $keys, $integration_code, $item_code );
	}

	/**
	 * Note the field-bag keys a block or block-modifier update changed: added,
	 * removed, or holding a different value.
	 *
	 * @param string               $item_type `Wire_Node_Type::BLOCK` or `::BLOCK_MODIFIER`.
	 * @param int                  $item_id   Post id.
	 * @param int                  $recipe_id Recipe id.
	 * @param array<string, mixed> $stored    The stored bag before the write (empty when none was stored).
	 * @param array<string, mixed> $incoming  The bag the write stores.
	 *
	 * @return void
	 */
	public static function note_bag_fields( string $item_type, int $item_id, int $recipe_id, array $stored, array $incoming ): void {
		$keys = array();
		foreach ( array_unique( array_merge( array_keys( $stored ), array_keys( $incoming ) ) ) as $key ) {
			if ( ! array_key_exists( $key, $stored ) || ! array_key_exists( $key, $incoming ) || $stored[ $key ] !== $incoming[ $key ] ) {
				$keys[] = (string) $key;
			}
		}

		self::note_fields( $item_type, $item_id, $recipe_id, $keys, '', '' );
	}

	/**
	 * `transition_post_status` listener: note a recipe's, trigger's, action's,
	 * block's or block modifier's insert or status change, inside an operation.
	 *
	 * @param string       $new_status New post status.
	 * @param string       $old_status Old post status.
	 * @param WP_Post|null $post       The post.
	 *
	 * @return void
	 */
	public static function note_status( $new_status, $old_status, $post ): void {
		if ( ! $post instanceof WP_Post || $new_status === $old_status || ! Recipe_Change_Events::in_operation() ) {
			return;
		}

		$item_type = self::item_type_of( (string) $post->post_type );
		if ( null === $item_type ) {
			return;
		}

		$recipe_id = Recipe_Changed::RECIPE === $item_type ? (int) $post->ID : self::recipe_of( $post );
		if ( $recipe_id <= 0 ) {
			return;
		}

		$key = $item_type . ':' . $post->ID;
		if ( 'new' === $old_status ) {
			self::$inserted[ $key ] = array(
				'item_type' => $item_type,
				'item_id'   => (int) $post->ID,
				'recipe_id' => $recipe_id,
			);
			return;
		}

		if ( isset( self::$statuses[ $key ] ) ) {
			self::$statuses[ $key ]['new_status'] = (string) $new_status;
			return;
		}

		self::$statuses[ $key ] = array(
			'item_type'  => $item_type,
			'item_id'    => (int) $post->ID,
			'recipe_id'  => $recipe_id,
			'old_status' => (string) $old_status,
			'new_status' => (string) $new_status,
		);
	}

	/**
	 * Take the entries for the recipes an operation touched. An item the operation
	 * inserted, and that still exists, is reported as created, with no field or
	 * status entry; statuses that came back to where they started are dropped.
	 *
	 * @param int[] $recipe_ids Recipes the operation touched.
	 *
	 * @return array{created: list<array{item_type: string, item_id: int, recipe_id: int}>, fields: list<array{item_type: string, item_id: int, recipe_id: int, keys: list<string>, integration_code: string, item_code: string}>, statuses: list<array{item_type: string, item_id: int, recipe_id: int, old_status: string, new_status: string}>}
	 */
	public static function take( array $recipe_ids ): array {
		$recipe_ids = array_map( 'intval', $recipe_ids );
		$taken      = array(
			'created'  => array(),
			'fields'   => array(),
			'statuses' => array(),
		);

		foreach ( self::$inserted as $entry ) {
			if ( in_array( $entry['recipe_id'], $recipe_ids, true ) && false !== get_post_status( $entry['item_id'] ) ) {
				$taken['created'][] = $entry;
			}
		}

		foreach ( self::$fields as $key => $entry ) {
			if ( in_array( $entry['recipe_id'], $recipe_ids, true ) && ! isset( self::$inserted[ $key ] ) ) {
				$entry['keys']     = array_keys( $entry['keys'] );
				$taken['fields'][] = $entry;
			}
		}

		foreach ( self::$statuses as $key => $entry ) {
			if ( in_array( $entry['recipe_id'], $recipe_ids, true ) && ! isset( self::$inserted[ $key ] ) && $entry['old_status'] !== $entry['new_status'] ) {
				$taken['statuses'][] = $entry;
			}
		}

		self::reset();

		return $taken;
	}

	/**
	 * Forget every entry.
	 *
	 * @return void
	 */
	public static function reset(): void {
		self::$fields   = array();
		self::$statuses = array();
		self::$inserted = array();
	}

	/**
	 * Merge field keys into an item's entry.
	 *
	 * @param string   $item_type        Item type.
	 * @param int      $item_id          Item post id.
	 * @param int      $recipe_id        Recipe id.
	 * @param string[] $keys             Changed keys.
	 * @param string   $integration_code Integration (triggers and actions), or ''.
	 * @param string   $item_code        Item code (triggers and actions), or ''.
	 *
	 * @return void
	 */
	private static function note_fields( string $item_type, int $item_id, int $recipe_id, array $keys, string $integration_code, string $item_code ): void {
		if ( array() === $keys || $item_id <= 0 || ! Recipe_Change_Events::in_operation() ) {
			return;
		}

		// A store that could not name the recipe (a modifier without its recipe stamp) leaves it to the parent chain.
		if ( $recipe_id <= 0 ) {
			$post      = get_post( $item_id );
			$recipe_id = $post instanceof WP_Post ? self::recipe_of( $post ) : 0;
		}
		if ( $recipe_id <= 0 ) {
			return;
		}

		$key = $item_type . ':' . $item_id;
		if ( ! isset( self::$fields[ $key ] ) ) {
			self::$fields[ $key ] = array(
				'item_type'        => $item_type,
				'item_id'          => $item_id,
				'recipe_id'        => $recipe_id,
				'keys'             => array(),
				'integration_code' => $integration_code,
				'item_code'        => $item_code,
			);
		}

		foreach ( $keys as $field_key ) {
			self::$fields[ $key ]['keys'][ (string) $field_key ] = true;
		}
	}

	/**
	 * The event item type for a post type, or null for a post that is not part of a recipe.
	 *
	 * @param string $post_type Post type.
	 *
	 * @return string|null
	 */
	public static function item_type_of( string $post_type ): ?string {
		$types = array(
			WP_Recipe_Store::POST_TYPE         => Recipe_Changed::RECIPE,
			WP_Recipe_Trigger_Store::POST_TYPE => Integration_Item_Types::TRIGGER,
			WP_Action_Store::POST_TYPE         => Integration_Item_Types::ACTION,
			WP_Block_Store::POST_TYPE          => Wire_Node_Type::BLOCK,
			WP_Block_Modifier_Store::POST_TYPE => Wire_Node_Type::BLOCK_MODIFIER,
		);

		return $types[ $post_type ] ?? null;
	}

	/**
	 * The recipe a post belongs to: its top ancestor, when that is a recipe.
	 *
	 * @param WP_Post $post A trigger, action, block or block modifier.
	 *
	 * @return int The recipe id, or 0.
	 */
	private static function recipe_of( WP_Post $post ): int {
		$ancestors = get_post_ancestors( $post );
		$root      = (int) end( $ancestors );

		return $root > 0 && WP_Recipe_Store::POST_TYPE === get_post_type( $root ) ? $root : 0;
	}
}
