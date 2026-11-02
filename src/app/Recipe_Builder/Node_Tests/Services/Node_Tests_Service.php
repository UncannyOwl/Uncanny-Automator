<?php
declare(strict_types=1);
namespace Uncanny_Automator\App\Recipe_Builder\Node_Tests\Services;

use Uncanny_Automator\App\Recipe_Builder\Node_Tests\Node_Test_Context;
use Uncanny_Automator\App\Recipe_Builder\Node_Tests\Node_Test_Provider;
use Uncanny_Automator\App\Recipe_Builder\Node_Tests\Registry\Node_Tests_Registry;
use Uncanny_Automator\App\Recipe_Builder\Node_Tests\Value_Objects\Run_Result;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Wire_Serializer\Recipe_Wire_Service;

/**
 * Node-tests orchestration behind the four REST endpoints.
 *
 * Locates the node in its recipe's wire (existence + ownership in one
 * check), dispatches to the registered provider, and applies the
 * persistence rules: a terminal result from run/run-status always
 * persists; the cancel path never writes (spec D3). Freshness is not
 * decided here — the write-time invalidation the update paths perform
 * (Slice 2) is what keeps a stored run current; this service only guards
 * against a listen capture that outlives an intervening invalidation
 * (the armed-listen marker, cleared by that same invalidation). A missing
 * provider (Pro deactivated mid-session, spec D5) lists as `tests: []`
 * and runs as an explanatory failed result — never a bare error.
 */
final class Node_Tests_Service {

	/**
	 * Last-run persistence.
	 *
	 * @var Node_Test_Run_Store
	 */
	private Node_Test_Run_Store $runs;

	/**
	 * Wire provider: fn( int $recipe_id ): ?array. Test seam.
	 *
	 * @var callable
	 */
	private $wire_provider;

	/**
	 * Lazy-default DI per Rule 9c.
	 *
	 * @param Node_Test_Run_Store|null $runs          Run store (test seam).
	 * @param callable|null            $wire_provider Wire source (test seam).
	 */
	public function __construct( ?Node_Test_Run_Store $runs = null, ?callable $wire_provider = null ) {
		$this->runs          = $runs ?? new Node_Test_Run_Store();
		$this->wire_provider = $wire_provider ?? function ( int $recipe_id ): ?array {
			return Recipe_Wire_Service::instance()->get_wire_array( $recipe_id );
		};
	}

	/**
	 * The §3 list body, or null when the node is not in this recipe.
	 *
	 * @param int    $recipe_id Recipe post id.
	 * @param string $kind      item | block | block_modifier.
	 * @param int    $node_id   Node post id.
	 * @return array|null `{ tests: [...] }` or null (route → 404).
	 */
	public function list_tests( int $recipe_id, string $kind, int $node_id ): ?array {
		$node = $this->locate( $recipe_id, $kind, $node_id );
		if ( null === $node ) {
			return null;
		}

		$provider = $this->provider_for_node( $kind, $node );
		if ( null === $provider ) {
			return array( 'tests' => array() );
		}

		$context = new Node_Test_Context( $recipe_id, $kind, $node_id, $node );

		$tests = array();
		foreach ( $provider->get_tests( $context ) as $test ) {
			$wire = $test->to_wire();

			$last = $this->runs->get_last_result( $kind, $node, $test->get_id() );
			if ( null !== $last ) {
				$wire['last_result'] = $last;
			}

			$tests[] = $wire;
		}

		return array( 'tests' => $tests );
	}

	/**
	 * Execute or arm one test. A terminal result always persists — there is
	 * no per-value comparison anywhere in this call; a listen arm sets the
	 * armed marker so the status poller knows no invalidation has cleared
	 * it out from under the capture.
	 *
	 * @param int    $recipe_id   Recipe post id.
	 * @param string $kind        Node kind.
	 * @param int    $node_id     Node post id.
	 * @param string $test_id     Test id.
	 * @param array  $node_values FieldsOutput from the FE (saved + unsaved edits).
	 * @param array  $test_values FieldsOutput for the test's own fields.
	 * @return array|null Wire run-result, or null when the node is not in this recipe.
	 */
	public function run( int $recipe_id, string $kind, int $node_id, string $test_id, array $node_values, array $test_values ): ?array {
		$node = $this->locate( $recipe_id, $kind, $node_id );
		if ( null === $node ) {
			return null;
		}

		$provider = $this->provider_for_node( $kind, $node );
		if ( null === $provider ) {
			return $this->unavailable_result()->to_wire();
		}

		$context = new Node_Test_Context( $recipe_id, $kind, $node_id, $node );

		if ( ! $this->test_exists( $provider, $test_id, $context ) ) {
			return $this->unknown_test_result()->to_wire();
		}

		$result = $this->guarded(
			static function () use ( $provider, $test_id, $node_values, $test_values, $context ) {
				return $provider->run( $test_id, $node_values, $test_values, $context );
			}
		);

		if ( $result->is_terminal() ) {
			$this->runs->record( $kind, $node, $test_id, $result );
			$this->runs->clear_pending_marker( $kind, $node, $test_id );
		} elseif ( 'running' === $result->get_status() ) {
			$this->runs->set_pending_marker( $kind, $node, $test_id );
		}

		return $result->to_wire();
	}

	/**
	 * Poll an armed listen test. A terminal capture persists only while its
	 * armed marker is still present — a marker an intervening invalidating
	 * save has already cleared means the capture displays in this response
	 * but is not written (mid-listen divergence).
	 *
	 * @param int    $recipe_id Recipe post id.
	 * @param string $kind      Node kind.
	 * @param int    $node_id   Node post id.
	 * @param string $test_id   Test id.
	 * @return array|null Wire run-result, or null when the node is not in this recipe.
	 */
	public function run_status( int $recipe_id, string $kind, int $node_id, string $test_id ): ?array {
		$node = $this->locate( $recipe_id, $kind, $node_id );
		if ( null === $node ) {
			return null;
		}

		$provider = $this->provider_for_node( $kind, $node );
		if ( null === $provider ) {
			return $this->unavailable_result()->to_wire();
		}

		$context = new Node_Test_Context( $recipe_id, $kind, $node_id, $node );

		if ( ! $this->test_exists( $provider, $test_id, $context ) ) {
			return $this->unknown_test_result()->to_wire();
		}

		$result = $this->guarded(
			static function () use ( $provider, $test_id, $context ) {
				return $provider->status( $test_id, $context );
			}
		);

		if ( $result->is_terminal() ) {
			// Unlocked check-then-write: a concurrent save's invalidation can
			// land between has_pending_marker() and record(), re-materializing
			// a result the wipe was meant to cover. Accepted — the builder is
			// a single-author surface, the window is one poll tick, and any
			// later edit re-invalidates; a lock here costs more than the
			// stale row it prevents.
			if ( $this->runs->has_pending_marker( $kind, $node, $test_id ) ) {
				$this->runs->record( $kind, $node, $test_id, $result );
			}
			$this->runs->clear_pending_marker( $kind, $node, $test_id );
		}

		return $result->to_wire();
	}

	/**
	 * Stop an armed listen test. NEVER persists (spec D3) — a timeout is
	 * absence of evidence, and cancelled is an undo.
	 *
	 * @param int    $recipe_id Recipe post id.
	 * @param string $kind      Node kind.
	 * @param int    $node_id   Node post id.
	 * @param string $test_id   Test id.
	 * @param string $reason    'cancelled' or 'timed_out'.
	 * @return array|null Wire run-result, or null when the node is not in this recipe.
	 */
	public function cancel( int $recipe_id, string $kind, int $node_id, string $test_id, string $reason ): ?array {
		$node = $this->locate( $recipe_id, $kind, $node_id );
		if ( null === $node ) {
			return null;
		}

		$provider = $this->provider_for_node( $kind, $node );
		if ( null === $provider ) {
			$this->runs->clear_pending_marker( $kind, $node, $test_id );

			return Run_Result::not_run()->to_wire();
		}

		$context = new Node_Test_Context( $recipe_id, $kind, $node_id, $node );

		if ( ! $this->test_exists( $provider, $test_id, $context ) ) {
			return Run_Result::not_run()->to_wire();
		}

		$result = $this->guarded(
			static function () use ( $provider, $test_id, $reason, $context ) {
				return $provider->cancel( $test_id, $reason, $context );
			}
		);
		$this->runs->clear_pending_marker( $kind, $node, $test_id );

		return $result->to_wire();
	}

	/**
	 * Execute one provider call, converting any escape — a throwing
	 * integration filter, a legacy handler fatal — into the §8 explanatory
	 * failed result. The wire never surfaces a bare error, for third-party
	 * providers as much as ours (D5 spirit).
	 *
	 * @param callable $dispatch Provider call returning a Run_Result.
	 * @return Run_Result
	 */
	private function guarded( callable $dispatch ): Run_Result {
		try {
			return $dispatch();
		} catch ( \Throwable $e ) {
			// Raw like every provider content path: the FE's uas-markdown
			// escapes at render time, so pre-escaping would double-encode.
			return Run_Result::failed(
				esc_html_x( 'The test could not run.', 'Node tests', 'uncanny-automator' ),
				$e->getMessage()
			);
		}
	}

	/**
	 * Locate the node inside its recipe's wire.
	 *
	 * @param int    $recipe_id Recipe post id.
	 * @param string $kind      Node kind.
	 * @param int    $node_id   Node post id.
	 * @return array|null
	 */
	private function locate( int $recipe_id, string $kind, int $node_id ): ?array {
		$wire = call_user_func( $this->wire_provider, $recipe_id );

		return is_array( $wire ) ? Node_Locator::locate( $wire, $kind, $node_id ) : null;
	}

	/**
	 * The provider for a located node's identity.
	 *
	 * @param string $kind Node kind.
	 * @param array  $node Serialized wire node.
	 * @return Node_Test_Provider|null
	 */
	private function provider_for_node( string $kind, array $node ): ?Node_Test_Provider {
		if ( 'block' === $kind ) {
			return Node_Tests_Registry::provider_for( 'block', '', (string) ( $node['block_type'] ?? '' ) );
		}

		return Node_Tests_Registry::provider_for(
			$kind,
			(string) ( $node['integration_code'] ?? '' ),
			(string) ( $node['code'] ?? '' )
		);
	}

	/**
	 * The spec-D5 result for a node whose provider is gone (stale FE session).
	 *
	 * @return Run_Result
	 */
	private function unavailable_result(): Run_Result {
		return Run_Result::failed(
			esc_html_x( 'This test is no longer available.', 'Node tests', 'uncanny-automator' ),
			esc_html_x( 'The plugin that provides it may have been deactivated. Reload the page and try again.', 'Node tests', 'uncanny-automator' )
		);
	}

	/**
	 * Whether the provider declares the requested test id.
	 *
	 * Guards the execution paths: an unknown id must never reach a
	 * provider's executor (a stale caller could otherwise trigger a live
	 * side effect such as a webhook send) nor persist run state under an
	 * arbitrary postmeta key.
	 *
	 * @param Node_Test_Provider $provider Provider.
	 * @param string             $test_id  Requested test id.
	 * @param Node_Test_Context  $context  Node context.
	 * @return bool
	 */
	private function test_exists( Node_Test_Provider $provider, string $test_id, Node_Test_Context $context ): bool {
		foreach ( $provider->get_tests( $context ) as $test ) {
			if ( $test->get_id() === $test_id ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * The failed result for a test id the provider does not declare.
	 *
	 * @return Run_Result
	 */
	private function unknown_test_result(): Run_Result {
		return Run_Result::failed(
			esc_html_x( 'This test does not exist for this node.', 'Node tests', 'uncanny-automator' ),
			esc_html_x( 'Reload the page and try again.', 'Node tests', 'uncanny-automator' )
		);
	}
}
