<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Recipe_Logs\Lists\Enums;

/**
 * The four lists of the Logs page.
 *
 * Each value is the last segment of its route: `GET logs/{kind}`.
 *
 * @since 8.1.0
 */
final class Log_List_Kind {

	/**
	 * One entry per recipe run (`uap_recipe_log`).
	 *
	 * @var string
	 */
	const RECIPE_RUNS = 'recipe-runs';

	/**
	 * One entry per trigger log row (`uap_trigger_log`).
	 *
	 * @var string
	 */
	const TRIGGERS = 'trigger-logs';

	/**
	 * One entry per action log row (`uap_action_log`). Actions inside a loop
	 * have no such row and are not listed (decision L12).
	 *
	 * @var string
	 */
	const ACTIONS = 'action-logs';

	/**
	 * One entry per failed App action that can still be resent: the same
	 * actions the Resend App actions tool listed.
	 *
	 * @var string
	 */
	const FAILED_ACTIONS = 'failed-actions';

	/**
	 * Static-only class.
	 */
	private function __construct() {
	}

	/**
	 * Every kind, in the order of the page's tabs.
	 *
	 * @return string[]
	 */
	public static function all(): array {
		return array( self::RECIPE_RUNS, self::TRIGGERS, self::ACTIONS, self::FAILED_ACTIONS );
	}

	/**
	 * Whether a kind lists triggers or actions, and so has an item.
	 *
	 * @param string $kind A kind.
	 *
	 * @return bool
	 */
	public static function has_item( string $kind ): bool {
		return self::RECIPE_RUNS !== $kind;
	}

	/**
	 * Whether a kind lists action log rows.
	 *
	 * @param string $kind A kind.
	 *
	 * @return bool
	 */
	public static function lists_actions( string $kind ): bool {
		return self::ACTIONS === $kind || self::FAILED_ACTIONS === $kind;
	}
}
