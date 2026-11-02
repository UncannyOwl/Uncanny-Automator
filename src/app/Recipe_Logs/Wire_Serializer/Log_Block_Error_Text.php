<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Recipe_Logs\Wire_Serializer;

use Uncanny_Automator\App\Plan\Domain\Plan_Levels;
use Uncanny_Automator\App\Recipe_Builder\Block\Registry\Block_Registry;
use Uncanny_Automator\App\Recipe_Builder\Block\Registry\WP_Block_Registry;
use Uncanny_Automator\App\Recipe_Runner\Services\Error_Code;

/**
 * The wire text of the run-level errors stored against a block, which every action under that block shows as its
 * result message.
 *
 * A block whose type had no processor is the walker's error from `Action_Run_Stage::process_block()`: a system row
 * whose context names both its `block_type` and its `block_id`, or a block row with the `BLOCK_NOT_RUN` code whose
 * context names both and records no exception (a processor that threw records its exception). Pro 8.0 registers
 * every processor, so for a type the block registry says needs a Pro plan it reads "This block did not run because
 * Uncanny Automator Pro is not active.", translated when the log is read, whoever stored the row. When the row records
 * that Pro was active (`pro_active`: Pro 7.x loads on an 8.0 Free but registers no processor), it reads "Not run: the
 * Filter block needs Uncanny Automator Pro 8.0 or later.", with the block's name from the registry. Every other block
 * error reads as stored.
 *
 * @since 8.0.0
 */
final class Log_Block_Error_Text {

	/**
	 * Block definitions: each type's name and the plan it needs.
	 *
	 * @var Block_Registry
	 */
	private $registry;

	/**
	 * Wires the block registry, defaulting to the production one.
	 *
	 * @param Block_Registry|null $registry Block definition registry.
	 */
	public function __construct( ?Block_Registry $registry = null ) {
		$this->registry = $registry ?? new WP_Block_Registry();
	}

	/**
	 * The wire text of each run-level error stored against the block on this run, in the order stored, each text once.
	 *
	 * @param int             $block_id Block post id.
	 * @param Run_Data_Bundle $bundle   Run data.
	 * @return string[] Plain text, empty when the block stored no error.
	 */
	public function for_block( int $block_id, Run_Data_Bundle $bundle ): array {
		$texts = array();
		foreach ( $bundle->block_errors( $block_id ) as $error ) {
			$text = $this->from_error( $error );
			if ( '' !== $text && ! in_array( $text, $texts, true ) ) {
				$texts[] = $text;
			}
		}

		return $texts;
	}

	/**
	 * One stored block error as wire text.
	 *
	 * @param array{item_type: string, code: string, message: string, context: array<string,mixed>} $error The stored error.
	 * @return string Plain text.
	 */
	public function from_error( array $error ): string {
		$block_name = $this->pro_block_without_processor( $error );
		if ( null === $block_name ) {
			return Log_Message_Text::from_stored( $error['message'] );
		}

		if ( true === ( $error['context']['pro_active'] ?? false ) ) {
			return sprintf(
				/* translators: %s: the block's name, such as Filter */
				_x( 'Not run: the %s block needs Uncanny Automator Pro 8.0 or later.', 'Recipe log', 'uncanny-automator' ), // phpcs:ignore Uncanny_Automator.Strings.AutoContextTranslation.MissingContext -- Plain text sent as JSON; escaped where it is rendered.
				$block_name
			);
		}

		return _x( 'This block did not run because Uncanny Automator Pro is not active.', 'Recipe run log', 'uncanny-automator' ); // phpcs:ignore Uncanny_Automator.Strings.AutoContextTranslation.MissingContext -- Plain text sent as JSON; escaped where it is rendered.
	}

	/**
	 * The block's name for a missing-processor error that needs the Pro explanation; null for other errors.
	 *
	 * @param array{item_type: string, code: string, message: string, context: array<string,mixed>} $error The stored error.
	 * @return string|null The block's name as plain text, or null.
	 */
	private function pro_block_without_processor( array $error ): ?string {
		$context = $error['context'];
		if ( ! $this->is_missing_processor_error( $error ) || ! isset( $context['block_type'], $context['block_id'] ) ) {
			return null;
		}

		$block = $this->registry->get_block_definition( (string) $context['block_type'] );
		if ( null === $block || Plan_Levels::LITE === $block->get_required_tier() ) {
			return null;
		}

		// The registry escapes its names for HTML; the wire is plain text.
		return Log_Message_Text::from_stored( $block->get_name() );
	}

	/**
	 * Whether a stored error has the code the walker gives a block with no processor: `SYSTEM_ERROR` on a system row,
	 * or `BLOCK_NOT_RUN` on a block row that records no exception (a processor that threw records its exception).
	 *
	 * @since 8.0.0
	 *
	 * @param array{item_type: string, code: string, message: string, context: array<string,mixed>} $error The stored error.
	 * @return bool
	 */
	private function is_missing_processor_error( array $error ): bool {
		$item_type = $error['item_type'] ?? 'system';
		if ( 'system' === $item_type ) {
			return Error_Code::SYSTEM_ERROR === $error['code'];
		}

		return 'block' === $item_type && Error_Code::BLOCK_NOT_RUN === $error['code'] && ! isset( $error['context']['exception'] );
	}
}
