<?php
declare(strict_types=1);
namespace Uncanny_Automator\App\Recipe_Builder\Node_Tests\Services;

use Uncanny_Automator\App\Recipe_Builder\Node_Tests\Node_Test_Context;
use Uncanny_Automator\App\Recipe_Builder\Node_Tests\Registry\Node_Tests_Registry;

/**
 * The §6 tests tag, computed at wire-serialization time.
 *
 * Node tags are never stored — this resolver runs per node inside the
 * availability decorator, so every response carrying nodes (get-recipe,
 * item/block/modifier mutations) refreshes the tag for free. Scenario
 * precedence over the node's REQUIRED tests, evaluated against the STORED
 * run statuses only (write-time invalidation guarantees a stored row is
 * current — no values comparison here): any required test with a stored
 * failed run → tests-failed; else every required test has a stored passed
 * run → tests-passed; else → tests-skipped (never run, cleared by an
 * invalidating save, or optional-only).
 */
final class Node_Tests_Tag_Resolver {

	/**
	 * Run store.
	 *
	 * @var Node_Test_Run_Store
	 */
	private Node_Test_Run_Store $runs;

	/**
	 * Lazy-default DI per Rule 9c.
	 *
	 * @param Node_Test_Run_Store|null $runs Run store (test seam).
	 */
	public function __construct( ?Node_Test_Run_Store $runs = null ) {
		$this->runs = $runs ?? new Node_Test_Run_Store();
	}

	/**
	 * The node's tests tag, or null when its definition declares no tests.
	 *
	 * @param array $node Serialized wire node.
	 * @return array|null Tag array { type, scenario_id, label, color, icon } or null.
	 */
	public function resolve( array $node ): ?array {
		$kind = (string) ( $node['type'] ?? '' );

		if ( 'block' === $kind ) {
			$provider = Node_Tests_Registry::provider_for( 'block', '', (string) ( $node['block_type'] ?? '' ) );
		} else {
			$provider = Node_Tests_Registry::provider_for( $kind, (string) ( $node['integration_code'] ?? '' ), (string) ( $node['code'] ?? '' ) );
		}

		if ( null === $provider ) {
			return null;
		}

		$context = new Node_Test_Context( 0, $kind, (int) ( $node['id'] ?? 0 ), $node );

		$required_total  = 0;
		$required_passed = 0;
		$any_failed      = false;

		foreach ( $provider->get_tests( $context ) as $test ) {
			if ( ! $test->is_required() ) {
				continue;
			}
			++$required_total;

			$last = $this->runs->get_last_result( $kind, $node, $test->get_id() );
			if ( null === $last ) {
				continue;
			}
			if ( 'failed' === $last['status'] ) {
				$any_failed = true;
			}
			if ( 'passed' === $last['status'] ) {
				++$required_passed;
			}
		}

		if ( $any_failed ) {
			return self::tag( 'tests-failed', esc_html_x( 'Tests failed', 'Node tests', 'uncanny-automator' ), 'error', 'circle-xmark' );
		}
		if ( $required_total > 0 && $required_passed === $required_total ) {
			return self::tag( 'tests-passed', esc_html_x( 'Tests passed', 'Node tests', 'uncanny-automator' ), 'success', 'circle-check' );
		}

		return self::tag( 'tests-skipped', esc_html_x( 'Tests skipped', 'Node tests', 'uncanny-automator' ), 'warning', 'circle-minus' );
	}

	/**
	 * Assemble one tag object in the shared tag shape.
	 *
	 * @param string $scenario_id Scenario id.
	 * @param string $label       Display label.
	 * @param string $color       Tag color token.
	 * @param string $icon        Tag icon slug.
	 * @return array
	 */
	private static function tag( string $scenario_id, string $label, string $color, string $icon ): array {
		return array(
			'type'        => 'tests',
			'scenario_id' => $scenario_id,
			'label'       => $label,
			'color'       => $color,
			'icon'        => $icon,
		);
	}
}
