<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Presentation\Admin\Notices;

/**
 * One Automator admin notice. Every notice extends this and is shown by the
 * Notice_Center, never by a hook of its own: the center decides who sees it,
 * where, in what order and in what look.
 *
 * `message()` only reads: it may run on a screen where the notice is then
 * not drawn (counted under "more notices"). A notice that consumes one-shot
 * state does so in `shown()`, which runs only once it was drawn.
 *
 * @since 8.0.0
 */
abstract class Notice {

	/**
	 * Shown on every admin screen.
	 */
	public const EVERYWHERE = 'everywhere';

	/**
	 * Shown on Automator's own screens only.
	 */
	public const AUTOMATOR_SCREENS = 'automator_screens';

	/**
	 * The priority of a notice Automator has not judged more urgent.
	 */
	public const DEFAULT_PRIORITY = 50;

	/**
	 * The notice's stable id: lower case letters, digits and hyphens. Its
	 * dismissals are stored under it.
	 *
	 * @return string
	 */
	abstract public function id(): string;

	/**
	 * What the notice says on this screen right now; null when it has
	 * nothing to say. Reads only: no remote call, no state consumed.
	 *
	 * @param Notice_Screen $screen The screen it would be drawn on.
	 *
	 * @return Notice_Message|null
	 */
	abstract public function message( Notice_Screen $screen ): ?Notice_Message;

	/**
	 * Every message the notice can show, one per variant (tier, state,
	 * outcome), with sample values: what the center draws instead of
	 * `message()` while `Notice_Center::SHOW_ALL_FILTER` is on, so every
	 * notice a customer could meet can be reviewed. Build each with the code
	 * `message()` uses, so the copy cannot drift. Reads no state.
	 *
	 * @return Notice_Message[]
	 */
	abstract public function previews(): array;

	/**
	 * The capability a user needs to see it.
	 *
	 * @return string
	 */
	public function capability(): string {

		return automator_get_admin_capability();
	}

	/**
	 * Where it shows: self::EVERYWHERE or self::AUTOMATOR_SCREENS.
	 *
	 * @return string
	 */
	public function placement(): string {

		return self::EVERYWHERE;
	}

	/**
	 * How urgent it is, as Automator judges it; lower comes first, ahead of
	 * severity. At the default, its severity decides.
	 *
	 * @return int
	 */
	public function priority(): int {

		return self::DEFAULT_PRIORITY;
	}

	/**
	 * How it may be put away.
	 *
	 * @return Notice_Dismissal
	 */
	public function dismissal(): Notice_Dismissal {

		return Notice_Dismissal::none();
	}

	/**
	 * Called once the notice was drawn: where a one-shot notice consumes the
	 * state it reported.
	 *
	 * @return void
	 */
	public function shown(): void {
	}

	/**
	 * Called once the center stored this user's dismissal: where a notice
	 * that keeps its own state (an option a migration also reads) records it.
	 *
	 * @return void
	 */
	public function dismissed(): void {
	}
}
