<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Recipe_Builder\Recipe\Wire_Serializer;

use Uncanny_Automator\App\Recipe_Builder\Recipe\Wire_Serializer\Enums\Wire_Key;

/**
 * Project a tree's flow-shape data to ModelRecipeFlow.
 *
 * Strict 3-key shape per the wire contract:
 *   { entry: { operator, triggers[] }, run_as: null|object, content: list }
 *
 * Triggers ALWAYS live in `entry.triggers[]`, never in `content[]`.
 * `content[]` is heterogeneous (action / block); dispatch on
 * each entry's `kind` discriminator.
 *
 * `operator` is `'all' | 'any'` (closed enum). Reads `trigger_logic` from
 * the tree; defaults to `'all'` for missing or unknown values to keep the
 * wire contract honest under partial trees.
 *
 * `run_as` is null or `{ fields }`. Tree builder stamps `run_as` directly on
 * the tree via User_Selector_Service; the already-final FieldsOutput passes
 * through verbatim.
 *
 * Reads tree keys as literals; emits wire keys from Wire_Key.
 */
final class Flow_Wire_Serializer {

	/**
	 * Trigger-logic operators the wire accepts. Anything else falls back to the default rather than reaching the builder.
	 *
	 * @var array<string>
	 */
	private const VALID_OPERATORS = array( 'all', 'any' );

	/**
	 * Projects each action appearing in `content[]`.
	 *
	 * @var Item_Wire_Serializer
	 */
	private $item;

	/**
	 * Projects each filter, loop or delay block appearing in `content[]`.
	 *
	 * @var Block_Wire_Serializer
	 */
	private $block;

	/**
	 * Projects field arrays wherever the flow shape carries them.
	 *
	 * @var Field_Output_Serializer
	 */
	private $fields;

	/**
	 * Wire up the serializers a recipe flow needs.
	 *
	 * Collaborators are injectable so tests can substitute fakes; production
	 * passes nothing and gets the real serializers.
	 *
	 * @param Item_Wire_Serializer|null    $item   Item projector.
	 * @param Block_Wire_Serializer|null   $block  Block projector.
	 * @param Field_Output_Serializer|null $fields Field projector.
	 */
	public function __construct(
		?Item_Wire_Serializer $item = null,
		?Block_Wire_Serializer $block = null,
		?Field_Output_Serializer $fields = null
	) {
		$this->fields = $fields ?? new Field_Output_Serializer();
		$modifier     = new Modifier_Wire_Serializer( $this->fields );
		$this->item   = $item ?? new Item_Wire_Serializer( $this->fields );
		$this->block  = $block ?? new Block_Wire_Serializer( $this->fields, $modifier, $this->item );
	}

	/**
	 * Project the canonical tree to the flow's strict 3-key wire shape.
	 *
	 * @param array $tree Schema v3 canonical tree.
	 * @return array{entry: array{operator: string, triggers: list<array>}, run_as: array|null, content: list<array>}
	 */
	public function serialize( array $tree ): array {
		return array(
			Wire_Key::ENTRY   => $this->serialize_entry( $tree ),
			Wire_Key::RUN_AS  => $this->serialize_run_as( $tree ),
			Wire_Key::CONTENT => $this->serialize_content( $tree ),
		);
	}

	/**
	 * Build `flow.entry` from the tree's `trigger_logic` + `triggers[]`.
	 *
	 * @return array{operator: string, triggers: list<array>}
	 */
	private function serialize_entry( array $tree ): array {
		$logic = (string) ( $tree['trigger_logic'] ?? 'all' );
		if ( ! in_array( $logic, self::VALID_OPERATORS, true ) ) {
			$logic = 'all';
		}

		$triggers = array();
		$raw      = $tree['triggers'] ?? array();
		if ( is_array( $raw ) ) {
			foreach ( $raw as $trigger ) {
				if ( is_array( $trigger ) ) {
					$triggers[] = $this->item->serialize( $trigger );
				}
			}
		}

		return array(
			Wire_Key::OPERATOR => $logic,
			Wire_Key::TRIGGERS => $triggers,
		);
	}

	/**
	 * Emit `flow.run_as`: null, or `{ fields }` passed through verbatim.
	 *
	 * The User_Selector projection is the single producer of the run_as
	 * FieldsOutput (self-describing, incl. miscellaneous.show_label_in_sentence).
	 * Re-running Field_Output_Serializer here would strip that flag, so the
	 * already-final shape passes straight through.
	 *
	 * @param array $tree Schema v3 canonical tree.
	 * @return array{fields: array}|null
	 */
	private function serialize_run_as( array $tree ): ?array {
		$run_as = $tree['run_as'] ?? null;
		if ( ! is_array( $run_as ) || ! isset( $run_as['fields'] ) || ! is_array( $run_as['fields'] ) ) {
			return null;
		}

		return array( Wire_Key::FIELDS => $run_as['fields'] );
	}

	/**
	 * Project the heterogeneous `content[]` list. Each entry dispatches on
	 * its `kind` discriminator: `'block'` → Block_Wire_Serializer,
	 * `'action'` → Item_Wire_Serializer.
	 *
	 * Trigger entries don't appear here per the wire contract — they're
	 * in `entry.triggers[]` only.
	 *
	 * @return list<array>
	 */
	private function serialize_content( array $tree ): array {
		$out = array();
		$raw = $tree['content'] ?? array();
		if ( ! is_array( $raw ) ) {
			return $out;
		}

		foreach ( $raw as $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}
			$kind = (string) ( $entry['kind'] ?? '' );
			if ( 'block' === $kind ) {
				$out[] = $this->block->serialize( $entry );
				continue;
			}
			$out[] = $this->item->serialize( $entry );
		}
		return $out;
	}
}
