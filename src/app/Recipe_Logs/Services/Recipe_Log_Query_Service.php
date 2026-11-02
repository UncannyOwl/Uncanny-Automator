<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Recipe_Logs\Services;

use Uncanny_Automator\App\Infrastructure\Database\Database;
use Uncanny_Automator\App\Recipe_Builder\Block\Migration\Legacy_Storage_Migration_Exception;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Schema\Recipe_Schema_Reader;
use Uncanny_Automator\App\Recipe_Logs\Wire_Serializer\Iteration_Label_Resolver;
use Uncanny_Automator\App\Recipe_Logs\Wire_Serializer\Log_Iteration_Serializer;
use Uncanny_Automator\App\Recipe_Logs\Wire_Serializer\Recipe_Log_Wire_Serializer;
use Uncanny_Automator\App\Recipe_Logs\Wire_Serializer\Run_Data_Bundle;
use Uncanny_Automator\App\Recipe_Builder\Block\Enums\Block_Type;
use Uncanny_Automator\App\Recipe_Builder\Field\Value_Objects\Field_Context;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Wire_Serializer\Enums\Wire_Node_Type;

/**
 * Read-only orchestrator for the recipe-log parent payload: resolves the run,
 * loads the schema tree through Recipe_Schema_Reader, prefetches all run data
 * into a Run_Data_Bundle, and hands it to the wire serializer.
 */
final class Recipe_Log_Query_Service {

	/**
	 * Shared singleton instance.
	 *
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * Recipe-log wire serializer.
	 *
	 * @var Recipe_Log_Wire_Serializer
	 */
	private $serializer;

	/**
	 * Iteration-entry serializer for the iterations sub-resource.
	 *
	 * @var Log_Iteration_Serializer
	 */
	private $iterations;

	/**
	 * Iteration label resolver.
	 *
	 * @var Iteration_Label_Resolver
	 */
	private $labels;

	/**
	 * The one tree read seam.
	 *
	 * @var Recipe_Schema_Reader
	 */
	private $reader;

	/**
	 * Wires up the service's collaborators, defaulting each to its production implementation.
	 *
	 * @param Recipe_Log_Wire_Serializer|null $serializer Wire serializer.
	 * @param Log_Iteration_Serializer|null   $iterations Iteration serializer.
	 * @param Iteration_Label_Resolver|null   $labels     Label resolver.
	 * @param Recipe_Schema_Reader|null       $reader     Tree reader; defaults to the shared instance.
	 */
	public function __construct( ?Recipe_Log_Wire_Serializer $serializer = null, ?Log_Iteration_Serializer $iterations = null, ?Iteration_Label_Resolver $labels = null, ?Recipe_Schema_Reader $reader = null ) {
		$this->serializer = $serializer ?? new Recipe_Log_Wire_Serializer();
		$this->iterations = $iterations ?? new Log_Iteration_Serializer();
		$this->labels     = $labels ?? new Iteration_Label_Resolver();
		$this->reader     = $reader ?? Recipe_Schema_Reader::instance();
	}

	/**
	 * The shared singleton instance, created on first access.
	 *
	 * @return self The singleton instance.
	 */
	public static function instance(): self {
		return self::$instance ??= new self();
	}

	/**
	 * Build the recipe-log payload, or a WP_Error on a bad id triple.
	 *
	 * @param int $recipe_id  Recipe post id.
	 * @param int $run_number 1-indexed run number.
	 * @param int $log_id     Recipe-log row id.
	 * @return array<string,mixed>|\WP_Error The wire ModelRecipeLog payload, or a WP_Error on a bad id triple.
	 */
	public function get_log_payload( int $recipe_id, int $run_number, int $log_id ) {
		$store = Database::get_execution_log_store();

		$log_row = $store->get_recipe_log_row_by_ids( $recipe_id, $run_number, $log_id );
		if ( null === $log_row ) {
			return $this->not_found();
		}
		if ( ! get_post( $recipe_id ) ) {
			return $this->not_found();
		}

		try {
			$tree = $this->reader->get_or_build( $recipe_id );
		} catch ( Legacy_Storage_Migration_Exception $e ) {
			return $this->migration_failed( $e );
		}
		if ( ! is_array( $tree ) ) {
			return $this->not_found();
		}

		$bundle = $this->build_bundle( $recipe_id, $run_number, $log_id, $log_row, $store, $tree );

		return $this->serializer->serialize( $recipe_id, $run_number, $log_id, $tree, $bundle );
	}

	/**
	 * Build one page of the iterations sub-resource for one node inside a
	 * run, or a WP_Error on a bad id triple. Only the ITEM arm serves rows —
	 * per-iteration detail belongs to loop-body actions (product ruling
	 * 2026-07-16, matching the legacy per-action loop-runs endpoint). BLOCK
	 * and MODIFIER nodes return structural empty pages: wire-supported so
	 * the FE gates degrade safely, never populated. An unknown numeric node
	 * falls back to the ITEM arm (orphan-safe), an unknown non-numeric node
	 * to the MODIFIER arm. Pro absent or loop never ran → empty page, never
	 * fatal.
	 *
	 * @param int        $recipe_id  Recipe post id.
	 * @param int        $run_number 1-indexed run number.
	 * @param int        $log_id     Recipe-log row id.
	 * @param int|string $node_id    Node id (numeric post id or string modifier id).
	 * @param int        $page       1-indexed page (pre-clamped by the route).
	 * @param int        $per_page   Page size 1–100 (pre-clamped by the route).
	 * @param string     $search     Search term; '' disables filtering.
	 * @return array<string,mixed>|\WP_Error The wire iterations envelope, or a WP_Error on a bad id triple.
	 */
	public function get_iterations_payload( int $recipe_id, int $run_number, int $log_id, $node_id, int $page, int $per_page, string $search ) {
		$store = Database::get_execution_log_store();

		$log_row = $store->get_recipe_log_row_by_ids( $recipe_id, $run_number, $log_id );
		if ( null === $log_row ) {
			return $this->not_found();
		}

		try {
			$tree = $this->reader->get_or_build( $recipe_id ) ?? array();
		} catch ( Legacy_Storage_Migration_Exception $e ) {
			return $this->migration_failed( $e );
		}

		$resolved = $this->resolve_node_kind( $tree, $node_id );

		if ( 'modifier' === $resolved['kind'] ) {
			return $this->iterations_envelope( Wire_Node_Type::BLOCK_MODIFIER, array(), 0, $page, $per_page );
		}

		if ( 'block' === $resolved['kind'] ) {
			return $this->iterations_envelope( Wire_Node_Type::BLOCK, array(), 0, $page, $per_page );
		}

		return $this->item_iterations( $store, $store->get_loop_entries( $log_id, $run_number ), $resolved, $recipe_id, $run_number, $log_id, (int) $node_id, ( $page - 1 ) * $per_page, $page, $per_page, $search );
	}

	/**
	 * ITEM arm: per-execution rows for one loop-body action. When the tree
	 * did not resolve an enclosing loop (orphan node), the loop id is
	 * recovered from the first row's filter_id so labels and kind-aware
	 * search still work.
	 *
	 * @param \Uncanny_Automator\App\Infrastructure\Database\Interfaces\Execution_Log_Store $store Execution store.
	 * @param array<int,array<string,mixed>> $loop_entries Loop rows keyed by loop_id.
	 * @param array<string,mixed>            $resolved     Kind-resolution result (kind, loop_id).
	 * @param int                            $recipe_id    Recipe id.
	 * @param int                            $run_number   Run number.
	 * @param int                            $log_id       Log id.
	 * @param int                            $action_id    Loop-body action post id.
	 * @param int                            $offset       Row offset.
	 * @param int                            $page         Page.
	 * @param int                            $per_page     Page size.
	 * @param string                         $search       Search term.
	 * @return array<string,mixed> The wire envelope.
	 */
	private function item_iterations( $store, array $loop_entries, array $resolved, int $recipe_id, int $run_number, int $log_id, int $action_id, int $offset, int $page, int $per_page, string $search ): array {
		$loop_id = (int) ( $resolved['loop_id'] ?? 0 );
		if ( 0 === $loop_id || ! isset( $loop_entries[ $loop_id ] ) ) {
			$probe   = $store->get_action_iteration_rows( $recipe_id, $log_id, $run_number, $action_id, 1, 0, '', '' );
			$loop_id = empty( $probe ) ? 0 : $this->loop_id_from_process_id( (string) ( ( (array) $probe[0] )['filter_id'] ?? '' ) );
		}
		$parent    = $loop_entries[ $loop_id ] ?? null;
		$loop_type = (string) ( $parent['loop_type'] ?? '' );

		$total = $store->count_action_iteration_rows( $recipe_id, $log_id, $run_number, $action_id, $search, $loop_type );
		$rows  = $store->get_action_iteration_rows( $recipe_id, $log_id, $run_number, $action_id, $per_page, $offset, $search, $loop_type );

		$ctx = $this->item_field_context( $resolved );

		$iterations = array();
		foreach ( array_values( $rows ) as $position => $row ) {
			$row          = (array) $row;
			$iterations[] = $this->iterations->serialize_item_row(
				$row,
				$offset + $position,
				null !== $parent ? $this->labels->resolve( (int) ( $row['entity_id'] ?? 0 ), $loop_type, $parent ) : null,
				$ctx
			);
		}
		return $this->iterations_envelope( Wire_Node_Type::ITEM, $iterations, $total, $page, $per_page );
	}

	/**
	 * Field_Context for a tree-resolved item, or null when any catalog
	 * coordinate is missing (orphan node, corrupt tree entry). Null makes
	 * the serializer omit the per-iteration `fields` overlay — the
	 * catalog-defined-only contract has nothing to filter against.
	 *
	 * @param array<string,mixed> $resolved Kind-resolution result.
	 * @return Field_Context|null The context, or null.
	 */
	private function item_field_context( array $resolved ): ?Field_Context {
		$integration_code = (string) ( $resolved['integration_code'] ?? '' );
		$item_type        = (string) ( $resolved['item_type'] ?? '' );
		$code             = (string) ( $resolved['code'] ?? '' );

		if ( '' === $integration_code || '' === $item_type || '' === $code ) {
			return null;
		}

		return new Field_Context( $integration_code, $item_type, $code );
	}

	/**
	 * Resolve a node id against the schema tree: trigger/action → item (with
	 * its enclosing loop id), block → block, modifier → modifier. Not in
	 * tree: numeric → item (orphan-safe), non-numeric → modifier. Never a
	 * 404 — only the id triple 404s.
	 *
	 * @param array<string,mixed> $tree    Schema tree.
	 * @param int|string          $node_id Node id.
	 * @return array<string,mixed> The resolution result: kind + loop_id, plus the catalog identity for item matches.
	 */
	private function resolve_node_kind( array $tree, $node_id ): array {
		$needle = (string) $node_id;

		foreach ( (array) ( $tree['triggers'] ?? array() ) as $trigger ) {
			$trigger = (array) $trigger;
			if ( (string) ( $trigger['id'] ?? '' ) === $needle ) {
				return $this->item_result( $trigger, 0 );
			}
		}

		$found = $this->find_node_in_content( (array) ( $tree['content'] ?? array() ), $needle, 0 );
		if ( null !== $found ) {
			return $found;
		}

		return is_numeric( $node_id )
			? $this->item_result( array(), 0 )
			: array(
				'kind'    => 'modifier',
				'loop_id' => 0,
			);
	}

	/**
	 * Item-arm resolution result carrying the node's catalog identity (empty
	 * strings on the orphan fallback — the field overlay is then omitted).
	 *
	 * @param array<string,mixed> $node    Tree item node, or empty for the orphan fallback.
	 * @param int                 $loop_id Enclosing loop id, 0 when none.
	 * @return array{kind: string, loop_id: int, integration_code: string, item_type: string, code: string} The resolution result.
	 */
	private function item_result( array $node, int $loop_id ): array {
		return array(
			'kind'             => 'item',
			'loop_id'          => $loop_id,
			'integration_code' => (string) ( $node['integration_code'] ?? '' ),
			'item_type'        => (string) ( $node['item_type'] ?? $node['kind'] ?? '' ),
			'code'             => (string) ( $node['code'] ?? '' ),
		);
	}

	/**
	 * Depth-first content walk matching a node id with loose string
	 * comparison (modifier ids can be strings), threading the nearest
	 * enclosing loop id down through block paths.
	 *
	 * @param array<int,mixed> $nodes             Content nodes (items and blocks).
	 * @param string           $needle            Stringified node id.
	 * @param int              $enclosing_loop_id Nearest enclosing loop block id, 0 when none.
	 * @return array<string,mixed>|null The resolution result, or null when not found.
	 */
	private function find_node_in_content( array $nodes, string $needle, int $enclosing_loop_id ): ?array {
		foreach ( $nodes as $node ) {
			$node = (array) $node;
			$id   = (string) ( $node['id'] ?? '' );
			if ( 'block' === ( $node['kind'] ?? '' ) ) {
				if ( $id === $needle ) {
					return array(
						'kind'    => 'block',
						'loop_id' => (int) ( $node['id'] ?? 0 ),
					);
				}
				foreach ( (array) ( $node['modifiers'] ?? array() ) as $modifier ) {
					if ( (string) ( ( (array) $modifier )['id'] ?? '' ) === $needle ) {
						return array(
							'kind'    => 'modifier',
							'loop_id' => 0,
						);
					}
				}
				$child_loop_id = Block_Type::LOOP === (string) ( $node['block_type'] ?? '' ) ? (int) ( $node['id'] ?? 0 ) : $enclosing_loop_id;
				foreach ( (array) ( $node['paths'] ?? array() ) as $path ) {
					$found = $this->find_node_in_content( (array) ( ( (array) $path )['items'] ?? array() ), $needle, $child_loop_id );
					if ( null !== $found ) {
						return $found;
					}
				}
				continue;
			}
			if ( $id === $needle ) {
				return $this->item_result( $node, $enclosing_loop_id );
			}
		}
		return null;
	}

	/**
	 * Parse the loop id out of a process id
	 * (`loop_process_{loop}_{recipe}_{log}_{run}`), 0 when unparsable.
	 *
	 * @param string $process_id The items' filter_id / parent's process_id.
	 * @return int The loop id, or 0.
	 */
	private function loop_id_from_process_id( string $process_id ): int {
		if ( 1 === preg_match( '/^loop_process_(\d+)_\d+_\d+_\d+$/', $process_id, $matches ) ) {
			return (int) $matches[1];
		}
		return 0;
	}

	/**
	 * Assemble the iterations wire envelope. `totalPages` and `perPage` are
	 * camelCase ON PURPOSE (the FE response schema) while the request query
	 * uses snake_case — do not normalize either direction.
	 *
	 * @param string                          $node_type  item|block|block_modifier.
	 * @param array<int,array<string,mixed>>  $iterations Serialized iteration entries.
	 * @param int                             $total      Total matching iterations.
	 * @param int                             $page       1-indexed page.
	 * @param int                             $per_page   Page size.
	 * @return array<string,mixed> The envelope.
	 */
	private function iterations_envelope( string $node_type, array $iterations, int $total, int $page, int $per_page ): array {
		return array(
			'node_type'  => $node_type,
			'iterations' => $iterations,
			'total'      => $total,
			'totalPages' => $per_page > 0 ? (int) ceil( $total / $per_page ) : 0,
			'page'       => $page,
			'perPage'    => $per_page,
		);
	}

	/**
	 * Prefetch every store read for the run into an id-indexed bundle.
	 *
	 * @param int                 $recipe_id  Recipe id.
	 * @param int                 $run_number Run number.
	 * @param int                 $log_id     Log id.
	 * @param array<string,mixed> $log_row    Recipe-log row.
	 * @param \Uncanny_Automator\App\Infrastructure\Database\Interfaces\Execution_Log_Store $store Execution store.
	 * @param array<string,mixed> $tree       Schema tree (its node ids seed the creation-date map).
	 * @return Run_Data_Bundle The prefetched, id-indexed bundle.
	 */
	private function build_bundle( int $recipe_id, int $run_number, int $log_id, array $log_row, $store, array $tree ): Run_Data_Bundle {
		$action_rows  = $store->get_action_log_rows( $log_id );
		$trigger_rows = $store->get_trigger_log_rows( $log_id );

		$action_log_ids  = array_map( static fn( $r ) => (int) $r['ID'], $action_rows );
		$trigger_log_ids = array_map( static fn( $r ) => (int) $r['ID'], $trigger_rows );

		return new Run_Data_Bundle(
			array(
				'recipe_log_row'     => $log_row,
				'trigger_rows'       => $trigger_rows,
				'action_rows'        => $action_rows,
				'api_retries'        => Database::get_api_log_store()->get_api_retries( $log_id ),
				'api_logged'         => Database::get_api_log_store()->get_api_logged_action_log_ids( $log_id ),
				'tokens_map'         => $store->get_tokens_record_map( $recipe_id, $log_id, $run_number ),
				'action_meta_bag'    => $store->get_action_log_meta_bag( $action_log_ids ),
				'trigger_meta_bag'   => $store->get_trigger_log_meta_bag( $trigger_log_ids ),
				'trigger_properties' => $store->get_trigger_log_meta_values( $trigger_log_ids, 'properties' ),
				'recipe_meta_bag'    => $store->get_recipe_log_meta_bag( $log_id ),
				'error_rows'         => Database::get_action_error_store()->get_by_recipe_log( $log_id ),
				'loop_entries'       => $store->get_loop_entries( $log_id, $run_number ),
				'loop_breakdowns'    => $store->get_loop_item_status_breakdown( $log_id, $run_number ),
				'node_created_map'   => $this->collect_node_created_map( $tree ),
			)
		);
	}

	/**
	 * Creation datetimes (site-local post_date) for every tree node post —
	 * the input for Node_Run_Visibility's "existed when the run started"
	 * check, which keeps later-added items out of historical runs. get_post
	 * rides the object cache; recipe trees are small.
	 *
	 * @param array<string,mixed> $tree Schema tree.
	 * @return array<int,string> Node post id => post_date.
	 */
	private function collect_node_created_map( array $tree ): array {
		$ids = array();
		$this->collect_node_ids( (array) ( $tree['triggers'] ?? array() ), $ids );
		$this->collect_node_ids( (array) ( $tree['content'] ?? array() ), $ids );

		$map = array();
		foreach ( array_unique( $ids ) as $id ) {
			$post = get_post( $id );
			if ( null !== $post ) {
				$map[ $id ] = (string) $post->post_date;
			}
		}
		return $map;
	}

	/**
	 * Collect every numeric node id (items, blocks, modifiers) under a node
	 * list, recursing through block paths.
	 *
	 * @param array<int,mixed> $nodes Tree nodes.
	 * @param array<int,int>   $ids   Accumulator, appended in place.
	 * @return void
	 */
	private function collect_node_ids( array $nodes, array &$ids ): void {
		foreach ( $nodes as $node ) {
			$node = (array) $node;
			if ( is_numeric( $node['id'] ?? null ) ) {
				$ids[] = (int) $node['id'];
			}
			foreach ( (array) ( $node['modifiers'] ?? array() ) as $modifier ) {
				$modifier_id = ( (array) $modifier )['id'] ?? null;
				if ( is_numeric( $modifier_id ) ) {
					$ids[] = (int) $modifier_id;
				}
			}
			foreach ( (array) ( $node['paths'] ?? array() ) as $path ) {
				$this->collect_node_ids( (array) ( ( (array) $path )['items'] ?? array() ), $ids );
			}
		}
	}

	/**
	 * Build the standard "recipe log not found" REST error.
	 *
	 * @return \WP_Error The 404 rest_log_not_found error.
	 */
	private function not_found(): \WP_Error {
		return new \WP_Error( 'rest_log_not_found', esc_html_x( 'Recipe log not found.', 'Recipe log REST error', 'uncanny-automator' ), array( 'status' => 404 ) );
	}

	/**
	 * The legacy storage migration failed for this recipe in this request; the next read retries.
	 *
	 * @param Legacy_Storage_Migration_Exception $e The failure.
	 * @return \WP_Error The 500 rest_log_legacy_migration_failed error.
	 */
	private function migration_failed( Legacy_Storage_Migration_Exception $e ): \WP_Error {
		return new \WP_Error( 'rest_log_legacy_migration_failed', esc_html( $e->getMessage() ), array( 'status' => 500 ) );
	}
}
