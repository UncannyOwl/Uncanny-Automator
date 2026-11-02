<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Recipe_Runner\Services;

/**
 * Compatibility shim for Pro 7.x. Carries no contract.
 *
 * The loop is a block now: Pro registers a `Loop_Block_Processor` through
 * `Recipe_Runner::register_block_processor()` and nothing in Free calls this.
 *
 * It stays declared because Pro 7.x's `Pro_Loop_Processor` says
 * `implements Loop_Processor`, and Pro 7.x constructs that class at boot. Free
 * auto-updates from wp.org while Pro waits on a licence check, so a site can sit
 * on Free 8.0.0 with Pro 7.x for as long as the user takes to update. Without
 * this declaration autoloading the Pro class throws "Interface not found" on
 * every request, wp-admin included, which locks the user out of the screen they
 * would update Pro from.
 *
 * Deliberately empty: any shape of Pro 7.x implementation satisfies it, so the
 * shim cannot fail on a signature it does not match. Under it, a loop block
 * finds no registered processor and records on the block that it did not run —
 * the loud failure the design spec asks for, rather than a white screen.
 *
 * @deprecated 8.0.0 Remove in 8.1, once Pro 8.0.0 is the floor.
 *
 * @package Uncanny_Automator\App\Recipe_Runner\Services
 */
interface Loop_Processor {
}
