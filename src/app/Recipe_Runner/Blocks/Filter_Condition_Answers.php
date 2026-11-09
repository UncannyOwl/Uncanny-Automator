<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Recipe_Runner\Blocks;

use Uncanny_Automator\App\Recipe_Builder\Block\Enums\Block_Type;
use Uncanny_Automator\App\Recipe_Runner\Value_Objects\Execution_Context;

/**
 * Notes, in memory, what each condition of a filter block answered while its processor evaluated it, so the
 * children runner can keep the answers with the path the filter chose.
 *
 * Temporary. It stands in for the per-condition results Pro 8.0.0 and 8.0.1 don't pass to
 * Block_Children_Runner::run(). Delete it, with Action_Run_Stage's open() / close() around process() and the
 * children runner's $answers, once Free requires a Pro that passes them.
 *
 * Those Pro versions ask each condition through `automator_pro_evaluate_actions_conditions`, once per entry of the
 * block's `modifiers` in order, and stop at the first decisive answer, without naming the modifier. So an answer
 * goes to the first modifier not yet answered whose integration and code match the condition asked. The callback
 * runs last on the hook, only while a filter is open, and hands the envelope back unchanged.
 *
 * @since 8.0.3
 */
final class Filter_Condition_Answers {

	/**
	 * The hook Pro asks each condition through.
	 *
	 * @var string
	 */
	public const EVALUATE_HOOK = 'automator_pro_evaluate_actions_conditions';

	/**
	 * Open filters by block id: their modifiers in order, and the answers noted so far (modifier id => met).
	 *
	 * @var array<int, array{modifiers: array<int, array<string, mixed>>, answers: array<string, bool>}>
	 */
	private array $scopes = array();

	/**
	 * Start noting answers for a filter about to be handed to its processor. Does nothing for another block type,
	 * in loop scope (each entity evaluates the filter again), or for a filter with no conditions.
	 *
	 * @param array<string, mixed> $block_node Block node.
	 * @param Execution_Context    $context    The run.
	 *
	 * @return void
	 */
	public function open( array $block_node, Execution_Context $context ): void {
		$modifiers = (array) ( $block_node['modifiers'] ?? array() );
		if ( Block_Type::FILTER !== (string) ( $block_node['block_type'] ?? '' ) || $context->is_loop_scoped() || empty( $modifiers ) ) {
			return;
		}

		if ( empty( $this->scopes ) ) {
			add_filter( self::EVALUATE_HOOK, array( $this, 'observe' ), PHP_INT_MAX, 2 );
		}

		$this->scopes[ (int) ( $block_node['id'] ?? 0 ) ] = array(
			'modifiers' => array_values( $modifiers ),
			'answers'   => array(),
		);
	}

	/**
	 * The answers noted for an open filter: modifier id => met. A condition the filter decided without is absent.
	 *
	 * @param int $block_id Block id.
	 *
	 * @return array<string, bool>|null Null when the filter is not open.
	 */
	public function answers_for( int $block_id ): ?array {
		return isset( $this->scopes[ $block_id ] ) ? $this->scopes[ $block_id ]['answers'] : null;
	}

	/**
	 * Stop noting answers for a filter. The hook comes off once no filter is open.
	 *
	 * @param int $block_id Block id.
	 *
	 * @return void
	 */
	public function close( int $block_id ): void {
		if ( ! isset( $this->scopes[ $block_id ] ) ) {
			return;
		}

		unset( $this->scopes[ $block_id ] );

		if ( empty( $this->scopes ) ) {
			remove_filter( self::EVALUATE_HOOK, array( $this, 'observe' ), PHP_INT_MAX );
		}
	}

	/**
	 * Note the answer one condition gave. Hooked last on the evaluation hook while a filter is open; reads arrays
	 * only, so nothing here can throw into Pro's evaluation and change the filter's decision.
	 *
	 * @param mixed $envelope  The evaluation envelope, as the condition classes left it.
	 * @param mixed $condition The condition asked: `{ integration, condition, fields }`.
	 *
	 * @return mixed The envelope, unchanged.
	 */
	public function observe( $envelope, $condition = array() ) {
		$asked       = is_array( $envelope ) ? $envelope : array();
		$condition   = is_array( $condition ) ? $condition : array();
		$action_data = is_array( $asked['action_data'] ?? null ) ? $asked['action_data'] : array();
		$block_id    = (int) ( $action_data['ID'] ?? 0 );

		if ( ! isset( $this->scopes[ $block_id ] ) ) {
			return $envelope;
		}

		foreach ( $this->scopes[ $block_id ]['modifiers'] as $modifier ) {
			$modifier_id = (string) ( $modifier['id'] ?? '' );
			if ( '' === $modifier_id || array_key_exists( $modifier_id, $this->scopes[ $block_id ]['answers'] ) ) {
				continue;
			}
			if ( (string) ( $modifier['integration_code'] ?? '' ) !== (string) ( $condition['integration'] ?? '' ) || (string) ( $modifier['code'] ?? '' ) !== (string) ( $condition['condition'] ?? '' ) ) {
				continue;
			}

			$this->scopes[ $block_id ]['answers'][ $modifier_id ] = isset( $asked['process_further'] ) && false !== $asked['process_further'];
			break;
		}

		return $envelope;
	}
}
