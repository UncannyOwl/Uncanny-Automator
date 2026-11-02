<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Recipe_Builder\Recipe\Portability;

use Uncanny_Automator\App\Recipe_Builder\Field\Services\Custom_Item_Name_Field;
use Uncanny_Automator\App\Infrastructure\Database\Stores\WP_Block_Store;
use Uncanny_Automator\App\Infrastructure\Database\Stores\WP_Recipe_Trigger_Store;
use Uncanny_Automator\App\Recipe_Builder\Block\Enums\Block_Path_Code;
use Uncanny_Automator\App\Recipe_Builder\Field\Services\Item_Field_Filter;
use Uncanny_Automator\App\Recipe_Builder\Block\Enums\Block_Type;
use Uncanny_Automator\App\Recipe_Builder\Block\Migration\Delay_Schedule_Meta_Migrator;
use Uncanny_Automator\App\Integration_Catalog\Integration\Enums\Block_Modifier_Types;
use Uncanny_Automator\App\Recipe_Builder\Loop\Value_Objects\Loop_Field_Translator;
use WP_Error;

/**
 * Old-format export file → portable document.
 *
 * Legacy export files are raw post+meta dumps (`{recipe:{post,meta},
 * triggers:[{post,meta}], actions, loops, blocks, closure}`; the closure branch
 * becomes Redirect actions appended last). This adapter
 * translates them MECHANICALLY into the canonical-tree portable document the
 * importer replays: nodes carry the dump's OLD post ids (the materializer's
 * Pass-2 reference rewrite keys on them), item fields are the dump meta
 * minus the structural keys, loops invert through `Loop_Field_Translator`.
 *
 * Pre-canonical recipe meta the tree cannot express rides a `_legacy`
 * side-channel on the document — `actions_conditions` JSON and the
 * anonymous-selector `source`/`fields` pair — which the importer stashes
 * via the Recipe_Legacy_Meta_Store and canonicalizes through the EXISTING
 * migrators/stores. An action's `async_*` delay meta rides the side-channel
 * too, keyed by source action id: left in the field bag it would be written
 * back as ordinary action meta by every 8.0.0 write path.
 */
final class Legacy_Export_Adapter {

	/**
	 * `async_*` meta stripped from the actions of the dump being adapted, keyed by source action id.
	 *
	 * @var array<int,array<string,string>>
	 */
	private array $action_async = array();

	/**
	 * Dump meta keys that are NOT item fields: the item identity keys (the
	 * node carries them as `code`/`integration_code`) and the path bucket.
	 * Composed from the canonical constants and merged with
	 * {@see Item_Field_Filter::STRUCTURAL_META_KEYS} and
	 * {@see Recipe_Replay_Writer::LEGACY_BOOKKEEPING_KEYS} at strip time so
	 * the lists cannot drift. The `sentence` meta stays in the fields — the
	 * replay writer persists it verbatim (an item for an inactive integration
	 * renders from its carried sentence).
	 *
	 * @var string[]
	 */
	private const STRUCTURAL_META_KEYS = array(
		WP_Recipe_Trigger_Store::META_TRIGGER_CODE,
		WP_Recipe_Trigger_Store::META_INTEGRATION,
		WP_Block_Store::META_PATH_CODE,
	);

	/**
	 * Translate one decoded legacy export file into a portable document.
	 *
	 * @param object $old Decoded legacy export JSON (objects, as json_decode emits).
	 * @return array<string,mixed>|WP_Error Portable document (+ `_legacy` side-channel), or the failure.
	 */
	public function adapt( object $old ) {
		$recipe = $old->recipe ?? null;
		if ( ! is_object( $recipe ) || ! isset( $recipe->post, $recipe->meta ) ) {
			return new WP_Error(
				'invalid-recipe-json',
				esc_html_x( 'The uploaded file is not a valid recipe export.', 'Import Recipe', 'uncanny-automator' )
			);
		}

		$recipe_meta        = (array) $recipe->meta;
		$this->action_async = array();

		$tree = array(
			'title'         => (string) ( $recipe->post->post_title ?? 'Imported recipe' ),
			'recipe_type'   => $this->meta_value( $recipe_meta, 'uap_recipe_type', 'user' ),
			'trigger_logic' => $this->meta_value( $recipe_meta, 'automator_trigger_logic', 'all' ),
			'notes'         => $this->meta_value( $recipe_meta, 'uap_recipe_notes', '' ),
			'triggers'      => $this->adapt_items( $old->triggers ?? false, 'trigger' ),
			'content'       => array_merge(
				$this->adapt_items( $old->actions ?? false, 'action' ),
				$this->adapt_loops( $old->loops ?? false ),
				$this->adapt_blocks( $old->blocks ?? false ),
				$this->adapt_closures( $old->closure ?? false )
			),
		);

		$selector_source = $this->meta_value( $recipe_meta, 'source', '' );
		$selector_fields = automator_safe_unserialize( $this->meta_value( $recipe_meta, 'fields', '' ) );

		return array(
			'schema_version' => Recipe_Exporter::SCHEMA_VERSION,
			'exported_from'  => $this->origin_from_guid( (string) ( $recipe->post->guid ?? '' ) ),
			'exported_at'    => '',
			'user_selector'  => null,
			'recipe'         => $tree,
			'_legacy'        => array(
				'actions_conditions' => $this->meta_value( $recipe_meta, 'actions_conditions', '' ),
				// Condition groups parent at the SOURCE loop/recipe ids —
				// the importer remaps them; recipe-parented groups need the
				// source recipe id since it never enters the item id map.
				'source_recipe_id'   => (int) ( $recipe->post->ID ?? 0 ),
				'selector_source'    => $selector_source,
				// Legacy selector `fields` meta is a PHP-serialized array.
				'selector_fields'    => '' !== $selector_source && is_array( $selector_fields ) ? $selector_fields : array(),
				'action_async'       => $this->action_async,
			),
		);
	}

	/**
	 * Translate a dump part list into item tree nodes.
	 *
	 * @param mixed  $parts Dump branch (`[{post,meta}]` or `false`).
	 * @param string $kind  Tree node kind (`trigger`/`action`).
	 * @return array<int,array<string,mixed>> Item nodes.
	 */
	private function adapt_items( $parts, string $kind ): array {
		$nodes = array();
		foreach ( $this->parts_list( $parts ) as $part ) {
			$nodes[] = $this->adapt_item( $part, $kind );
		}
		return $nodes;
	}

	/**
	 * Translate the dump's closure branch into Redirect action nodes. The only closure that ever shipped
	 * is WordPress → Redirect; its one field, `REDIRECTURL`, becomes the action's `REDIRECT_URL`. The node
	 * carries the action's own sentence template, as every imported item carries its `sentence`; the closure's
	 * wording ("when recipe is completed") is not copied.
	 *
	 * @param mixed $parts Dump branch (`[{post,meta}]` or `false`).
	 * @return array<int,array<string,mixed>> Action nodes.
	 */
	private function adapt_closures( $parts ): array {
		$nodes = array();
		foreach ( $this->parts_list( $parts ) as $part ) {
			$meta = (array) ( $part->meta ?? array() );
			if ( 'REDIRECT' !== (string) $this->meta_value( $meta, 'code', '' ) ) {
				continue;
			}
			$nodes[] = array(
				'kind'             => 'action',
				'id'               => (int) ( $part->post->ID ?? 0 ),
				'code'             => 'REDIRECT_TO_URL',
				'integration_code' => 'REDIRECT',
				'fields'           => array(
					'REDIRECT_URL' => (string) $this->meta_value( $meta, 'REDIRECTURL', '' ),
					'sentence'     => 'Redirect to {{a URL:REDIRECT_URL}}',
				),
				'is_item_on'       => 'publish' === (string) ( $part->post->post_status ?? '' ),
				'custom_name'      => '',
			);
		}
		return $nodes;
	}

	/**
	 * Translate one dump part into an item tree node. Fields are the dump
	 * meta minus structural keys, unserialized, FLAT (raw values) — exactly
	 * the shape `Item_Tree_Entry` emits.
	 *
	 * @param object $part Dump part (`{post,meta}`).
	 * @param string $kind Tree node kind.
	 * @return array<string,mixed> Item node.
	 */
	private function adapt_item( object $part, string $kind ): array {
		$meta       = (array) ( $part->meta ?? array() );
		$fields     = array();
		$async      = array();
		$async_keys = 'action' === $kind ? Delay_Schedule_Meta_Migrator::async_meta_keys() : array();
		foreach ( $meta as $key => $values ) {
			if ( in_array( (string) $key, $async_keys, true ) ) {
				$value = (string) $this->first_of( $values );
				if ( '' !== $value ) {
					$async[ (string) $key ] = $value;
				}
				continue;
			}
			if ( in_array( (string) $key, self::STRUCTURAL_META_KEYS, true )
				|| in_array( (string) $key, Item_Field_Filter::STRUCTURAL_META_KEYS, true )
				|| in_array( (string) $key, Recipe_Replay_Writer::LEGACY_BOOKKEEPING_KEYS, true ) ) {
				continue;
			}
			$fields[ (string) $key ] = automator_safe_unserialize( $this->first_of( $values ) );
		}

		if ( ! empty( $async ) ) {
			$this->action_async[ (int) ( $part->post->ID ?? 0 ) ] = $async;
		}

		$custom_name = (string) $this->meta_value( $meta, Custom_Item_Name_Field::FIELD_CODE, '' );

		return array(
			'kind'             => $kind,
			'id'               => (int) ( $part->post->ID ?? 0 ),
			'code'             => (string) $this->meta_value( $meta, 'code', '' ),
			'integration_code' => (string) $this->meta_value( $meta, 'integration', '' ),
			'fields'           => $fields,
			'is_item_on'       => 'publish' === (string) ( $part->post->post_status ?? '' ),
			'custom_name'      => $custom_name,
		);
	}

	/**
	 * Translate the dump's nested loops into loop block nodes (legacy
	 * `uo-loop` storage shape — expression via `Loop_Field_Translator`,
	 * filters as `loop_filter` modifiers, child actions as path items).
	 *
	 * @param mixed $loops Dump branch.
	 * @return array<int,array<string,mixed>> Loop block nodes.
	 */
	private function adapt_loops( $loops ): array {
		$nodes = array();
		foreach ( $this->parts_list( $loops ) as $loop ) {
			$meta       = (array) ( $loop->meta ?? array() );
			$expression = automator_safe_unserialize( $this->first_of( $meta['iterable_expression'] ?? array() ) );
			$expression = is_array( $expression ) ? $expression : (array) $expression;

			$modifiers = array();
			foreach ( $this->parts_list( $loop->loops->filters ?? false ) as $filter ) {
				$filter_meta = (array) ( $filter->meta ?? array() );
				$modifiers[] = array(
					'block_modifier_type' => Block_Modifier_Types::LOOP_FILTER,
					'id'                  => (int) ( $filter->post->ID ?? 0 ),
					'code'                => (string) $this->meta_value( $filter_meta, 'code', '' ),
					'integration_code'    => $this->modifier_integration_code( $filter_meta ),
					'fields'              => $this->decoded_json( $this->meta_value( $filter_meta, 'fields', '' ) ),
				);
			}

			$nodes[] = array(
				'kind'       => 'block',
				'block_type' => Block_Type::LOOP,
				'id'         => (int) ( $loop->post->ID ?? 0 ),
				'fields'     => Loop_Field_Translator::to_fields( $expression ),
				'modifiers'  => $modifiers,
				'paths'      => array(
					array(
						'path_code' => Block_Path_Code::LOOP_ITERATION_COMPLETED,
						'items'     => $this->adapt_items( $loop->loops->actions ?? false, 'action' ),
					),
				),
			);
		}
		return $nodes;
	}

	/**
	 * Translate the dump's unified blocks (newer legacy exports) into block
	 * nodes, recursing into nested children.
	 *
	 * @param mixed $blocks Dump branch.
	 * @return array<int,array<string,mixed>> Block nodes.
	 */
	private function adapt_blocks( $blocks ): array {
		$nodes = array();
		foreach ( $this->parts_list( $blocks ) as $block ) {
			$meta       = (array) ( $block->meta ?? array() );
			$block_type = (string) $this->meta_value( $meta, 'block_type', Block_Type::FILTER );

			$modifiers = array();
			foreach ( $this->parts_list( $block->children->block_modifiers ?? false ) as $modifier ) {
				$modifier_meta = (array) ( $modifier->meta ?? array() );
				$modifiers[]   = array(
					'block_modifier_type' => Block_Modifier_Types::FILTER_CONDITION,
					'id'                  => (int) ( $modifier->post->ID ?? 0 ),
					'code'                => (string) $this->meta_value( $modifier_meta, 'code', '' ),
					'integration_code'    => $this->modifier_integration_code( $modifier_meta ),
					'fields'              => $this->decoded_json( $this->meta_value( $modifier_meta, 'fields', '' ) ),
				);
			}

			$items = array_merge(
				$this->adapt_items( $block->children->actions ?? false, 'action' ),
				$this->adapt_blocks( $block->children->blocks ?? false )
			);

			$nodes[] = array(
				'kind'       => 'block',
				'block_type' => $block_type,
				'id'         => (int) ( $block->post->ID ?? 0 ),
				'fields'     => $this->decoded_json( $this->meta_value( $meta, 'fields', '' ) ),
				'modifiers'  => $modifiers,
				'paths'      => array(
					array(
						'path_code' => Block_Path_Code::for_block_type( $block_type )[0] ?? Block_Path_Code::FILTER_CONDITIONS_MET,
						'items'     => $items,
					),
				),
			);
		}
		return $nodes;
	}

	/**
	 * Normalize a dump branch (`array|false|null`) to a list of part objects.
	 *
	 * @param mixed $parts Dump branch.
	 * @return array<int,object> Part objects.
	 */
	private function parts_list( $parts ): array {
		if ( ! is_array( $parts ) && ! is_object( $parts ) ) {
			return array();
		}
		$list = array();
		foreach ( (array) $parts as $part ) {
			if ( is_object( $part ) && isset( $part->post ) ) {
				$list[] = $part;
			}
		}
		return $list;
	}

	/**
	 * A loop filter's or block modifier's integration code. Writers stored it
	 * as `integration`, `integration_code` or both (Pro's 2024 loop endpoint
	 * wrote only `integration_code`; WP_Block_Modifier_Store writes
	 * `integration_code`), so read either — in the order Pro's loop filter
	 * reader uses. Items store `integration` and read it directly.
	 *
	 * @param array<string,mixed> $meta Dump meta of the modifier post.
	 * @return string Integration code, or '' when neither key is set.
	 */
	private function modifier_integration_code( array $meta ): string {
		$code = (string) $this->meta_value( $meta, 'integration', '' );

		return '' !== $code ? $code : (string) $this->meta_value( $meta, 'integration_code', '' );
	}

	/**
	 * Read the first value of a dump meta key (`$meta[key][0]` shape).
	 *
	 * @param array<string,mixed> $meta     Dump meta map.
	 * @param string              $key      Meta key.
	 * @param mixed               $fallback Default when absent.
	 * @return mixed First value or the fallback.
	 */
	private function meta_value( array $meta, string $key, $fallback ) {
		if ( ! isset( $meta[ $key ] ) ) {
			return $fallback;
		}
		$value = $this->first_of( $meta[ $key ] );
		return null === $value || '' === $value ? $fallback : $value;
	}

	/**
	 * First element of a dump meta value list (tolerates scalars/objects).
	 *
	 * @param mixed $values `[value]`, scalar, or object.
	 * @return mixed First value.
	 */
	private function first_of( $values ) {
		if ( is_array( $values ) ) {
			return $values[0] ?? null;
		}
		if ( is_object( $values ) ) {
			$values = (array) $values;
			return $values[0] ?? reset( $values );
		}
		return $values;
	}

	/**
	 * Decode a JSON meta value into an assoc array ('' / corrupt → []).
	 *
	 * @param mixed $json JSON string (or already-decoded array).
	 * @return array<string,mixed> Decoded value.
	 */
	private function decoded_json( $json ): array {
		if ( is_array( $json ) ) {
			return $json;
		}
		$decoded = json_decode( (string) $json, true );
		return is_array( $decoded ) ? $decoded : array();
	}

	/**
	 * Extract the exporting site's origin from the dump recipe post's guid
	 * (legacy `get_import_url_from_recipe_post` semantics).
	 *
	 * @param string $guid Recipe post guid.
	 * @return string `scheme://host[:port]`, or '' when unparsable.
	 */
	private function origin_from_guid( string $guid ): string {
		$parts = wp_parse_url( $guid );
		if ( empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
			return '';
		}
		return $parts['scheme'] . '://' . $parts['host'] . ( isset( $parts['port'] ) ? ':' . $parts['port'] : '' );
	}
}
