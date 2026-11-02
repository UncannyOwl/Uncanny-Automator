<?php
declare(strict_types=1);
namespace Uncanny_Automator\App\Recipe_Builder\Node_Tests;

use Uncanny_Automator\App\Recipe_Builder\Node_Tests\Value_Objects\Run_Result;

/**
 * A node-tests provider: declares one definition's tests and executes them.
 *
 * Identity is (kind, integration_code, code). Blocks use kind 'block',
 * integration_code '' and code = block_type. Registering a provider is
 * what flips the definition's catalog `has_tests` to true.
 *
 * Identity values are matched byte-exact and case-sensitive against the
 * codes the integration already registers in the catalog (the same
 * strings Integration_Item_Discoverer normalizes) — a mismatched code
 * fails silently: has_tests simply stays false with no error anywhere.
 * Codes must not contain the pipe character (registry key delimiter).
 *
 * Request-mode providers return Run_Result::not_run() from status() and
 * cancel() — the transport never calls them for request tests.
 */
interface Node_Test_Provider {

	/**
	 * The node kind this provider serves: item | block | block_modifier.
	 *
	 * @return string
	 */
	public function kind(): string;

	/**
	 * The owning integration code ('' for blocks).
	 *
	 * @return string
	 */
	public function integration_code(): string;

	/**
	 * The item / modifier code, or the block_type for blocks.
	 *
	 * @return string
	 */
	public function code(): string;

	/**
	 * The definition's tests, display order = array order.
	 *
	 * @param Node_Test_Context $context Node context (recipe_id may be 0).
	 * @return \Uncanny_Automator\App\Recipe_Builder\Node_Tests\Value_Objects\Node_Test[]
	 */
	public function get_tests( Node_Test_Context $context ): array;

	/**
	 * Execute (request mode) or arm (listen mode) one test.
	 *
	 * @param string            $test_id     Test id from get_tests().
	 * @param array             $node_values FieldsOutput: saved values + unsaved edits. Unknown keys must be tolerated.
	 * @param array             $test_values FieldsOutput for the test's own ephemeral fields.
	 * @param Node_Test_Context $context     Node context.
	 * @return Run_Result Terminal for request mode; running for a listen arm.
	 */
	public function run( string $test_id, array $node_values, array $test_values, Node_Test_Context $context ): Run_Result;

	/**
	 * Poll an armed listen test.
	 *
	 * @param string            $test_id Test id.
	 * @param Node_Test_Context $context Node context.
	 * @return Run_Result Running until captured, then terminal.
	 */
	public function status( string $test_id, Node_Test_Context $context ): Run_Result;

	/**
	 * Stop an armed listen test.
	 *
	 * @param string            $test_id Test id.
	 * @param string            $reason  'cancelled' or 'timed_out'.
	 * @param Node_Test_Context $context Node context.
	 * @return Run_Result not_run for cancelled; failed (with copy) for timed_out. NEVER persisted.
	 */
	public function cancel( string $test_id, string $reason, Node_Test_Context $context ): Run_Result;
}
