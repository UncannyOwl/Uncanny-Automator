<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Recipe_Builder\Recipe\Value_Objects;

/**
 * The flow node a recipe violation points at.
 *
 * `node_type` must be one of the wire discriminators the Wire_Serializer
 * emits (`item` / `block` / `block_modifier`); `id` is carried verbatim
 * from the recipe tree (int for items, string for filter blocks) so the
 * wire id type always matches the recipe serializer's.
 */
final class Recipe_Violation_Target {

	/**
	 * Wire node-type discriminators a violation may target.
	 *
	 * @var array<int,string>
	 */
	private const NODE_TYPES = array( 'item', 'block', 'block_modifier' );

	/**
	 * Wire node-type discriminator of the targeted node.
	 *
	 * @var string
	 */
	private string $node_type;

	/**
	 * Node id, verbatim from the recipe tree.
	 *
	 * @var int|string
	 */
	private $id;

	/**
	 * Wrap and validate the target coordinates.
	 *
	 * @param string     $node_type Wire discriminator (`item` / `block` / `block_modifier`).
	 * @param int|string $id        Node id, verbatim from the recipe tree.
	 * @throws \InvalidArgumentException On an unknown node type or a non-int/string id.
	 */
	public function __construct( string $node_type, $id ) {
		if ( ! in_array( $node_type, self::NODE_TYPES, true ) ) {
			throw new \InvalidArgumentException( 'Violation target node_type must be item, block, or block_modifier.' );
		}
		if ( ! is_int( $id ) && ! is_string( $id ) ) {
			throw new \InvalidArgumentException( 'Violation target id must be an int or a string.' );
		}
		$this->node_type = $node_type;
		$this->id        = $id;
	}

	/**
	 * Wire shape: `{ node_type, id }`.
	 *
	 * @return array{node_type: string, id: int|string}
	 */
	public function to_wire(): array {
		return array(
			'node_type' => $this->node_type,
			'id'        => $this->id,
		);
	}
}
