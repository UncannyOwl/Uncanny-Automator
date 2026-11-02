<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Recipe_Builder\Recipe\Schema\Value_Objects;

use DateTime;
use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Block_Store;
use Uncanny_Automator\App\Recipe_Builder\Block\Enums\Block_Path_Code;
use Uncanny_Automator\App\Recipe_Builder\Block\Enums\Block_Type;
use Uncanny_Automator\App\Recipe_Builder\Block\Registry\Blocks\Delay_Block;
use Uncanny_Automator\App\Recipe_Builder\Action\Value_Objects\Delay_Unit;
use Uncanny_Automator\App\Recipe_Builder\Block\Value_Objects\Fields_Bag;
use Uncanny_Automator\App\Recipe_Builder\Token\Services\Token_Dependency_Extractor;

/**
 * Tree-entry projection for a delay_schedule block.
 *
 * Owns the delay-block-specific computations the previous tree-builder
 * implementation had inline:
 *
 *  - `delay_seconds()` — `DELAY_VALUE × unit_factor(DELAY_UNIT)` for
 *    `mode='delay'`. The factor comes from {@see Delay_Unit}, whose set is
 *    wider than the block's picker offers; that class says why.
 *  - `schedule_at()` — fixed-point Unix timestamp parsed from
 *    `SCHEDULE_DATE` + `SCHEDULE_TIME` in the site's WP timezone
 *    (the explicit timezone arg is load-bearing — naive `strtotime`
 *    would shift by `gmt_offset`).
 *  - `delay_token` — token-resolved expression passed through verbatim.
 *  - `inner_action_ids` — direct (non-block) children of the delay block,
 *    sourced from `Block_Store::collect_inner_action_ids()` so the
 *    wrapped-action discovery routes through the store boundary.
 *  - `depends_on` — token-reference scan over the `DELAY_TOKEN` field
 *    only (other fields are literal scalars).
 *
 * Each of the three mode-specific values above is gated on `DELAY_MODE`, so a
 * block that kept stale fields from an earlier mode selection projects one
 * live value rather than two. A mode outside the block's own vocabulary
 * projects all three, unchanged from before the gate.
 *
 * The Pro `Delay_Schedule_Block_Type` consumes these via the cached tree
 * → `Delay_Schedule_Schema_Node` round-trip; both surfaces speak the
 * same canonical keys.
 */
final class Delay_Schedule_Tree_Entry extends Block_Tree_Entry {

	/**
	 * How long the block waits, in seconds, once its delay value and unit are
	 * combined. Zero unless `DELAY_MODE` is `delay`.
	 *
	 * @var int
	 */
	private int $delay_seconds;

	/**
	 * Unix timestamp the block fires at when it is scheduled rather than delayed.
	 * Zero unless `DELAY_MODE` is `schedule`.
	 *
	 * @var int
	 */
	private int $schedule_at;

	/**
	 * Token the author supplied in place of a fixed delay, resolved at run time.
	 * Empty unless `DELAY_MODE` is `token`.
	 *
	 * @var string
	 */
	private string $delay_token;

	/**
	 * Ids of the actions held inside this block, which run once the wait elapses.
	 *
	 * @var array
	 */
	private array $inner_action_ids;

	/**
	 * Ids this block's own configuration reads from, so the tree knows what must resolve first.
	 *
	 * @var array
	 */
	private array $depends_on;

	/**
	 * Private by design: build through the named constructors below so every
	 * instance is fully projected before it is handed out.
	 *
	 */
	private function __construct() {}

	/**
	 * Delay_schedule blocks don't carry modifiers — they wrap their
	 * inner actions directly.
	 */
	public static function modifier_type(): ?string {
		return null;
	}

	/**
	 * The branch a delay/schedule block routes its items down.
	 *
	 * @return string Catalog path code.
	 */
	public static function default_path_code(): string {
		return Block_Path_Code::DELAY_COMPLETED;
	}

	/**
	 * Build from a block storage row.
	 *
	 * Unlike the other block entries this also takes the block store, because a
	 * delay/schedule reads its descendants to resolve what it is deferring.
	 *
	 * @param array                      $block_row       Block storage row.
	 * @param array                      $modifiers       Pre-built modifier projections.
	 * @param array                      $paths           Pre-built path buckets.
	 * @param Token_Dependency_Extractor $token_extractor Token-reference scanner.
	 * @param Block_Store                $block_store     Store used to read descendants.
	 *
	 * @return self
	 */
	public static function from_block_row(
		array $block_row,
		array $modifiers,
		array $paths,
		Token_Dependency_Extractor $token_extractor,
		Block_Store $block_store
	): self {
		$instance                  = new self();
		$instance->id              = (int) ( $block_row['id'] ?? 0 );
		$instance->block_type      = Block_Type::DELAY_SCHEDULE;
		$instance->fields          = is_array( $block_row['fields'] ?? null ) ? $block_row['fields'] : array();
		$instance->modifiers       = $modifiers;
		$instance->paths           = $paths;
		$instance->menu_order      = (int) ( $block_row['menu_order'] ?? 0 );
		$instance->legacy_group_id = $block_row['legacy_group_id'] ?? null;

		$bag   = Fields_Bag::from_array( $instance->fields );
		$mode  = strtolower( $bag->scalar( Delay_Block::FIELD_MODE ) );
		$token = $bag->scalar( Delay_Block::FIELD_TOKEN );

		$instance->delay_seconds = self::projects_for( $mode, Delay_Block::MODE_DELAY )
			? self::compute_delay_seconds( $bag )
			: 0;
		$instance->schedule_at   = self::projects_for( $mode, Delay_Block::MODE_SCHEDULE )
			? self::compute_schedule_at( $bag )
			: 0;
		$instance->delay_token   = self::projects_for( $mode, Delay_Block::MODE_TOKEN ) ? $token : '';

		$instance->inner_action_ids = $block_store->collect_inner_action_ids( $instance->id );
		$instance->depends_on       = $token_extractor->from_fields_bag(
			array( $token ),
			$instance->id
		);

		return $instance;
	}

	/**
	 * Flatten this delay/schedule block entry to the cached tree shape.
	 *
	 * @return array<string, mixed> Storage-ready projection.
	 */
	public function to_array(): array {
		return parent::to_array() + array(
			'delay_seconds'    => $this->delay_seconds,
			'schedule_at'      => $this->schedule_at,
			'delay_token'      => $this->delay_token,
			'inner_action_ids' => $this->inner_action_ids,
			'depends_on'       => $this->depends_on,
		);
	}


	/**
	 * The `DELAY_MODE` vocabulary, as declared by the Delay_Block radio.
	 *
	 * @var string[]
	 */
	private const KNOWN_MODES = array( Delay_Block::MODE_DELAY, Delay_Block::MODE_SCHEDULE, Delay_Block::MODE_TOKEN );

	/**
	 * Whether the projection belonging to `$owner_mode` should be computed for
	 * the block's stored `$mode`.
	 *
	 * A stored mode outside {@see self::KNOWN_MODES} — absent, empty, or a
	 * vocabulary this build predates — computes every projection, which is the
	 * un-gated output this class shipped with. That matches the unrecognised-unit
	 * fall-through in {@see self::compute_delay_seconds()}: degrade to the older
	 * behaviour rather than silently cancel a configured wait.
	 *
	 * @param string $mode       Stored `DELAY_MODE`, lowercased.
	 * @param string $owner_mode Mode the projection belongs to.
	 *
	 * @return bool
	 */
	private static function projects_for( string $mode, string $owner_mode ): bool {
		return $mode === $owner_mode || ! in_array( $mode, self::KNOWN_MODES, true );
	}

	/**
	 * Resolve the wait duration in seconds from `DELAY_VALUE` × the
	 * `DELAY_UNIT` factor.
	 *
	 * An amount the author never saved is the block's declared default
	 * ({@see Delay_Block::DEFAULT_DELAY_VALUE}), as the builder shows it; a
	 * saved amount, even '', is used as stored.
	 *
	 * The factor comes from {@see Delay_Unit}, which is the one table for
	 * delay units. The vocabulary is deliberately wider than the Delay_Block
	 * picker offers: a migrated legacy delay carries whatever
	 * `async_delay_unit` held, singular spellings included, and that set
	 * covers `seconds` through `years`.
	 *
	 * An unrecognised unit still falls through to the raw value rather than
	 * zero, so an unforeseen vocabulary degrades as it always has instead of
	 * silently cancelling the wait. That is why this reads the lenient
	 * `Delay_Unit::seconds_for()` rather than constructing the value object,
	 * which throws — a tree walk has to keep building whatever storage holds.
	 */
	private static function compute_delay_seconds( Fields_Bag $bag ): int {
		$value = null === $bag->get( 'DELAY_VALUE' ) ? Delay_Block::DEFAULT_DELAY_VALUE : (int) $bag->scalar( 'DELAY_VALUE' );
		$unit  = $bag->scalar( 'DELAY_UNIT' );
		if ( '' === trim( $unit ) ) {
			$unit = 'minutes';
		}

		$seconds = Delay_Unit::seconds_for( $unit );

		return null === $seconds ? $value : $value * $seconds;
	}

	/**
	 * Parse the scheduled timestamp from `SCHEDULE_DATE` + `SCHEDULE_TIME`
	 * in the site's WP timezone. Returns `0` when unset or unparseable —
	 * the scheduler treats `0` as "not set".
	 *
	 * The explicit `wp_timezone()` argument is load-bearing: naive
	 * `strtotime` would parse the user's input as UTC and shift by
	 * `gmt_offset`.
	 */
	private static function compute_schedule_at( Fields_Bag $bag ): int {
		$date = $bag->scalar( 'SCHEDULE_DATE' );
		$time = $bag->scalar( 'SCHEDULE_TIME' );
		if ( '' === $date ) {
			return 0;
		}

		$date_time = DateTime::createFromFormat(
			'Y-m-d g:i A',
			trim( $date . ' ' . $time ),
			wp_timezone()
		);
		if ( ! $date_time instanceof DateTime ) {
			return 0;
		}

		return (int) $date_time->getTimestamp();
	}
}
