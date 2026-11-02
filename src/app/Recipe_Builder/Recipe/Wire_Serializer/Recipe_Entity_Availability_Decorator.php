<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Recipe_Builder\Recipe\Wire_Serializer;

use Uncanny_Automator\App\Recipe_Builder\Node_Tests\Services\Node_Tests_Tag_Resolver;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Wire_Serializer\Enums\Wire_Key;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Wire_Serializer\Enums\Wire_Node_Type;

/**
 * Projection-time decorator that attaches `availability` + `tags` to every flow
 * node in a serialized recipe `flow` — the triggers in `entry.triggers[]`, the
 * `content[]` list, and (recursively) each block's `paths[].content[]` and
 * `modifiers[]`, at any depth.
 *
 * Per-node resolution is delegated to Recipe_Entity_Availability_Resolver, which
 * is injectable so tests can supply a fake and a future cache can wrap it.
 * Runs at wire-projection time and is never cached on the schema tree, because
 * availability reflects site state (license tier + active plugins), not recipe
 * state.
 */
final class Recipe_Entity_Availability_Decorator {

	/**
	 * Per-node resolver. Anything exposing `resolve( array $node ): array`.
	 *
	 * @var Recipe_Entity_Availability_Resolver|object
	 */
	private $resolver;

	/**
	 * Per-node tests-tag resolver (registry-gated; null-op for test-less definitions).
	 *
	 * @var Node_Tests_Tag_Resolver
	 */
	private Node_Tests_Tag_Resolver $node_tests;

	/**
	 * Lazy-default DI: production gets the real resolver; tests inject a fake.
	 *
	 * @param Recipe_Entity_Availability_Resolver|object|null $resolver
	 * @param Node_Tests_Tag_Resolver|null                    $node_tests
	 */
	public function __construct( $resolver = null, ?Node_Tests_Tag_Resolver $node_tests = null ) {
		$this->resolver   = $resolver ?? new Recipe_Entity_Availability_Resolver();
		$this->node_tests = $node_tests ?? new Node_Tests_Tag_Resolver();
	}

	/**
	 * Decorate every node in the flow with `availability` + `tags`.
	 *
	 * @param array $flow The serialized `flow` structure ({ entry, run_as, content }).
	 * @return array The same flow with `availability` + `tags` added to every node.
	 */
	public function decorate( array $flow ): array {
		if ( isset( $flow[ Wire_Key::ENTRY ][ Wire_Key::TRIGGERS ] ) && is_array( $flow[ Wire_Key::ENTRY ][ Wire_Key::TRIGGERS ] ) ) {
			$flow[ Wire_Key::ENTRY ][ Wire_Key::TRIGGERS ] = $this->decorate_list( $flow[ Wire_Key::ENTRY ][ Wire_Key::TRIGGERS ] );
		}

		if ( isset( $flow[ Wire_Key::CONTENT ] ) && is_array( $flow[ Wire_Key::CONTENT ] ) ) {
			$flow[ Wire_Key::CONTENT ] = $this->decorate_list( $flow[ Wire_Key::CONTENT ] );
		}

		return $flow;
	}

	/**
	 * Decorate each node in a list, skipping non-array entries.
	 *
	 * @param array $nodes A list of serialized flow nodes.
	 * @return array The list with each node decorated.
	 */
	private function decorate_list( array $nodes ): array {
		foreach ( $nodes as $i => $node ) {
			if ( is_array( $node ) ) {
				$nodes[ $i ] = $this->decorate_node( $node );
			}
		}

		return $nodes;
	}

	/**
	 * Attach `availability` + `tags` to one node, then recurse into a block's
	 * `modifiers[]` and `paths[].content[]`.
	 *
	 * @param array $node A single serialized flow node.
	 * @return array The node with `availability` + `tags` added (children decorated).
	 */
	private function decorate_node( array $node ): array {
		$resolved                       = $this->resolver->resolve( $node );
		$node[ Wire_Key::AVAILABILITY ] = $resolved[ Wire_Key::AVAILABILITY ];
		$node[ Wire_Key::TAGS ]         = $resolved[ Wire_Key::TAGS ];

		// The tests tag (Tests passed / Tests failed / Tests skipped) is hidden for now. Uncomment to show it on nodes again.
		// $tests_tag = $this->node_tests->resolve( $node );
		// if ( null !== $tests_tag ) {
		//     $node[ Wire_Key::TAGS ][] = $tests_tag;
		// }

		if ( Wire_Node_Type::BLOCK !== ( $node[ Wire_Key::TYPE ] ?? '' ) ) {
			return $node;
		}

		if ( isset( $node[ Wire_Key::MODIFIERS ] ) && is_array( $node[ Wire_Key::MODIFIERS ] ) ) {
			$node[ Wire_Key::MODIFIERS ] = $this->decorate_list( $node[ Wire_Key::MODIFIERS ] );
		}

		if ( isset( $node[ Wire_Key::PATHS ] ) && is_array( $node[ Wire_Key::PATHS ] ) ) {
			foreach ( $node[ Wire_Key::PATHS ] as $p => $path ) {
				if ( is_array( $path ) && isset( $path[ Wire_Key::CONTENT ] ) && is_array( $path[ Wire_Key::CONTENT ] ) ) {
					$node[ Wire_Key::PATHS ][ $p ][ Wire_Key::CONTENT ] = $this->decorate_list( $path[ Wire_Key::CONTENT ] );
				}
			}
		}

		return $node;
	}
}
