<?php
declare(strict_types=1);
namespace Uncanny_Automator\App\Recipe_Builder\Node_Tests\Services;

use Uncanny_Automator\App\Infrastructure\Database\Stores\WP_Action_Store;
use Uncanny_Automator\App\Infrastructure\Database\Stores\WP_Block_Modifier_Store;
use Uncanny_Automator\App\Infrastructure\Database\Stores\WP_Block_Store;
use Uncanny_Automator\App\Infrastructure\Database\Stores\WP_Post_Store;
use Uncanny_Automator\App\Infrastructure\Database\Stores\WP_Recipe_Trigger_Store;
use Uncanny_Automator\App\Recipe_Builder\Node_Tests\Value_Objects\Run_Result;

/**
 * Last-run persistence for node tests.
 *
 * One postmeta map per node post: test_id → stored run row. Armed-listen
 * markers live in their own postmeta key (PENDING_META_KEY), so no test id
 * can collide with internal state. Terminal results from run/run-status
 * persist here; the cancel endpoint never writes (spec D3).
 *
 * Freshness is no longer detected here by comparing values (the removed
 * hash design) — it is guaranteed by write-time invalidation at the
 * update paths: {@see self::clear_all()} is the invalidation entry point,
 * called whenever a save actually changes a field value. A row surviving
 * here is therefore current by construction. Meta access goes through the
 * owning concrete store so the write stays POST_TYPE-scoped (Rule 3).
 */
final class Node_Test_Run_Store {

	/**
	 * Postmeta key holding the per-node run map.
	 *
	 * @var string
	 */
	const META_KEY = 'automator_node_test_runs';

	/**
	 * Postmeta key holding armed-listen markers, deliberately separate
	 * from the runs map so no test id can ever collide with internal state.
	 *
	 * @var string
	 */
	const PENDING_META_KEY = 'automator_node_test_pending';

	/**
	 * The test's stored terminal run, or null when none is on record.
	 *
	 * @param string $kind    item | block | block_modifier.
	 * @param array  $node    Serialized wire node (id + item_type drive store resolution).
	 * @param string $test_id Test id.
	 * @return array|null Wire run-result array or null.
	 */
	public function get_last_result( string $kind, array $node, string $test_id ): ?array {
		$runs = $this->read( $kind, $node, self::META_KEY );
		$row  = $runs[ $test_id ] ?? null;

		if ( ! is_array( $row ) ) {
			return null;
		}

		$result = Run_Result::from_stored( $row );

		return $result->is_terminal() ? $result->to_wire() : null;
	}

	/**
	 * Persist a terminal result as the test's last run.
	 *
	 * @param string     $kind    Node kind.
	 * @param array      $node    Serialized wire node.
	 * @param string     $test_id Test id.
	 * @param Run_Result $result  Terminal result.
	 * @return void
	 */
	public function record( string $kind, array $node, string $test_id, Run_Result $result ): void {
		if ( ! $result->is_terminal() ) {
			return;
		}

		$runs             = $this->read( $kind, $node, self::META_KEY );
		$runs[ $test_id ] = $result->to_stored();

		$this->write( $kind, $node, self::META_KEY, $runs );
	}

	/**
	 * Arm a listen test: mark it pending so a later capture knows no
	 * invalidating save has happened in between.
	 *
	 * @param string $kind    Node kind.
	 * @param array  $node    Serialized wire node.
	 * @param string $test_id Test id.
	 * @return void
	 */
	public function set_pending_marker( string $kind, array $node, string $test_id ): void {
		$pending             = $this->read( $kind, $node, self::PENDING_META_KEY );
		$pending[ $test_id ] = time();

		$this->write( $kind, $node, self::PENDING_META_KEY, $pending );
	}

	/**
	 * Whether the test is currently armed.
	 *
	 * @param string $kind    Node kind.
	 * @param array  $node    Serialized wire node.
	 * @param string $test_id Test id.
	 * @return bool
	 */
	public function has_pending_marker( string $kind, array $node, string $test_id ): bool {
		$pending = $this->read( $kind, $node, self::PENDING_META_KEY );

		return array_key_exists( $test_id, $pending );
	}

	/**
	 * Drop the armed-listen marker.
	 *
	 * @param string $kind    Node kind.
	 * @param array  $node    Serialized wire node.
	 * @param string $test_id Test id.
	 * @return void
	 */
	public function clear_pending_marker( string $kind, array $node, string $test_id ): void {
		$pending = $this->read( $kind, $node, self::PENDING_META_KEY );
		unset( $pending[ $test_id ] );

		// An empty map is dead weight: delete the row rather than persisting
		// a:0:{} — request-mode runs clear pending as hygiene, so this path
		// runs for every tested node. Delete-on-absent is a no-op, which
		// also sweeps legacy empty rows.
		if ( array() === $pending ) {
			$store = $this->store_for( $kind, $node );
			if ( null !== $store ) {
				$store->delete_meta( (int) ( $node['id'] ?? 0 ), self::PENDING_META_KEY );
			}
			return;
		}

		$this->write( $kind, $node, self::PENDING_META_KEY, $pending );
	}

	/**
	 * Drop every run and every armed marker for the node. The write-time
	 * invalidation entry point — called once a save has actually changed a
	 * field value, so any stored run (which executed against the values
	 * before that save) and any in-flight listen arm are no longer current.
	 *
	 * @param string $kind Node kind.
	 * @param array  $node Serialized wire node.
	 * @return void
	 */
	public function clear_all( string $kind, array $node ): void {
		$store = $this->store_for( $kind, $node );
		if ( null === $store ) {
			return;
		}

		$post_id = (int) ( $node['id'] ?? 0 );
		$store->delete_meta( $post_id, self::META_KEY );
		$store->delete_meta( $post_id, self::PENDING_META_KEY );
	}

	/**
	 * Read one of the node's postmeta maps.
	 *
	 * @param string $kind     Node kind.
	 * @param array  $node     Serialized wire node.
	 * @param string $meta_key Postmeta key to read (META_KEY or PENDING_META_KEY).
	 * @return array
	 */
	private function read( string $kind, array $node, string $meta_key ): array {
		$store = $this->store_for( $kind, $node );
		if ( null === $store ) {
			return array();
		}

		$map = $store->get_meta( (int) ( $node['id'] ?? 0 ), $meta_key, array() );

		return is_array( $map ) ? $map : array();
	}

	/**
	 * Write one of the node's postmeta maps.
	 *
	 * @param string $kind     Node kind.
	 * @param array  $node     Serialized wire node.
	 * @param string $meta_key Postmeta key to write (META_KEY or PENDING_META_KEY).
	 * @param array  $map      Full map for that key.
	 * @return void
	 */
	private function write( string $kind, array $node, string $meta_key, array $map ): void {
		$store = $this->store_for( $kind, $node );
		if ( null !== $store ) {
			$store->update_meta( (int) ( $node['id'] ?? 0 ), $meta_key, $map );
		}
	}

	/**
	 * The concrete store owning this node's post type.
	 *
	 * Resolves via the post's own `post_type` rather than `$kind` +
	 * `$node['item_type']`: the five node-post-type constants are a 1:1,
	 * unambiguous mapping onto the closed store family, so this needs
	 * nothing from the caller beyond `$node['id']` — notably, the
	 * write-time-invalidation listener (Slice 2) can pass a minimal
	 * `array( 'id' => $node_id )` with no `item_type` at all. `$kind`
	 * stays in the signature for symmetry with the sibling public methods
	 * (which use it for reasons of their own); it is not read here.
	 *
	 * WP_Action_Store requires the global $wpdb by
	 * constructor injection (no default); WP_Block_Store requires a
	 * Block_Modifier_Store collaborator — matching Database.php's wiring
	 * (WP_Recipe_Trigger_Store's optional arg defaults to global $wpdb).
	 * WP_Block_Modifier_Store is bare-constructible.
	 *
	 * @param string $kind Node kind (unused — see above).
	 * @param array  $node Serialized wire node.
	 * @return WP_Post_Store|null
	 */
	private function store_for( string $kind, array $node ): ?WP_Post_Store {
		global $wpdb;

		switch ( get_post_type( (int) ( $node['id'] ?? 0 ) ) ) {
			case AUTOMATOR_POST_TYPE_TRIGGER:
				return new WP_Recipe_Trigger_Store();
			case AUTOMATOR_POST_TYPE_ACTION:
				return new WP_Action_Store( $wpdb );
			case AUTOMATOR_POST_TYPE_BLOCK:
				return new WP_Block_Store( new WP_Block_Modifier_Store() );
			case AUTOMATOR_POST_TYPE_BLOCK_MODIFIER:
				return new WP_Block_Modifier_Store();
		}

		return null;
	}
}
