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
 * Entry points:
 *
 * - {@see self::action()}             — passthrough for `do_action()`
 * - {@see self::filter()}             — passthrough for `apply_filters()`
 * - {@see self::filter_unwinding_on_throw()} — `apply_filters()` that puts WordPress's filter stack back when a callback throws
 * - {@see self::action_deprecated()}  — passthrough for `do_action_deprecated()`
 * - {@see self::filter_deprecated()}  — passthrough for `apply_filters_deprecated()`
 * - {@see self::dispatch()}           — typed `automator_event_*` channel
 *
 * @package Uncanny_Automator\App\Events
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
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- Passthrough to WordPress; the caller supplies the full hook name, which carries our prefix for our own hooks.
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
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- Passthrough to WordPress; the caller supplies the full hook name, which carries our prefix for our own hooks.
		return apply_filters( $hook, $value, ...$args );
	}

	/**
	 * Apply a filter hook, and put WordPress's filter stack back if a callback throws.
	 *
	 * WordPress adds the hook name to `$wp_current_filter` before the callbacks run and
	 * removes it only when they return. A callback that throws leaves the name behind, so
	 * `current_filter()` and `doing_filter()` report the hook for the rest of the request,
	 * and an outer hook's own cleanup removes the wrong entry. This trims the stack back to
	 * the depth it had before the call and rethrows, so a caller that catches carries on
	 * with WordPress's hook context as it was.
	 *
	 * WordPress offers no way to reset the hook object's own nesting count; that count only
	 * matters when callbacks are added to or removed from this hook while it runs.
	 *
	 * @since 8.0.0
	 *
	 * @param string $hook    Hook name.
	 * @param mixed  $value   Initial value to filter.
	 * @param mixed  ...$args Additional filter arguments (variadic).
	 *
	 * @return mixed The filtered value.
	 *
	 * @throws \Throwable Whatever a callback threw, after the filter stack is put back.
	 */
	public static function filter_unwinding_on_throw( string $hook, $value, ...$args ) {
		global $wp_current_filter;

		$depth = is_array( $wp_current_filter ) ? count( $wp_current_filter ) : 0;

		try {
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- Passthrough to WordPress; the caller supplies the full hook name, which carries our prefix for our own hooks.
			return apply_filters( $hook, $value, ...$args );
		} catch ( \Throwable $e ) {
			if ( is_array( $wp_current_filter ) && count( $wp_current_filter ) > $depth ) {
				array_splice( $wp_current_filter, $depth );
			}

			throw $e;
		}
	}

	/**
	 * Fire a deprecated action hook.
	 *
	 * Passthrough for `do_action_deprecated()`. Emits the standard
	 * `_doing_it_wrong`-style deprecation notice on every call while
	 * still firing the legacy hook so existing subscribers continue to
	 * run during the migration window.
	 *
	 * @param string $hook        Hook name.
	 * @param array  $args        Arguments to pass to the hook callbacks.
	 * @param string $version     Version that deprecated the hook.
	 * @param string $replacement Optional replacement hook name.
	 * @param string $message     Optional human-readable deprecation message.
	 *
	 * @return void
	 */
	public static function action_deprecated( string $hook, array $args, string $version, string $replacement = '', string $message = '' ): void {
		do_action_deprecated( $hook, $args, $version, $replacement, $message );
	}

	/**
	 * Apply a deprecated filter hook.
	 *
	 * Passthrough for `apply_filters_deprecated()`. Emits the standard
	 * `_doing_it_wrong`-style deprecation notice on every call while
	 * still applying the legacy filter so existing subscribers continue
	 * to influence the value during the migration window.
	 *
	 * @param string $hook        Hook name.
	 * @param array  $args        Arguments (first element is the value to filter).
	 * @param string $version     Version that deprecated the hook.
	 * @param string $replacement Optional replacement hook name.
	 * @param string $message     Optional human-readable deprecation message.
	 *
	 * @return mixed The filtered value.
	 */
	public static function filter_deprecated( string $hook, array $args, string $version, string $replacement = '', string $message = '' ) {
		return apply_filters_deprecated( $hook, $args, $version, $replacement, $message );
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
