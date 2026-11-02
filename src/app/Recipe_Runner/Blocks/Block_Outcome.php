<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Recipe_Runner\Blocks;

use Uncanny_Automator\App\Recipe_Runner\Services\Error_Code;
use Uncanny_Automator\App\Recipe_Runner\Value_Objects\Action_Error;

/**
 * What a block processor reports back to the walker.
 *
 * Completed means the block's work for this walk is done. Deferred means the processor owns
 * completion later (a scheduled resume, a background loop) and the run must not finalize yet.
 * Either may carry one error the walker stores on the block. Not run means the block ran none
 * of its actions; it always carries the reason, and the walker skips every run row under the block.
 * In a loop body there are no run rows: the processor skips the entity's items itself before it
 * returns not run.
 */
final class Block_Outcome {

	/**
	 * Whether completion happens later, outside this walk.
	 *
	 * @var bool
	 */
	private bool $deferred;

	/**
	 * Whether the block ran none of its actions.
	 *
	 * @var bool
	 */
	private bool $not_run;

	/**
	 * Error to store on the block, if any.
	 *
	 * @var Action_Error|null
	 */
	private ?Action_Error $error;

	/**
	 * Use the named constructors.
	 *
	 * @param bool              $deferred Whether completion happens later.
	 * @param bool              $not_run  Whether the block ran none of its actions.
	 * @param Action_Error|null $error    Error to store on the block.
	 */
	private function __construct( bool $deferred, bool $not_run, ?Action_Error $error ) {
		$this->deferred = $deferred;
		$this->not_run  = $not_run;
		$this->error    = $error;
	}

	/**
	 * The block finished during this walk.
	 *
	 * @param Action_Error|null $error Error to store on the block, if any.
	 *
	 * @return self
	 */
	public static function completed( ?Action_Error $error = null ): self {
		return new self( false, false, $error );
	}

	/**
	 * The processor owns completion later; the run stays open.
	 *
	 * @param Action_Error|null $error Error to store on the block, if any.
	 *
	 * @return self
	 */
	public static function deferred( ?Action_Error $error = null ): self {
		return new self( true, false, $error );
	}

	/**
	 * The block ran none of its actions, for the reason given. In a loop body, skip the entity's items under the
	 * block first (Pro's `Entry_Point::skip_entry_item()`); the walker only records the reason there.
	 *
	 * @since 8.0.0
	 *
	 * @param string $reason  User-facing reason, stored escaped.
	 * @param array  $context Error context, such as the block and recipe IDs.
	 *
	 * @return self
	 */
	public static function not_run( string $reason, array $context = array() ): self {
		return new self( false, true, new Action_Error( Error_Code::BLOCK_NOT_RUN, $reason, $context ) );
	}

	/**
	 * Whether the run must wait for the processor's own completion.
	 *
	 * @return bool
	 */
	public function is_deferred(): bool {
		return $this->deferred;
	}

	/**
	 * Whether the block ran none of its actions.
	 *
	 * @since 8.0.0
	 *
	 * @return bool
	 */
	public function is_not_run(): bool {
		return $this->not_run;
	}

	/**
	 * The error to store on the block, or null.
	 *
	 * @return Action_Error|null
	 */
	public function error(): ?Action_Error {
		return $this->error;
	}
}
