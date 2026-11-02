<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Recipe_Logs\Wire_Serializer;

use Uncanny_Automator\App\Bridge\Automator_Integration_Registry_Bridge;
use Uncanny_Automator\App\Bridge\Integration_Registry_Bridge;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Wire_Serializer\Deferred_Order_Decorator;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Wire_Serializer\Enums\Wire_Key;

/**
 * Walks the schema tree's entry triggers and content, decorating each node
 * with execution data, then appends an orphan appendix: log rows whose ids
 * are no longer present in the (current) tree because the item was deleted
 * since the run. Mirrors Flow_Wire_Serializer's entry/content split. The run's
 * deferred blocks carry `deferred_order`, ranked by the time the run scheduled
 * each for and then by the recipe's own rule.
 *
 * Reads tree keys as literals; emits wire keys from Wire_Key.
 */
final class Log_Flow_Serializer {

	/**
	 * Item serializer for entry triggers and top-level content items.
	 *
	 * @var Log_Item_Serializer
	 */
	private $items;

	/**
	 * Block serializer for top-level content blocks.
	 *
	 * @var Log_Block_Serializer
	 */
	private $blocks;

	/**
	 * Existed-at-run-start visibility resolver.
	 *
	 * @var Node_Run_Visibility
	 */
	private $visibility;

	/**
	 * Resolves an orphan's integration from its code.
	 *
	 * @var Integration_Registry_Bridge
	 */
	private $integrations;

	/**
	 * Numbers the run's deferred blocks.
	 *
	 * @since 8.0.0
	 * @var Deferred_Order_Decorator
	 */
	private $deferred;

	/**
	 * Wires up the flow serializer's collaborators, defaulting each to its production implementation.
	 *
	 * @param Log_Item_Serializer|null  $items      Item serializer.
	 * @param Log_Block_Serializer|null $blocks     Block serializer.
	 * @param Node_Run_Visibility|null  $visibility Visibility resolver.
	 * @param Integration_Registry_Bridge|null $integrations Integration resolver.
	 * @param Deferred_Order_Decorator|null    $deferred     Deferred-block numbering.
	 */
	public function __construct( ?Log_Item_Serializer $items = null, ?Log_Block_Serializer $blocks = null, ?Node_Run_Visibility $visibility = null, ?Integration_Registry_Bridge $integrations = null, ?Deferred_Order_Decorator $deferred = null ) {
		$this->items        = $items ?? new Log_Item_Serializer();
		$this->blocks       = $blocks ?? new Log_Block_Serializer();
		$this->visibility   = $visibility ?? new Node_Run_Visibility();
		$this->integrations = $integrations ?? new Automator_Integration_Registry_Bridge();
		$this->deferred     = $deferred ?? new Deferred_Order_Decorator();
	}

	/**
	 * Serialize the schema tree's entry triggers and content, appending an orphan appendix.
	 *
	 * @param array<string,mixed> $tree   Schema tree.
	 * @param Run_Data_Bundle     $bundle Run data.
	 * @return array<string,mixed> The wire flow object: entry (operator, triggers) and content.
	 */
	public function serialize( array $tree, Run_Data_Bundle $bundle ): array {
		$ctx = array(
			'inside_loop'     => false,
			'in_untaken_path' => false,
		);

		$tree_trigger_ids = array();
		$triggers         = array();
		foreach ( (array) ( $tree['triggers'] ?? array() ) as $trigger ) {
			$trigger            = (array) $trigger;
			$tree_trigger_ids[] = (int) ( $trigger['id'] ?? 0 );
			// Nodes added to the recipe after this run started are omitted
			// (Node_Run_Visibility) — ids still land in the tree-id set so
			// the orphan diff below stays complete.
			if ( $this->visibility->item_hidden( $trigger, $bundle ) ) {
				continue;
			}
			$triggers[] = $this->items->serialize( $trigger, $bundle, array_merge( $ctx, array( 'kind' => 'trigger' ) ) );
		}
		foreach ( array_diff( $bundle->all_trigger_ids(), $tree_trigger_ids ) as $orphan_id ) {
			$triggers[] = $this->items->serialize( $this->orphan_node( 'trigger', $orphan_id, $bundle ), $bundle, array_merge( $ctx, array( 'kind' => 'trigger' ) ) );
		}

		$tree_action_ids = array();
		$content         = array();
		foreach ( (array) ( $tree['content'] ?? array() ) as $node ) {
			$node = (array) $node;
			if ( 'block' === ( $node['kind'] ?? '' ) ) {
				$tree_action_ids[] = (int) ( $node['id'] ?? 0 );
				$tree_action_ids   = array_merge( $tree_action_ids, $this->blocks->collect_block_ids( $node['paths'] ?? array() ) );
				$tree_action_ids   = array_merge( $tree_action_ids, $this->blocks->collect_action_ids( $node['paths'] ?? array() ) );
				if ( $this->visibility->block_hidden( $node, $bundle ) ) {
					continue;
				}
				$content[] = $this->blocks->serialize( $node, $bundle, $ctx );
			} else {
				$tree_action_ids[] = (int) ( $node['id'] ?? 0 );
				if ( $this->visibility->item_hidden( $node, $bundle ) ) {
					continue;
				}
				$content[] = $this->items->serialize( $node, $bundle, $ctx );
			}
		}
		foreach ( array_diff( $bundle->all_action_ids(), $tree_action_ids ) as $orphan_id ) {
			$content[] = $this->items->serialize( $this->orphan_node( 'action', $orphan_id, $bundle ), $bundle, $ctx );
		}

		$flow = array(
			Wire_Key::ENTRY   => array(
				Wire_Key::OPERATOR => $this->operator( $tree, $bundle ),
				Wire_Key::TRIGGERS => $triggers,
			),
			Wire_Key::CONTENT => $content,
		);

		return $this->deferred->decorate(
			$flow,
			$tree,
			function ( array $block ) use ( $bundle ): ?int {
				return $this->blocks->scheduled_for( $block, $bundle );
			}
		);
	}

	/**
	 * Trigger operator from the run's triggers_logic meta, falling back to the tree, then 'all'.
	 *
	 * @param array<string,mixed> $tree   Schema tree.
	 * @param Run_Data_Bundle     $bundle Run data.
	 * @return string The resolved operator, 'all' or 'any'.
	 */
	private function operator( array $tree, Run_Data_Bundle $bundle ): string {
		$decoded = json_decode( (string) $bundle->recipe_meta( 'triggers_logic' ), true );
		$logic   = is_array( $decoded ) && isset( $decoded['logic'] ) ? (string) $decoded['logic'] : (string) ( $tree['trigger_logic'] ?? '' );
		$logic   = strtolower( $logic );
		return in_array( $logic, array( 'all', 'any' ), true ) ? $logic : 'all';
	}

	/**
	 * Synthesize a minimal tree node for an orphan log row (item deleted since the run).
	 *
	 * @param string          $kind      trigger|action.
	 * @param int             $id        Post id.
	 * @param Run_Data_Bundle $bundle    Run data.
	 * @return array<string,mixed> A minimal tree node shaped for the item serializer.
	 */
	private function orphan_node( string $kind, int $id, Run_Data_Bundle $bundle ): array {
		$code = $this->orphan_code( $kind, $id, $bundle );

		return array(
			'kind'             => $kind,
			'item_type'        => $kind,
			'id'               => $id,
			'integration_code' => $this->orphan_integration_code( $kind, $code ),
			'code'             => $code,
			'custom_name'      => null,
			'fields'           => array(),
		);
	}

	/**
	 * Recover a deleted item's code from what the run logged.
	 *
	 * @param string          $kind   'trigger' or 'action'.
	 * @param int             $id     Item post id.
	 * @param Run_Data_Bundle $bundle Run data.
	 * @return string Code, or '' when the run logged none.
	 */
	private function orphan_code( string $kind, int $id, Run_Data_Bundle $bundle ): string {
		if ( 'action' === $kind ) {
			$row = $bundle->action_row( $id );

			return null === $row ? '' : (string) ( $bundle->action_meta( (int) $row['ID'], 'code' ) ?? '' );
		}

		// The serialized trigger_object, the same source the legacy log endpoint
		// reads for deleted triggers. Confirmed present for a code-defined
		// (Trigger_Definition) trigger, carrying meta.code.
		foreach ( $bundle->trigger_rows_for( $id ) as $row ) {
			$object = automator_safe_unserialize( (string) ( $bundle->trigger_meta( (int) $row['ID'], 'trigger_object' ) ?? '' ) );
			$code   = is_array( $object ) ? (string) ( $object['meta']['code'] ?? '' ) : '';
			if ( '' !== $code ) {
				return $code;
			}
		}

		return '';
	}

	/**
	 * Resolve the integration from the code via the registry.
	 *
	 * Keyed on code rather than read from logged meta so it holds whether or not
	 * the run logged an integration.
	 *
	 * @param string $kind 'trigger' or 'action'.
	 * @param string $code Item code.
	 * @return string Integration code, or '' when unresolvable.
	 */
	private function orphan_integration_code( string $kind, string $code ): string {
		if ( '' === $code ) {
			return '';
		}

		$integration = 'trigger' === $kind
			? $this->integrations->get_integration_for_trigger( $code )
			: $this->integrations->get_integration_for_action( $code );

		return null === $integration ? '' : $integration;
	}
}
