<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Recipe_Builder\Recipe\Wire_Serializer;

use Uncanny_Automator\App\Recipe_Builder\Block\Registry\Block_Registry;
use Uncanny_Automator\App\Recipe_Builder\Block\Registry\WP_Block_Registry;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Wire_Serializer\Enums\Wire_Key;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Wire_Serializer\Enums\Wire_Node_Type;

/**
 * Project a tree's block entry to the wire-shape ModelRecipeBlock.
 *
 * Strict 7-key shape per the wire contract:
 *   { type='block', block_type, id, fields, modifiers, paths, tokens }
 *
 * `tokens` is always present — Loop blocks emit their iteration token;
 * Filter and Delay emit an empty array. Mirrors Item_Wire_Serializer's
 * always-emit-tokens contract so the FE Zod schema can require the key.
 *
 * Recursive: paths' items dispatch back through Item_Wire_Serializer for
 * `kind='action'` and through this serializer for
 * nested `kind='block'` entries.
 *
 * **Forbidden top-level keys** stripped per the wire contract:
 * `logic`, `conditions`, `filters`, `iterable_expression`, `recipe_id`,
 * `status`, `_ui_order`, `run_on`, `priority`, `actions`, `parent_id`,
 * `mode`, top-level `items` (items live inside `paths[].items[]`).
 *
 * `id` is `int|string` — filter blocks emit legacy string-hash group ids,
 * loop / delay blocks emit numeric post ids. Both ride forward.
 *
 * Reads tree keys as literals; emits wire keys from Wire_Key.
 */
final class Block_Wire_Serializer {

	/**
	 * Projects the block's own field array into its wire envelope.
	 *
	 * @var Field_Output_Serializer
	 */
	private $fields;

	/**
	 * Projects each modifier attached to the block.
	 *
	 * @var Modifier_Wire_Serializer
	 */
	private $modifier;

	/**
	 * Projects the child items sitting under the block's paths.
	 *
	 * @var Item_Wire_Serializer
	 */
	private $item;

	/**
	 * Block definitions, read for the sentence-label flag of each block field.
	 *
	 * @var Block_Registry
	 */
	private $blocks;

	/**
	 * Wire up the serializers a block projection needs.
	 *
	 * Collaborators are injectable so tests can substitute fakes; production
	 * passes nothing and gets the real serializers.
	 *
	 * @param Field_Output_Serializer|null   $fields   Field projector.
	 * @param Modifier_Wire_Serializer|null  $modifier Modifier projector.
	 * @param Item_Wire_Serializer|null      $item     Item projector.
	 * @param Block_Registry|null            $blocks   Block definition registry.
	 */
	public function __construct(
		?Field_Output_Serializer $fields = null,
		?Modifier_Wire_Serializer $modifier = null,
		?Item_Wire_Serializer $item = null,
		?Block_Registry $blocks = null
	) {
		$this->fields   = $fields ?? new Field_Output_Serializer();
		$this->modifier = $modifier ?? new Modifier_Wire_Serializer( $this->fields );
		$this->item     = $item ?? new Item_Wire_Serializer( $this->fields );
		$this->blocks   = $blocks ?? new WP_Block_Registry();
	}

	/**
	 * Project one tree block entry to its strict 7-key wire shape.
	 *
	 * @param array $block Tree block entry.
	 * @return array Wire-shape block (strict 7 keys).
	 */
	public function serialize( array $block ): array {
		$block_type = (string) ( $block['block_type'] ?? '' );

		$id = $block['id'] ?? 0;
		if ( ! is_int( $id ) && ! is_string( $id ) ) {
			$id = 0;
		}

		return array(
			Wire_Key::TYPE       => Wire_Node_Type::BLOCK,
			Wire_Key::BLOCK_TYPE => $block_type,
			Wire_Key::ID         => $id,
			Wire_Key::FIELDS     => $this->fields->serialize( $this->with_sentence_labels( is_array( $block['fields'] ?? null ) ? $block['fields'] : array(), $block_type ) ),
			Wire_Key::MODIFIERS  => $this->modifier->serialize_list( is_array( $block['modifiers'] ?? null ) ? $block['modifiers'] : array() ),
			Wire_Key::PATHS      => $this->serialize_paths( is_array( $block['paths'] ?? null ) ? $block['paths'] : array() ),
			// Placeholder: Recipe_Entity_Token_Decorator derives loop tokens at projection time.
			Wire_Key::TOKENS     => array(),
		);
	}

	/**
	 * Carry each field's sentence-label flag from the block definition onto its input, so a field whose definition hides its label reads without it in the block's sentence. A scalar tree value is wrapped into the input shape the field serializer reads.
	 *
	 * @param array<string,mixed> $fields     Tree block fields.
	 * @param string              $block_type Block type.
	 * @return array<string,mixed> The fields, flagged where the definition hides the label.
	 */
	private function with_sentence_labels( array $fields, string $block_type ): array {
		if ( empty( $fields ) ) {
			return $fields;
		}

		$definition = $this->blocks->get_block_definition( $block_type );
		if ( null === $definition ) {
			return $fields;
		}

		foreach ( $definition->get_fields() as $field ) {
			$field = (array) $field;
			$code  = (string) ( $field['option_code'] ?? '' );
			if ( '' === $code || ! array_key_exists( $code, $fields ) || false !== ( $field['show_label_in_sentence'] ?? true ) ) {
				continue;
			}

			$entry = $fields[ $code ];
			if ( $entry instanceof \stdClass ) {
				$entry = (array) $entry;
			}
			if ( ! is_array( $entry ) ) {
				$entry = array( 'value' => $entry );
			}

			$misc = $entry['miscellaneous'] ?? array();
			if ( $misc instanceof \stdClass ) {
				$misc = (array) $misc;
			}
			$misc = is_array( $misc ) ? $misc : array();

			$misc['show_label_in_sentence'] = false;
			$entry['miscellaneous']         = $misc;
			$fields[ $code ]                = $entry;
		}

		return $fields;
	}

	/**
	 * Project paths list. Each path is `{path_code, content}` where the
	 * content entries recurse through item / block serializers.
	 *
	 * The wire key is `content` (matching the FE `ModelRecipeBlockPath`
	 * and `flow.content[]` at the root); the tree it reads from keys the
	 * same list as `items` (see `Recipe_Tree_Builder::build_block_paths`).
	 *
	 * @param array $paths
	 * @return list<array{path_code: string, content: list<array>}>
	 */
	private function serialize_paths( array $paths ): array {
		$out = array();
		foreach ( $paths as $path ) {
			if ( ! is_array( $path ) ) {
				continue;
			}
			$out[] = array(
				Wire_Key::PATH_CODE => (string) ( $path['path_code'] ?? '' ),
				Wire_Key::CONTENT   => $this->serialize_path_items( is_array( $path['items'] ?? null ) ? $path['items'] : array() ),
			);
		}
		return $out;
	}

	/**
	 * Project the heterogeneous item list inside a path. Each entry is
	 * dispatched on its `kind` discriminator: `'block'` recurses through
	 * this serializer, `'action'` goes through Item_Wire_Serializer.
	 *
	 * Triggers don't appear in path items per the wire contract — they're
	 * in `flow.entry.triggers[]`. Defensive: any `kind='trigger'` entry
	 * would be passed to Item_Wire_Serializer for projection consistency,
	 * but the upstream tree builder shouldn't produce that shape.
	 *
	 * @param array $items
	 * @return list<array>
	 */
	private function serialize_path_items( array $items ): array {
		$out = array();
		foreach ( $items as $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}
			$kind = (string) ( $entry['kind'] ?? '' );
			if ( 'block' === $kind ) {
				$out[] = $this->serialize( $entry );
				continue;
			}
			// Action / trigger go through the item serializer.
			$out[] = $this->item->serialize( $entry );
		}
		return $out;
	}
}
