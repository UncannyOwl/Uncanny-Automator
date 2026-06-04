<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Events;

use Uncanny_Automator\App\Events\Dtos\Event_Dto;

/**
 * Central dispatcher for API events, actions, and filters.
 *
 * Per the api-layer skill (Rule 7), every WordPress action and filter
 * dispatch in `src/app/` goes through this class. Subscribers still use
 * the standard `add_action()` / `add_filter()` against the existing hook
 * names — only the *firing* side is centralised so the full surface area
 * of hooks the API layer exposes is grep-discoverable in one place.
 *
 * Three entry points:
 *
 * - {@see self::action()}     — passthrough for `do_action()`
 * - {@see self::filter()}     — passthrough for `apply_filters()`
 * - {@see self::dispatch()}   — typed `automator_event_*` channel
 *
 * @package Uncanny_Automator\App\Events
 * @since   7.4.0
 */
final class Dispatcher {

	/**
	 * Fire an action hook.
	 *
	 * Passthrough for `do_action()`. The hook name and arguments are
	 * preserved exactly so existing subscribers continue to work.
	 *
	 * Why route through the dispatcher? Centralising the firing side
	 * makes every hook the API layer exposes grep-discoverable from
	 * `Dispatcher::action(`, which lets us audit, document, and
	 * deprecate the public hook surface in one place.
	 *
	 * @param string $hook    Hook name.
	 * @param mixed  ...$args Hook arguments (variadic).
	 *
	 * @return void
	 */
	public static function action( string $hook, ...$args ): void {
		do_action( $hook, ...$args );
	}

	/**
	 * Apply a filter hook.
	 *
	 * Passthrough for `apply_filters()`. The hook name, value, and
	 * arguments are preserved exactly so existing filter subscribers
	 * continue to work.
	 *
	 * @param string $hook    Hook name.
	 * @param mixed  $value   Initial value to filter.
	 * @param mixed  ...$args Additional filter arguments (variadic).
	 *
	 * @return mixed The filtered value.
	 */
	public static function filter( string $hook, $value, ...$args ) {
		return apply_filters( $hook, $value, ...$args );
	}

	/**
	 * Dispatch a typed API event on the `automator_event_*` channel.
	 *
	 * Event consumers receive a single DTO payload so transport layers
	 * and extensions can share one stable event shape.
	 *
	 * @param string         $event_name Event name suffix.
	 * @param Event_Dto|null $data       DTO payload for listeners.
	 *
	 * @return void
	 */
	public static function dispatch( string $event_name, ?Event_Dto $data = null ): void {
		do_action( "automator_event_{$event_name}", $data );
	}
}
