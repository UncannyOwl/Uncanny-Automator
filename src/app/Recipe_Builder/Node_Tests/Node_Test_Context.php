<?php
declare(strict_types=1);
namespace Uncanny_Automator\App\Recipe_Builder\Node_Tests;

/**
 * Per-request node context handed to providers.
 *
 * Carries the node's identity and its serialized wire array (including
 * `fields`, the saved FieldsOutput). recipe_id is 0 when the caller has
 * no recipe scope (tag resolution) — get_tests() must not depend on it.
 */
final class Node_Test_Context {

	/**
	 * Recipe post id (0 when unscoped).
	 *
	 * @var int
	 */
	private int $recipe_id;

	/**
	 * Node kind: item | block | block_modifier.
	 *
	 * @var string
	 */
	private string $kind;

	/**
	 * Node post id.
	 *
	 * @var int
	 */
	private int $node_id;

	/**
	 * The node's serialized wire array.
	 *
	 * @var array
	 */
	private array $node;

	/**
	 * Freeze the context.
	 *
	 * @param int    $recipe_id Recipe post id (0 when unscoped).
	 * @param string $kind      Node kind.
	 * @param int    $node_id   Node post id.
	 * @param array  $node      Serialized wire node.
	 */
	public function __construct( int $recipe_id, string $kind, int $node_id, array $node ) {
		$this->recipe_id = $recipe_id;
		$this->kind      = $kind;
		$this->node_id   = $node_id;
		$this->node      = $node;
	}

	/**
	 * The recipe post id (0 when unscoped).
	 *
	 * @return int
	 */
	public function get_recipe_id(): int {
		return $this->recipe_id;
	}

	/**
	 * The node kind.
	 *
	 * @return string
	 */
	public function get_kind(): string {
		return $this->kind;
	}

	/**
	 * The node post id.
	 *
	 * @return int
	 */
	public function get_node_id(): int {
		return $this->node_id;
	}

	/**
	 * The serialized wire node.
	 *
	 * @return array
	 */
	public function get_node(): array {
		return $this->node;
	}

	/**
	 * The node's saved FieldsOutput (empty-object wire shapes normalized to array).
	 *
	 * @return array
	 */
	public function get_fields(): array {
		$fields = $this->node['fields'] ?? array();

		return is_array( $fields ) ? $fields : (array) $fields;
	}
}
