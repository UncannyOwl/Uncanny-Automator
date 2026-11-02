<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Events;

use Uncanny_Automator\App\Events\Dtos\Recipe_Changed;
use Uncanny_Automator\App\Infrastructure\Cache\Recipe_Cache;

/**
 * Reports what one recipe operation changed: one `automator_event_recipe_changed`,
 * then the specific hooks from `Recipe_Change_Fanout`.
 *
 * Every public recipe write service runs its body inside `operation()` and notes
 * its change with `changed()`. A write that calls another write needs nothing
 * more: the inner write is a nested operation and fires nothing; the outermost
 * one reports, with what every write inside it recorded (`Recipe_Change_Record`).
 *
 * The contract of `operation()`:
 * - the outermost operation starts clean, so nothing from an earlier write rides on it;
 * - a body that noted an event dispatches it when the body returns, even with an error
 *   (the fields landed, the switch-on was refused), and when it throws afterwards: the event first,
 *   then the body's own throw reaches the caller;
 * - a body that returns or throws without noting drops what it recorded, a nested write's notes
 *   included. Nothing is rolled back, so a write that fails after it wrote must note what landed
 *   before it returns its error, or undo the write;
 * - the dispatch runs after the depth is back to zero, outside every service's own `try`, so a
 *   listener's own write is a fresh operation;
 * - a listener's write to the item whose event is firing (a re-save of what it heard) is part of that
 *   save: stored and invalidated, never announced again, so a listener that re-saves on every event
 *   cannot recurse. A write to any other item, on the same recipe or another, fires its own events, so
 *   an item a listener adds gets its created listeners. Announcements nest at most
 *   `MAX_NESTED_ANNOUNCEMENTS` deep; past that a write is stored and invalidated, and the stop is logged;
 * - a listener's throw is logged and never reaches the write's caller: the save has landed, and a
 *   failure reported for it would invite a retry and a duplicate (`Recipe_Change_Fanout::fire()`).
 *
 * The touched recipes are invalidated before the event, so a listener reads the saved values,
 * and again after the listeners and the fan-out, even when one throws, because the cached tree holds field values.
 *
 * @since 8.0.0
 */
final class Recipe_Change_Events {

	/**
	 * How many operations are open.
	 *
	 * @var int
	 */
	private static int $depth = 0;

	/**
	 * Recipes the open operation touched, keyed by id.
	 *
	 * @var array<int, true>
	 */
	private static array $recipes = array();

	/**
	 * The change the outermost write noted, dispatched when its operation ends.
	 *
	 * @var Recipe_Changed|null
	 */
	private static ?Recipe_Changed $pending = null;

	/**
	 * How deep announcements may nest: a listener's write fires its own events, whose listeners may write in turn.
	 */
	const MAX_NESTED_ANNOUNCEMENTS = 5;

	/**
	 * Items whose events are firing, keyed `item_type:item_id`.
	 *
	 * @var array<string, true>
	 */
	private static array $announcing = array();

	/**
	 * Run a recipe write as one operation.
	 *
	 * @param callable $write The write.
	 *
	 * @return mixed What `$write` returns.
	 */
	public static function operation( callable $write ) {
		$outermost = 0 === self::$depth;
		if ( $outermost ) {
			self::forget();
		}

		++self::$depth;
		$thrown = null;
		try {
			$result = $write();
		} catch ( \Throwable $e ) {
			$thrown = $e;
		} finally {
			--self::$depth;
			$closing = $outermost ? self::close() : null;
		}

		// What the body noted landed, even when it threw afterwards; the body's own throw still reaches the caller.
		try {
			if ( null !== $closing ) {
				self::dispatch( ...$closing );
			}
		} finally {
			if ( null !== $thrown ) {
				throw $thrown;
			}
		}

		return $result;
	}

	/**
	 * Note a persisted change. The outermost write's note is the operation's event;
	 * a nested write's note only adds its recipe. Outside any operation the change is
	 * its own operation.
	 *
	 * @param Recipe_Changed $event The change the write made.
	 *
	 * @return void
	 */
	public static function changed( Recipe_Changed $event ): void {
		if ( 0 === self::$depth ) {
			self::operation(
				static function () use ( $event ) {
					self::changed( $event );
				}
			);
			return;
		}

		self::$recipes[ $event->recipe_id() ] = true;
		if ( 1 === self::$depth ) {
			self::$pending = $event;
		}
	}

	/**
	 * Whether a recipe write operation is open.
	 *
	 * @return bool
	 */
	public static function in_operation(): bool {
		return 0 < self::$depth;
	}

	/**
	 * End the outermost operation: hand back what to dispatch, if it noted an event,
	 * and forget everything else it recorded.
	 *
	 * @return array{0: Recipe_Changed, 1: int[], 2: array}|null The event, its recipes and its recorded changes.
	 */
	private static function close(): ?array {
		$event   = self::$pending;
		$recipes = array_keys( self::$recipes );
		$changes = null === $event ? null : Recipe_Change_Record::take( $recipes );
		self::forget();

		return null === $event ? null : array( $event, $recipes, $changes );
	}

	/**
	 * Dispatch an ended operation's event and its specific hooks.
	 *
	 * @param Recipe_Changed $event   The operation's event.
	 * @param int[]          $recipes Recipes it touched.
	 * @param array          $changes What its writes recorded.
	 *
	 * @return void
	 */
	private static function dispatch( Recipe_Changed $event, array $recipes, array $changes ): void {
		foreach ( $recipes as $recipe_id ) {
			Recipe_Cache::invalidate( (int) $recipe_id );
		}

		// A listener's re-save of the item whose event is firing is part of that save. Announcing it again
		// would reach the same listener, and a listener that re-saves every time would recurse until PHP dies.
		$item = $event->item_type() . ':' . $event->item_id();
		if ( isset( self::$announcing[ $item ] ) ) {
			return;
		}

		if ( count( self::$announcing ) >= self::MAX_NESTED_ANNOUNCEMENTS ) {
			automator_log(
				sprintf( 'Recipe change events are nested %d deep, so "%s" %s on recipe %d is stored but not announced.', count( self::$announcing ), $event->operation(), $item, $event->recipe_id() ),
				'Recipe_Change_Events',
				true
			);
			return;
		}

		self::$announcing[ $item ] = true;

		try {
			Recipe_Change_Fanout::fire( 'recipe_changed', static fn() => $event );

			Recipe_Change_Fanout::fan_out( $event, $changes );
		} finally {
			// The cached tree holds field values, so a tree a listener built before another listener's meta write is stale,
			// even when a later listener throws.
			foreach ( $recipes as $recipe_id ) {
				Recipe_Cache::invalidate( (int) $recipe_id );
			}
			unset( self::$announcing[ $item ] );
		}
	}

	/**
	 * Forget the open operation's state.
	 *
	 * @return void
	 */
	private static function forget(): void {
		self::$recipes = array();
		self::$pending = null;
		Recipe_Change_Record::reset();
	}
}
