<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Recipe_Logs\Wire_Serializer;

use Uncanny_Automator\App\Recipe_Logs\Status\Log_Status_Registry;
use Uncanny_Automator\App\Recipe_Logs\Status\Enums\Log_Status_Code;
use Uncanny_Automator\App\Recipe_Logs\Status\Enums\Log_Status_Phase;
use Uncanny_Automator\App\Recipe_Builder\Block\Enums\Block_Path_Code;
use Uncanny_Automator\App\Recipe_Builder\Block\Enums\Block_Type;
use Uncanny_Automator\App\Recipe_Builder\Block\Registry\Block_Registry;
use Uncanny_Automator\App\Recipe_Builder\Block\Registry\WP_Block_Registry;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Wire_Serializer\Enums\Wire_Key;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Wire_Serializer\Enums\Wire_Node_Type;
use Uncanny_Automator\App\Bridge\Automator_Recipe_Runner_Bridge;
use Uncanny_Automator\App\Recipe_Runner\Blocks\Block_Control_Registry;
use Uncanny_Automator\App\Recipe_Runner\Services\Error_Code;
use Uncanny_Automator\Automator_Status;
use Uncanny_Automator\Rest\Endpoint\Log_Endpoint\Utils\Formatters_Utils;

/**
 * Serializes a block node (loop / filter / delay) to the strict
 * ModelRecipeLogBlock shape: static config + modifiers[] + paths[] (content
 * recursed) + an execution sub-object. Loop blocks carry a progress
 * aggregate and mark their body items inside_loop; filter blocks mark the
 * untaken path so its items resolve to skipped. A block the user can act on
 * right now carries execution.control, from the runner's control registry.
 *
 * Reads tree keys as literals; emits wire keys from Wire_Key.
 */
final class Log_Block_Serializer {

	/**
	 * Status derivation for blocks.
	 *
	 * @var Node_Status_Resolver
	 */
	private $resolver;

	/**
	 * Item serializer, used to serialize path content.
	 *
	 * @var Log_Item_Serializer
	 */
	private $items;

	/**
	 * Block modifier serializer.
	 *
	 * @var Log_Modifier_Serializer
	 */
	private $modifiers;

	/**
	 * Tree-field to FieldsOutput serializer.
	 *
	 * @var \Uncanny_Automator\App\Recipe_Builder\Recipe\Wire_Serializer\Field_Output_Serializer
	 */
	private $fields;

	/**
	 * Block definition registry, used to resolve block-field labels.
	 *
	 * @var Block_Registry
	 */
	private $block_registry;

	/**
	 * Existed-at-run-start visibility resolver.
	 *
	 * @var Node_Run_Visibility
	 */
	private $visibility;

	/**
	 * What a user may do to a block of this run; Pro registers the controls, Free registers none.
	 *
	 * @var Block_Control_Registry
	 */
	private $controls;

	/**
	 * The wire text of a block's run-level errors, which every action under the block shows.
	 *
	 * @var Log_Block_Error_Text
	 */
	private $error_text;

	/**
	 * Wires up the block serializer's collaborators, defaulting each to its production implementation.
	 *
	 * @param Node_Status_Resolver|null   $resolver  Status derivation.
	 * @param Log_Item_Serializer|null     $items     Item serializer for path content.
	 * @param Log_Modifier_Serializer|null $modifiers Modifier serializer.
	 * @param \Uncanny_Automator\App\Recipe_Builder\Recipe\Wire_Serializer\Field_Output_Serializer|null $fields FieldsOutput serializer.
	 * @param Block_Registry|null $block_registry Block definition registry.
	 * @param Node_Run_Visibility|null $visibility Visibility resolver.
	 * @param Block_Control_Registry|null $controls Block-control registry.
	 * @param Log_Block_Error_Text|null $error_text Block-error text, defaulting to one on the same block registry.
	 */
	public function __construct(
		?Node_Status_Resolver $resolver = null,
		?Log_Item_Serializer $items = null,
		?Log_Modifier_Serializer $modifiers = null,
		$fields = null,
		?Block_Registry $block_registry = null,
		?Node_Run_Visibility $visibility = null,
		?Block_Control_Registry $controls = null,
		?Log_Block_Error_Text $error_text = null
	) {
		$this->resolver       = $resolver ?? new Node_Status_Resolver();
		$this->items          = $items ?? new Log_Item_Serializer();
		$this->modifiers      = $modifiers ?? new Log_Modifier_Serializer();
		$this->fields         = $fields ?? new \Uncanny_Automator\App\Recipe_Builder\Recipe\Wire_Serializer\Field_Output_Serializer();
		$this->block_registry = $block_registry ?? new WP_Block_Registry();
		$this->visibility     = $visibility ?? new Node_Run_Visibility();
		$this->controls       = $controls ?? ( new Automator_Recipe_Runner_Bridge() )->block_controls();
		$this->error_text     = $error_text ?? new Log_Block_Error_Text( $this->block_registry );
	}

	/**
	 * Serialize a block tree node (loop / filter / delay) with its run data.
	 *
	 * @param array<string,mixed> $block  Tree block entry.
	 * @param Run_Data_Bundle     $bundle Run data.
	 * @param array<string,mixed> $ctx    Inherited item ctx (inside_loop, in_untaken_path, block_errors).
	 * @return array<string,mixed> The wire ModelRecipeLogBlock entry.
	 */
	public function serialize( array $block, Run_Data_Bundle $bundle, array $ctx ): array {
		$block_type = (string) ( $block['block_type'] ?? '' );
		$block_id   = (int) ( $block['id'] ?? 0 );
		$is_loop    = Block_Type::LOOP === $block_type;

		// A run that recorded its configuration lists the modifiers that ran and ships the executed fields
		// on execution.fields, the FE's per-execution slot (present: the run's view; absent: block.fields).
		$snapshot  = $bundle->block_run_config( $block_id );
		$modifiers = is_array( $snapshot['modifiers'] ?? null ) ? $snapshot['modifiers'] : (array) ( $block['modifiers'] ?? array() );

		$loop_entry  = $is_loop ? $bundle->loop_entry( $block_id ) : null;
		$loop_ran    = null !== $loop_entry;
		$chosen_path = Block_Type::FILTER === $block_type && empty( $ctx['inside_loop'] ) ? $this->resolve_filter_chosen_path( $block, $block_id, $bundle ) : null;

		$execution = $this->execution( $block, $block_id, $bundle, $block_type, $loop_entry, $chosen_path, $ctx );
		// A block on a path the run did not take never ran, so it has no dates.
		if ( ! empty( $ctx['in_untaken_path'] ) ) {
			unset( $execution['date_started'], $execution['date_ended'], $execution['date_elapsed'] );
		}
		if ( is_array( $snapshot['fields'] ?? null ) && ! empty( $snapshot['fields'] ) ) {
			$execution['fields'] = $this->fields->serialize( $this->label_block_fields( $snapshot['fields'], $block_type ) );
		}

		// Blocks in a loop body have no rows of their own to act on, so only a top-level block is offered a control.
		$control = empty( $ctx['inside_loop'] ) ? $this->controls->control_for( $block_type, $block, $bundle ) : null;
		if ( null !== $control ) {
			$execution['control'] = $control;
		}

		return array(
			Wire_Key::TYPE       => Wire_Node_Type::BLOCK,
			Wire_Key::BLOCK_TYPE => $block_type,
			Wire_Key::ID         => $block_id,
			Wire_Key::FIELDS     => $this->fields->serialize( $this->label_block_fields( is_array( $snapshot['fields'] ?? null ) ? $snapshot['fields'] : ( is_array( $block['fields'] ?? null ) ? $block['fields'] : array() ), $block_type ) ),
			Wire_Key::MODIFIERS  => $this->serialize_modifiers( $modifiers, $block_id, $bundle, $is_loop, $loop_ran, $chosen_path, ! empty( $ctx['inside_loop'] ) ? $this->path_entity_counts( $block, $bundle ) : null ),
			Wire_Key::PATHS      => $this->serialize_paths( $block, $bundle, $ctx, $block_type, $chosen_path ),
			'execution'          => $execution,
		);
	}

	/**
	 * Stamp block-field labels (and input types) from the block definition onto
	 * the stored fields so Field_Output_Serializer emits a labelled Properties
	 * row. Stored block fields are flat scalars ({code: value}); this normalizes
	 * each into the FieldsOutput input shape and fills miscellaneous.label from
	 * the block registry when the stored value carries none, and sets
	 * miscellaneous.show_label_in_sentence to false where the definition hides
	 * the label.
	 *
	 * @param array<string,mixed> $fields     Stored block fields.
	 * @param string              $block_type Block type.
	 * @return array<string,mixed> Fields enriched with labels/types.
	 */
	private function label_block_fields( array $fields, string $block_type ): array {
		if ( empty( $fields ) ) {
			return array();
		}
		$defs = $this->block_field_definitions( $block_type );
		$out  = array();
		foreach ( $fields as $code => $value ) {
			$entry = is_array( $value ) ? $value : array( 'value' => $value );
			$def   = $defs[ (string) $code ] ?? array();
			$misc  = ( isset( $entry['miscellaneous'] ) && is_array( $entry['miscellaneous'] ) ) ? $entry['miscellaneous'] : array();
			if ( empty( $misc['label'] ) && '' !== (string) ( $def['label'] ?? '' ) ) {
				$misc['label'] = (string) $def['label'];
			}
			if ( false === ( $def['show_label_in_sentence'] ?? true ) ) {
				$misc['show_label_in_sentence'] = false;
			}
			$entry['miscellaneous'] = $misc;
			if ( empty( $entry['type'] ) && '' !== (string) ( $def['type'] ?? '' ) ) {
				$entry['type'] = (string) $def['type'];
			}
			$out[ (string) $code ] = $entry;
		}
		return $out;
	}

	/**
	 * option_code => {label, type, show_label_in_sentence} map from the block definition, or empty when
	 * the block type is not registered.
	 *
	 * @param string $block_type Block type.
	 * @return array<string,array{label:string,type:string,show_label_in_sentence:bool}>
	 */
	private function block_field_definitions( string $block_type ): array {
		$definition = $this->block_registry->get_block_definition( $block_type );
		if ( null === $definition ) {
			return array();
		}
		$map = array();
		foreach ( $definition->get_fields() as $field ) {
			$field = (array) $field;
			$code  = (string) ( $field['option_code'] ?? '' );
			if ( '' !== $code ) {
				$map[ $code ] = array(
					'label'                  => (string) ( $field['label'] ?? '' ),
					'type'                   => (string) ( $field['input_type'] ?? ( $field['type'] ?? '' ) ),
					'show_label_in_sentence' => false !== ( $field['show_label_in_sentence'] ?? true ),
				);
			}
		}
		return $map;
	}

	/**
	 * Serialize the block's modifiers[] with the shared modifier context flags.
	 *
	 * @param array<int,mixed>    $modifiers The modifier list the run executed with, or the block's current one.
	 * @param int                 $block_id Block id.
	 * @param Run_Data_Bundle     $bundle  Run data.
	 * @param bool                $is_loop Loop block?
	 * @param bool                $loop_ran Loop produced an entry?
	 * @param string|null         $chosen_path Resolved filter chosen path, or null.
	 * @param array<string,int>|null $path_entities Entity counts per path when the block sits in a loop body, else null.
	 * @return array<int,array<string,mixed>> The wire modifier entries.
	 */
	private function serialize_modifiers( array $modifiers, int $block_id, Run_Data_Bundle $bundle, bool $is_loop, bool $loop_ran, ?string $chosen_path, ?array $path_entities = null ): array {
		$out     = array();
		$refusal = $bundle->block_refusal( $block_id );
		foreach ( $modifiers as $mod ) {
			// A filter/loop-filter added after this run started is omitted
			// (Node_Run_Visibility) — it played no part in the run.
			if ( $this->visibility->modifier_hidden( (array) $mod, $bundle ) ) {
				continue;
			}
			$out[] = $this->modifiers->serialize(
				(array) $mod,
				$block_id,
				$bundle,
				array(
					'is_loop_filter'   => $is_loop,
					'loop_ran'         => $loop_ran,
					'chosen_path'      => $chosen_path,
					'inside_loop'      => null !== $path_entities,
					'path_entities'    => $path_entities ?? array(),
					'refused'          => null !== $refusal,
					'refusal_messages' => null === $refusal ? array() : $this->refusal_messages( $refusal, ( (array) $mod )['id'] ?? null ),
				)
			);
		}
		return $out;
	}

	/**
	 * The messages of a refusal's violations that target one modifier. Ids compare as strings: filter
	 * conditions may carry string ids, and the record round-trips through JSON.
	 *
	 * @param array<string,mixed> $refusal     The block's refusal record.
	 * @param int|string|null     $modifier_id The modifier's id.
	 * @return string[]
	 */
	private function refusal_messages( array $refusal, $modifier_id ): array {
		if ( null === $modifier_id ) {
			return array();
		}

		$wanted   = (string) $modifier_id;
		$messages = array();
		foreach ( (array) ( $refusal['violations'] ?? array() ) as $violation ) {
			$target    = is_array( $violation ) ? (array) ( $violation['target'] ?? array() ) : array();
			$target_id = (string) ( $target['id'] ?? '' );
			if ( 'block_modifier' === ( $target['node_type'] ?? '' ) && $wanted === $target_id ) {
				$messages[] = (string) ( $violation['message'] ?? '' );
			}
		}

		return $messages;
	}

	/**
	 * Resolve a filter block's chosen path: the run's record when the runner kept one (a filter absent from it
	 * was never evaluated), otherwise inferred from execution — the path whose descendant actions ran was the
	 * taken path (runs older than the record).
	 *
	 * @param array<string,mixed> $block    Block entry.
	 * @param int                 $block_id Block id.
	 * @param Run_Data_Bundle     $bundle   Run data.
	 * @return string|null The path_code, or null when the filter was not evaluated.
	 */
	private function resolve_filter_chosen_path( array $block, int $block_id, Run_Data_Bundle $bundle ): ?string {
		if ( $bundle->chosen_paths_recorded() ) {
			return $bundle->chosen_path_for_block( $block_id );
		}

		return $this->infer_chosen_path_from_execution( $block, $bundle );
	}

	/**
	 * Infer the taken path for a run with no chosen-path meta (pre-8.0.0 runs): the first path with a
	 * descendant action row that executed. Rows born at run start, rows scheduled under a delay,
	 * queued rows and skipped rows say nothing about the choice, so a filter still waiting stays not-evaluated.
	 *
	 * @param array<string,mixed> $block  Block entry.
	 * @param Run_Data_Bundle     $bundle Run data.
	 * @return string|null The inferred path_code, or null.
	 */
	private function infer_chosen_path_from_execution( array $block, Run_Data_Bundle $bundle ): ?string {
		foreach ( (array) ( $block['paths'] ?? array() ) as $path ) {
			$path = (array) $path;
			foreach ( $this->collect_action_ids( array( $path ) ) as $action_id ) {
				$row = $bundle->action_row( $action_id );
				if ( is_array( $row ) && $this->row_executed( (int) ( $row['completed'] ?? Automator_Status::NOT_COMPLETED ) ) ) {
					return (string) ( $path['path_code'] ?? '' );
				}
			}
		}
		return null;
	}

	/**
	 * Whether an action-log status means the action ran on its path.
	 *
	 * @param int $status Automator_Status value.
	 * @return bool
	 */
	private function row_executed( int $status ): bool {
		return ! in_array( $status, array( Automator_Status::NOT_COMPLETED, Automator_Status::IN_PROGRESS, Automator_Status::QUEUED, Automator_Status::SKIPPED ), true );
	}

	/**
	 * Serialize the block's paths[], recursing into each path's content.
	 *
	 * Every node under the block inherits the block's run-level errors (`block_errors`, outermost block first), which
	 * each action under it shows as its result message.
	 *
	 * @param array<string,mixed> $block       Block entry.
	 * @param Run_Data_Bundle     $bundle      Run data.
	 * @param array<string,mixed> $ctx         Inherited ctx.
	 * @param string              $block_type  Block type.
	 * @param string|null         $chosen_path Filter chosen path, or null.
	 * @return array<int,array<string,mixed>> The wire path entries.
	 */
	private function serialize_paths( array $block, Run_Data_Bundle $bundle, array $ctx, string $block_type, ?string $chosen_path ): array {
		$source_paths = (array) ( $block['paths'] ?? array() );
		if ( empty( $source_paths ) ) {
			// A stale/legacy tree can arrive with no paths; mirror
			// Recipe_Tree_Builder::build_block_paths() and emit the catalog
			// defaults (empty content) so the wire never ships paths: [].
			$source_paths = array_map(
				static function ( $code ) {
					return array(
						'path_code' => $code,
						'items'     => array(),
					);
				},
				Block_Path_Code::for_block_type( $block_type )
			);
		}

		$block_errors = array_merge( (array) ( $ctx['block_errors'] ?? array() ), $this->error_text->for_block( (int) ( $block['id'] ?? 0 ), $bundle ) );

		$paths = array();
		foreach ( $source_paths as $path ) {
			$path      = (array) $path;
			$path_code = (string) ( $path['path_code'] ?? '' );
			$taken     = null === $chosen_path ? true : ( $path_code === $chosen_path );

			$child_ctx = array_merge(
				$ctx,
				array(
					'inside_loop'     => ! empty( $ctx['inside_loop'] ) || Block_Type::LOOP === $block_type,
					'in_untaken_path' => ! empty( $ctx['in_untaken_path'] ) || ( null !== $chosen_path && ! $taken ),
					'block_errors'    => $block_errors,
				)
			);

			$content = array();
			foreach ( (array) ( $path['items'] ?? array() ) as $child ) {
				$child = (array) $child;
				if ( 'block' === ( $child['kind'] ?? '' ) ) {
					// Nested nodes added after this run started are omitted
					// (Node_Run_Visibility), same as the top-level flow walk.
					if ( $this->visibility->block_hidden( $child, $bundle ) ) {
						continue;
					}
					$content[] = $this->serialize( $child, $bundle, $child_ctx );
					continue;
				}
				if ( $this->visibility->item_hidden( $child, $bundle ) ) {
					continue;
				}
				$content[] = $this->items->serialize( $child, $bundle, $child_ctx );
			}

			$entry = array(
				Wire_Key::PATH_CODE => $path_code,
				Wire_Key::CONTENT   => $content,
			);
			if ( Block_Type::FILTER === $block_type && null !== $chosen_path ) {
				$entry['execution'] = array( 'status' => $this->resolver->path_status( $taken )->to_array() );
			}
			$paths[] = $entry;
		}
		return $paths;
	}

	/**
	 * Build the block's execution sub-object, branching by block type.
	 *
	 * @param array<string,mixed>     $block      Block entry.
	 * @param int                     $block_id   Block id.
	 * @param Run_Data_Bundle         $bundle     Run data.
	 * @param string                  $block_type Block type.
	 * @param array<string,mixed>|null $loop_entry Loop-run row, or null.
	 * @param string|null             $chosen_path Resolved filter chosen path, or null.
	 * @param array<string,mixed>     $ctx         Inherited item ctx (inside_loop, in_untaken_path).
	 * @return array<string,mixed> The block's execution sub-object.
	 */
	private function execution( array $block, int $block_id, Run_Data_Bundle $bundle, string $block_type, ?array $loop_entry, ?string $chosen_path = null, array $ctx = array() ): array {
		if ( Block_Type::LOOP === $block_type ) {
			$exec    = array(
				'status'   => $this->resolver->loop_block( $block_id, $bundle )->to_array(),
				'progress' => array(
					'elements_total'   => (int) ( $loop_entry['num_entities'] ?? 0 ),
					'status_breakdown' => $this->breakdown_entries( $this->descendant_breakdown_totals( $block, $bundle ) ),
				),
			);
			$message = Log_Message_Text::from_stored( (string) ( $loop_entry['message'] ?? '' ) );
			if ( '' !== $message ) {
				$exec['progress']['message'] = $message;
			}
			$ended   = (string) ( $loop_entry['process_date_ended'] ?? '' );
			$started = null === $loop_entry ? $this->reached_at( $block_id, $bundle ) : ( $loop_entry['process_date_started'] ?? null );
			return $this->with_block_error( $this->with_dates( $exec, $started, '' === $ended ? null : $ended ), $block_id, $bundle );
		}

		// Inside a loop body there are no action-log rows; the entity items under the block are the record.
		if ( ! empty( $ctx['inside_loop'] ) ) {
			return $this->with_block_error(
				array(
					'status'       => $this->resolver->rollup_from_breakdown( $this->descendant_breakdown_totals( $block, $bundle ) )->to_array(),
					'date_started' => $this->fmt( (string) ( $bundle->recipe_log_row()['date_time'] ?? '' ) ),
				),
				$block_id,
				$bundle
			);
		}

		if ( $bundle->is_node_blocked( $block_id ) ) {
			return array(
				'status'       => Log_Status_Registry::from_code( Log_Status_Code::BLOCKED_ON_DEPENDENCY )->to_array(),
				'date_started' => $this->fmt( (string) ( $bundle->recipe_log_row()['date_time'] ?? '' ) ),
			);
		}

		if ( Block_Type::FILTER === $block_type ) {
			$status = null !== $chosen_path ? Log_Status_Registry::from_code( Log_Status_Code::COMPLETED )->to_array() : $this->rollup_block_status( $block, $bundle );
			return $this->with_block_error( $this->with_dates( array( 'status' => $status ), $this->reached_at( $block_id, $bundle ), $this->finished_at( $block, $bundle ) ), $block_id, $bundle );
		}

		// Delay (and future single-execution) blocks: a pending delayed/scheduled
		// descendant wins over the rollup, and the block starts when it runs: the
		// target time, the same date its waiting rows show.
		$pending = $this->earliest_pending_delay( $block, $bundle );
		if ( null !== $pending ) {
			return $this->with_block_error(
				array(
					'status'       => Log_Status_Registry::from_code( $pending['code'] )->to_array(),
					'date_started' => $this->fmt( (string) wp_date( 'Y-m-d H:i:s', $pending['time'] ) ),
				),
				$block_id,
				$bundle
			);
		}

		// Once the wait is over, a delay still starts at its scheduled time, or when an admin ran it early.
		$scheduled = $this->scheduled_start( $block, $bundle );
		$started   = null === $scheduled ? $this->reached_at( $block_id, $bundle ) : (string) wp_date( 'Y-m-d H:i:s', $scheduled );

		return $this->with_block_error( $this->with_dates( array( 'status' => $this->rollup_block_status( $block, $bundle ) ), $started, $this->finished_at( $block, $bundle ) ), $block_id, $bundle );
	}

	/**
	 * Show the block's own error: its message as the block's notes, and Completed with errors when the block did
	 * not run its actions (its rows read Skipped, so the rollup alone would call it finished work). In a loop body
	 * other entities may still be at work under the block; while its rollup is active it reads In progress with errors.
	 *
	 * @since 8.0.0
	 *
	 * @param array<string,mixed> $exec     The execution so far.
	 * @param int                 $block_id Block id.
	 * @param Run_Data_Bundle     $bundle   Run data.
	 * @return array<string,mixed> The execution with the block's error, if it has one.
	 */
	private function with_block_error( array $exec, int $block_id, Run_Data_Bundle $bundle ): array {
		$error = $bundle->block_error( $block_id );
		if ( null === $error ) {
			return $exec;
		}

		$exec['result_message'] = Log_Message_Text::from_stored( $error['message'] );
		foreach ( $bundle->block_errors( $block_id ) as $row ) {
			if ( 'block' !== $row['item_type'] || $error['message'] !== $row['message']
				|| ( $error['not_run'] && Error_Code::BLOCK_NOT_RUN !== $row['code'] ) ) {
				continue;
			}
			$exec['result_message'] = $this->error_text->from_error( $row );
			break;
		}
		if ( $error['not_run'] ) {
			$active         = Log_Status_Phase::ACTIVE === Log_Status_Registry::from_code( (string) ( $exec['status']['code'] ?? '' ) )->get_phase();
			$exec['status'] = Log_Status_Registry::from_code( $active ? Log_Status_Code::IN_PROGRESS_WITH_ERRORS : Log_Status_Code::COMPLETED_WITH_ERRORS )->to_array();
		}

		return $exec;
	}

	/**
	 * Add date_started, date_ended and date_elapsed to an execution for whichever dates the block has.
	 *
	 * @param array<string,mixed> $exec    The execution so far.
	 * @param string|null         $started Raw MySQL start, or null when the block has not started.
	 * @param string|null         $ended   Raw MySQL end, or null when the block has not finished.
	 * @return array<string,mixed> The execution with its dates.
	 */
	private function with_dates( array $exec, ?string $started, ?string $ended ): array {
		if ( null === $started || '' === $started ) {
			if ( null !== $ended ) {
				$exec['date_ended'] = $this->fmt( $ended );
			}
			return $exec;
		}
		$exec['date_started'] = $this->fmt( $started );
		if ( null !== $ended ) {
			$exec['date_ended'] = $this->fmt( $ended );
			$elapsed            = $this->elapsed( $started, $ended );
			if ( null !== $elapsed ) {
				$exec['date_elapsed'] = $elapsed;
			}
		}
		return $exec;
	}

	/**
	 * When the run reached the block, from the run's block configuration record.
	 *
	 * @param int             $block_id Block id.
	 * @param Run_Data_Bundle $bundle   Run data.
	 * @return string|null Raw MySQL datetime, or null when the run never reached the block.
	 */
	private function reached_at( int $block_id, Run_Data_Bundle $bundle ): ?string {
		$run_start = (string) ( $bundle->recipe_log_row()['date_time'] ?? '' );
		$config    = $bundle->block_run_config( $block_id );
		if ( null !== $config ) {
			return (string) ( $config['started_at'] ?? $run_start );
		}

		// A run that records the blocks it reached and not this one never reached it; a run from before the record starts every block at its own start.
		return $bundle->has_block_run_config_record() ? null : $run_start;
	}

	/**
	 * When the block's last step finished, once every step under it has.
	 *
	 * @param array<string,mixed> $block  Block entry.
	 * @param Run_Data_Bundle     $bundle Run data.
	 * @return string|null Raw MySQL datetime, or null while a step is unfinished or there are none.
	 */
	private function finished_at( array $block, Run_Data_Bundle $bundle ): ?string {
		$latest = null;
		foreach ( $this->collect_action_ids( $block['paths'] ?? array() ) as $action_id ) {
			$row = $bundle->action_row( $action_id );
			if ( ! is_array( $row ) ) {
				continue;
			}
			if ( ! Automator_Status::finished( (int) ( $row['completed'] ?? Automator_Status::NOT_COMPLETED ) ) ) {
				return null;
			}
			$date   = (string) ( $row['date_time'] ?? '' );
			$latest = null === $latest || $date > $latest ? $date : $latest;
		}
		return $latest;
	}

	/**
	 * When the block's delayed steps were due, or ran when an admin ran them early, from the run's delay record.
	 *
	 * @param array<string,mixed> $block  Block entry.
	 * @param Run_Data_Bundle     $bundle Run data.
	 * @return int|null Unix timestamp, or null when no step under the block was delayed.
	 */
	private function scheduled_start( array $block, Run_Data_Bundle $bundle ): ?int {
		$earliest = null;
		foreach ( $this->collect_action_ids( $block['paths'] ?? array() ) as $action_id ) {
			$config = $bundle->delay_config_for_action( $action_id );
			$time   = $config['ran_now_at'] ?? $config['time'] ?? null;
			if ( is_numeric( $time ) && ( null === $earliest || (int) $time < $earliest ) ) {
				$earliest = (int) $time;
			}
		}
		return $earliest;
	}

	/**
	 * When the run scheduled the block's delayed steps for, from the run's delay record.
	 *
	 * The time they were due, never when an admin ran them early: this orders the
	 * run's deferred blocks, and running one early does not move it.
	 *
	 * @since 8.0.0
	 *
	 * @param array<string,mixed> $block  Tree block entry.
	 * @param Run_Data_Bundle     $bundle Run data.
	 * @return int|null Unix timestamp, or null when no step under the block was scheduled.
	 */
	public function scheduled_for( array $block, Run_Data_Bundle $bundle ): ?int {
		$earliest = null;
		foreach ( $this->collect_action_ids( $block['paths'] ?? array() ) as $action_id ) {
			$time = $bundle->delay_config_for_action( $action_id )['time'] ?? null;
			if ( is_numeric( $time ) && ( null === $earliest || (int) $time < $earliest ) ) {
				$earliest = (int) $time;
			}
		}
		return $earliest;
	}

	/**
	 * Earliest pending delay/schedule among the block's descendant actions.
	 *
	 * @param array<string,mixed> $block  Block entry.
	 * @param Run_Data_Bundle     $bundle Run data.
	 * @return array{code: string, time: int}|null The earliest pending descriptor, or null when none pending.
	 */
	private function earliest_pending_delay( array $block, Run_Data_Bundle $bundle ): ?array {
		$earliest = null;
		foreach ( $this->collect_action_ids( $block['paths'] ?? array() ) as $action_id ) {
			$pending = $this->resolver->pending_delay( $action_id, $bundle );
			if ( null !== $pending && ( null === $earliest || $pending['time'] < $earliest['time'] ) ) {
				$earliest = $pending;
			}
		}
		return $earliest;
	}

	/**
	 * Elapsed sub-object from two raw MySQL datetimes, or null when
	 * unparsable. Legal on item/block executions ONLY — never the root.
	 *
	 * @param string $start Start datetime.
	 * @param string $end   End datetime.
	 * @return array{seconds: int, human_readable: string}|null The elapsed sub-object, or null when unparsable.
	 */
	private function elapsed( string $start, string $end ): ?array {
		$start_ts = strtotime( $start );
		$end_ts   = strtotime( $end );
		if ( false === $start_ts || false === $end_ts || $end_ts < $start_ts ) {
			return null;
		}
		return array(
			'seconds'        => $end_ts - $start_ts,
			'human_readable' => human_time_diff( $start_ts, $end_ts ),
		);
	}

	/**
	 * Roll a single-lane block's status up from what its descendant rows did: any failure to failed; any
	 * completed row to completed; otherwise every row cancelled to cancelled, every row skipped to skipped,
	 * and a block whose rows only exist (run-start rows, still scheduled) to not-completed.
	 *
	 * @param array<string,mixed> $block  Block entry.
	 * @param Run_Data_Bundle     $bundle Run data.
	 * @return array<string,string> The rolled-up wire status object.
	 */
	private function rollup_block_status( array $block, Run_Data_Bundle $bundle ): array {
		$statuses = array();
		foreach ( $this->collect_action_ids( $block['paths'] ?? array() ) as $action_id ) {
			$row = $bundle->action_row( $action_id );
			if ( is_array( $row ) ) {
				$statuses[] = (int) ( $row['completed'] ?? Automator_Status::NOT_COMPLETED );
			}
		}

		$completed = array( Automator_Status::COMPLETED, Automator_Status::COMPLETED_WITH_ERRORS, Automator_Status::COMPLETED_WITH_NOTICE, Automator_Status::DID_NOTHING, Automator_Status::COMPLETED_AWAITING );

		if ( in_array( Automator_Status::FAILED, $statuses, true ) ) {
			$code = Log_Status_Code::FAILED;
		} elseif ( array_intersect( $statuses, $completed ) ) {
			$code = Log_Status_Code::COMPLETED;
		} elseif ( in_array( Automator_Status::CANCELLED, $statuses, true ) && array() === array_diff( $statuses, array( Automator_Status::CANCELLED, Automator_Status::SKIPPED ) ) ) {
			// Skipped rows are the ordinary product of a chosen path; once every row is terminal, a cancellation among them is the outcome to show.
			$code = Log_Status_Code::CANCELLED;
		} elseif ( $statuses && array( Automator_Status::SKIPPED ) === array_values( array_unique( $statuses ) ) ) {
			$code = Log_Status_Code::SKIPPED;
		} else {
			$code = Log_Status_Code::NOT_COMPLETED;
		}

		return Log_Status_Registry::from_code( $code )->to_array();
	}

	/**
	 * Sum per-action loop breakdowns across every action under the block into one status => count map.
	 *
	 * @param array<string,mixed> $block  Block entry.
	 * @param Run_Data_Bundle     $bundle Run data.
	 * @return array<string,int> status => count.
	 */
	private function descendant_breakdown_totals( array $block, Run_Data_Bundle $bundle ): array {
		$totals = array();
		foreach ( $this->collect_action_ids( $block['paths'] ?? array() ) as $action_id ) {
			foreach ( $bundle->loop_breakdown_for_action( $action_id ) as $code => $count ) {
				$totals[ $code ] = ( $totals[ $code ] ?? 0 ) + (int) $count;
			}
		}

		return $totals;
	}

	/**
	 * Project a status => count map to the wire breakdown list.
	 *
	 * @param array<string,int> $totals status => count.
	 * @return array<int,array<string,mixed>> The breakdown entries.
	 */
	private function breakdown_entries( array $totals ): array {
		$out = array();
		foreach ( $totals as $code => $count ) {
			$out[] = array(
				'status' => Log_Status_Registry::from_code( (string) $code )->to_array(),
				'count'  => (int) $count,
			);
		}

		return $out;
	}

	/**
	 * Entities that took each path of a block inside a loop body: the non-skipped items of the path's first action.
	 *
	 * @param array<string,mixed> $block  Block entry.
	 * @param Run_Data_Bundle     $bundle Run data.
	 * @return array<string,int> path_code => entity count.
	 */
	private function path_entity_counts( array $block, Run_Data_Bundle $bundle ): array {
		$out = array();
		foreach ( (array) ( $block['paths'] ?? array() ) as $path ) {
			$path      = (array) $path;
			$action_id = $this->collect_action_ids( array( $path ) )[0] ?? 0;
			$count     = 0;
			foreach ( $bundle->loop_breakdown_for_action( (int) $action_id ) as $code => $n ) {
				if ( Log_Status_Code::SKIPPED !== (string) $code ) {
					$count += (int) $n;
				}
			}
			$out[ (string) ( $path['path_code'] ?? '' ) ] = $count;
		}

		return $out;
	}

	/**
	 * Recursively collects every action id referenced under a paths[] structure.
	 *
	 * Public so sibling serializers (e.g. Log_Flow_Serializer's orphan diff) reuse
	 * the one walk rather than re-implementing it. This is the seam the planned
	 * shared recipe-tree walker (see the stream-2 follow-ups) will eventually own.
	 *
	 * @param array<int,mixed> $paths The block's paths list (each entry an array with an `items` bucket).
	 * @return array<int,int> Flat list of descendant action post ids, children without an `id` skipped.
	 */
	public function collect_action_ids( array $paths ): array {
		$ids = array();
		foreach ( $paths as $path ) {
			foreach ( (array) ( ( (array) $path )['items'] ?? array() ) as $child ) {
				$child = (array) $child;
				if ( 'block' === ( $child['kind'] ?? '' ) ) {
					$ids = array_merge( $ids, $this->collect_action_ids( $child['paths'] ?? array() ) );
				} elseif ( isset( $child['id'] ) ) {
					$ids[] = (int) $child['id'];
				}
			}
		}
		return $ids;
	}

	/**
	 * Recursively collects every block id nested under a paths[] structure.
	 *
	 * Sibling of collect_action_ids: Log_Flow_Serializer folds these into the
	 * known-tree-id set so a block id the runner logged as its own action_log
	 * row is never mistaken for an orphaned action.
	 *
	 * @param array<int,mixed> $paths The block's paths list.
	 * @return array<int,int> Flat list of descendant block post ids.
	 */
	public function collect_block_ids( array $paths ): array {
		$ids = array();
		foreach ( $paths as $path ) {
			foreach ( (array) ( ( (array) $path )['items'] ?? array() ) as $child ) {
				$child = (array) $child;
				if ( 'block' === ( $child['kind'] ?? '' ) ) {
					$ids[] = (int) ( $child['id'] ?? 0 );
					$ids   = array_merge( $ids, $this->collect_block_ids( $child['paths'] ?? array() ) );
				}
			}
		}
		return $ids;
	}

	/**
	 * Format a MySQL datetime to the display string. Empty in → empty out.
	 *
	 * @param string $mysql_datetime Raw datetime.
	 * @return string The formatted display string, or empty when the input is empty.
	 */
	private function fmt( string $mysql_datetime ): string {
		return '' === $mysql_datetime ? '' : (string) Formatters_Utils::date_time_format_relative( $mysql_datetime );
	}
}
