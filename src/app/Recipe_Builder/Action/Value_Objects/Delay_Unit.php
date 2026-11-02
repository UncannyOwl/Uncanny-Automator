<?php
declare(strict_types=1);
namespace Uncanny_Automator\App\Recipe_Builder\Action\Value_Objects;

use Uncanny_Automator\App\Recipe_Builder\Shared\Polyfill\Str;

/**
 * Delay Unit Value Object, and the single source of truth for what a delay
 * unit is anywhere in the plugin.
 *
 * `DELAY_UNIT` reaches this class from three directions that historically each
 * carried their own table: the Delay block's picker, the legacy
 * `async_delay_unit` post meta, and MCP tool input. They disagreed — the picker
 * offered `weeks`, this class rejected it, and the wire mapper multiplied an
 * unknown unit by 1, so a four-week delay became four seconds. One table now
 * answers for all three.
 *
 * Legacy input is normalised rather than rejected: singular forms
 * (`day`, `week`) are what the pre-8.0.0 meta and some MCP callers write.
 * {@see self::canonical()} is the plural form everything downstream stores.
 */
class Delay_Unit {

	/**
	 * Every accepted spelling mapped to its length in seconds.
	 *
	 * Singular forms are accepted because legacy `async_delay_unit` meta and
	 * MCP callers write them; they normalise to the plural on the way out.
	 * Months are 30 days and years 365 days — a delay is a relative offset
	 * computed once at schedule time, not a calendar walk.
	 *
	 * @var array<string,int>
	 */
	private const SECONDS = array(
		'second'  => 1,
		'seconds' => 1,
		'minute'  => 60,
		'minutes' => 60,
		'hour'    => 3600,
		'hours'   => 3600,
		'day'     => 86400,
		'days'    => 86400,
		'week'    => 604800,
		'weeks'   => 604800,
		'month'   => 2592000,
		'months'  => 2592000,
		'year'    => 31536000,
		'years'   => 31536000,
	);

	/**
	 * The canonical plural spelling of every unit we ACCEPT, ascending.
	 *
	 * Wider than what any picker offers on purpose: migrated legacy delays and
	 * MCP callers carry whatever `async_delay_unit` held, and rejecting one of
	 * those loses a configured wait rather than correcting it.
	 *
	 * @var string[]
	 */
	private const CANONICAL = array( 'seconds', 'minutes', 'hours', 'days', 'weeks', 'months', 'years' );

	/**
	 * The units a picker OFFERS, ascending — a curated subset of CANONICAL.
	 *
	 * Matches the released `async_delay_unit` vocabulary — seconds, minutes,
	 * hours, days, years — so nothing a migrated or MCP-authored delay can
	 * carry stops being expressible. That set is verifiable from this tree:
	 * it is what the pre-8.0.0 MCP enum published and what every
	 * `async_delay_unit` reader still accepts. (It was also the legacy delay
	 * UI's option list, but that UI is not in this codebase — it was removed
	 * before this stack's base — so the wire vocabulary is the checkable
	 * justification.)
	 *
	 * Plus `weeks`, which the delay block already offered and which every
	 * consumer handles — the legacy path hands the unit to `strtotime`, which
	 * knows `weeks` natively, and the new path multiplies by a constant.
	 *
	 * `months` is accepted but not offered: no picker has ever shown it, and a
	 * 30-day "month" is a poor thing to put in front of an author.
	 *
	 * @var string[]
	 */
	private const OFFERED = array( 'seconds', 'minutes', 'hours', 'days', 'weeks', 'years' );

	/**
	 * The chosen unit of time, canonicalised to its plural form.
	 *
	 * @var string
	 */
	private string $value;

	/**
	 * Constructor.
	 *
	 * @param string $value Delay unit value, singular or plural.
	 * @throws \InvalidArgumentException If invalid.
	 */
	public function __construct( string $value ) {
		$this->validate( $value );
		$this->value = self::canonical( $value );
	}

	/**
	 * The unit of time the delay is counted in, in its canonical plural form.
	 *
	 * @return string
	 */
	public function get_value(): string {
		return $this->value;
	}

	/**
	 * Convert delay to seconds based on unit.
	 *
	 * @param int $number The delay number.
	 * @return int Total seconds.
	 */
	public function to_seconds( int $number ): int {
		return $number * self::SECONDS[ $this->value ];
	}

	/**
	 * The length of one unit in seconds, for callers that hold a raw string
	 * and must not fatal on a bad one — the tree walk reads whatever storage
	 * happens to hold and has to keep building.
	 *
	 * @param string $unit Delay unit, any accepted spelling, any case.
	 * @return int|null Seconds per unit, or null when the unit is unknown.
	 */
	public static function seconds_for( string $unit ): ?int {
		return self::SECONDS[ strtolower( trim( $unit ) ) ] ?? null;
	}

	/**
	 * Normalise any accepted spelling to the canonical plural form.
	 *
	 * @param string $unit Delay unit, any accepted spelling, any case.
	 * @return string Canonical plural form, or the trimmed lowercase input when unknown.
	 */
	public static function canonical( string $unit ): string {
		$unit = strtolower( trim( $unit ) );
		if ( ! isset( self::SECONDS[ $unit ] ) ) {
			return $unit;
		}

		return Str::ends_with( $unit, 's' ) ? $unit : $unit . 's';
	}

	/**
	 * Whether a string names a unit this class understands.
	 *
	 * @param string $unit Delay unit, any accepted spelling, any case.
	 * @return bool
	 */
	public static function is_valid( string $unit ): bool {
		return null !== self::seconds_for( $unit );
	}

	/**
	 * Every unit this class accepts, canonical plural, ascending — for tool
	 * schemas and documentation, so none of them keeps its own copy.
	 *
	 * @return string[]
	 */
	public static function accepted(): array {
		return self::CANONICAL;
	}

	/**
	 * The units a picker should offer, ascending. Always a subset of
	 * {@see self::accepted()}, so a user can never select a unit the backend
	 * would refuse.
	 *
	 * @return string[]
	 */
	public static function offered(): array {
		return self::OFFERED;
	}

	/**
	 * Validate delay unit.
	 *
	 * @param string $value Value to validate.
	 * @throws \InvalidArgumentException If invalid.
	 */
	private function validate( string $value ): void {
		if ( self::is_valid( $value ) ) {
			return;
		}

		throw new \InvalidArgumentException(
			'Delay unit must be one of: ' . esc_html( implode( ', ', self::CANONICAL ) ) . '. Got: ' . esc_html( $value )
		);
	}
}
