<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Events;

use Uncanny_Automator\App\Events\Dtos\Event_Dto;
use Uncanny_Automator\App\Events\Dtos\Recipe_Changed;
use Uncanny_Automator\App\Events\Dtos\Recipe_Fields_Changed;
use Uncanny_Automator\App\Events\Dtos\Recipe_Status_Changed;
use Uncanny_Automator\App\Events\Enums\Recipe_Change_Operation;
use Uncanny_Automator\App\Recipe_Builder\Field\Services\Declared_Field_Codes_Resolver;

/**
 * Fans `recipe_changed` out to specific hooks, so a listener subscribes only to the
 * change it handles and gets the detail it needs.
 *
 * `Recipe_Change_Events` calls it right after `automator_event_recipe_changed`, when an
 * operation ends, with what its writes recorded (`Recipe_Change_Record::take()`). It fires, in order:
 * - `automator_event_{item_type}_{operation}` with the same `Recipe_Changed`;
 * - `automator_event_{item_type}_created` with a `Recipe_Changed`, per other item the operation
 *   inserted (a copy's triggers and actions, a loop's default filter, a wrapping block's content);
 * - `automator_event_{item_type}_fields_changed` with a `Recipe_Fields_Changed`, per item
 *   whose fields changed;
 * - `automator_event_{item_type}_status_changed` with a `Recipe_Status_Changed`, per
 *   recipe or item whose status changed.
 *
 * Every hook goes through `fire()`. The save has landed by the time a hook fires, so a listener's
 * throw is logged and never reaches the write's caller. Each listener is guarded on its own, inside
 * WordPress's dispatch, so WordPress finishes its own hook bookkeeping and every other listener, on
 * that hook and the next, still runs.
 *
 * @since 8.0.0
 */
final class Recipe_Change_Fanout {

	/**
	 * Suffix a custom value's companion key carries, in any case (trigger configuration keys are stored uppercase).
	 */
	const CUSTOM_SUFFIX = '_custom';

	/**
	 * Fire the specific hooks for one write.
	 *
	 * @param Recipe_Changed $event   The write's event.
	 * @param array          $changes What the write recorded, from `Recipe_Change_Record::take()`.
	 *
	 * @return void
	 */
	public static function fan_out( Recipe_Changed $event, array $changes ): void {
		self::fire( $event->item_type() . '_' . $event->operation(), static fn() => $event );

		foreach ( (array) ( $changes['created'] ?? array() ) as $entry ) {
			if ( $entry['item_type'] === $event->item_type() && $entry['item_id'] === $event->item_id() ) {
				continue;
			}

			self::fire(
				$entry['item_type'] . '_' . Recipe_Change_Operation::CREATED,
				static fn() => new Recipe_Changed( Recipe_Change_Operation::CREATED, $entry['item_type'], $entry['item_id'], $entry['recipe_id'] )
			);
		}

		foreach ( (array) ( $changes['fields'] ?? array() ) as $entry ) {
			$name = $entry['item_type'] . '_fields_changed';
			if ( ! has_action( 'automator_event_' . $name ) ) {
				continue;
			}

			self::fire(
				$name,
				static function () use ( $entry ) {
					$fields = self::field_codes( $entry );

					return array() === $fields ? null : new Recipe_Fields_Changed( $entry['item_type'], $entry['item_id'], $entry['recipe_id'], $fields );
				}
			);
		}

		foreach ( (array) ( $changes['statuses'] ?? array() ) as $entry ) {
			self::fire(
				$entry['item_type'] . '_status_changed',
				static fn() => new Recipe_Status_Changed( $entry['item_type'], $entry['item_id'], $entry['recipe_id'], $entry['old_status'], $entry['new_status'] )
			);
		}
	}

	/**
	 * Build a payload and dispatch it. A throw from a listener, or from building the payload,
	 * is logged (forced, so it lands without debug mode) and goes no further.
	 *
	 * @param string   $name    Event name; fires as `automator_event_{$name}`.
	 * @param callable $payload Returns the payload, or null when there is nothing to report.
	 *
	 * @return void
	 */
	public static function fire( string $name, callable $payload ): void {
		try {
			$dto = $payload();
		} catch ( \Throwable $e ) {
			self::log_throw( $name, $e );
			return;
		}

		if ( ! $dto instanceof Event_Dto ) {
			return;
		}

		$guarded = self::guard_listeners( $name );
		try {
			Dispatcher::dispatch( $name, $dto );
		} catch ( \Throwable $e ) {
			// Only a listener added while the hook was firing is unguarded; its throw unwound WordPress's dispatch.
			self::log_throw( $name, $e );
		} finally {
			self::unguard_listeners( $name, $guarded );
		}
	}

	/**
	 * Wrap each listener on `automator_event_{$name}` so its throw is caught inside WordPress's own
	 * dispatch. A throw that crosses `do_action()` skips WordPress's cleanup (its current-hook stack and
	 * the hook's nesting level stay set for the rest of the request) and every later listener on the hook.
	 * The wrappers keep each listener's id and accepted arguments, so `remove_action()` and `has_action()`
	 * still find it while the hook fires.
	 *
	 * @param string $name Event name.
	 *
	 * @return array<int, array<string, callable>> The original callbacks, by priority and id.
	 */
	private static function guard_listeners( string $name ): array {
		$hook = $GLOBALS['wp_filter'][ 'automator_event_' . $name ] ?? null;
		if ( ! $hook instanceof \WP_Hook ) {
			return array();
		}

		$originals = array();
		foreach ( $hook->callbacks as $priority => $callbacks ) {
			foreach ( $callbacks as $id => $callback ) {
				$listener                                        = $callback['function'];
				$originals[ $priority ][ $id ]                   = $listener;
				$hook->callbacks[ $priority ][ $id ]['function'] = static function ( ...$args ) use ( $name, $listener ) {
					try {
						return call_user_func_array( $listener, $args );
					} catch ( \Throwable $e ) {
						self::log_throw( $name, $e );
						return null;
					}
				};
			}
		}

		return $originals;
	}

	/**
	 * Put back the listeners `guard_listeners()` wrapped, except any removed while the hook fired.
	 *
	 * @param string                               $name      Event name.
	 * @param array<int, array<string, callable>> $originals The original callbacks, by priority and id.
	 *
	 * @return void
	 */
	private static function unguard_listeners( string $name, array $originals ): void {
		$hook = $GLOBALS['wp_filter'][ 'automator_event_' . $name ] ?? null;
		if ( ! $hook instanceof \WP_Hook ) {
			return;
		}

		foreach ( $originals as $priority => $listeners ) {
			foreach ( $listeners as $id => $listener ) {
				if ( isset( $hook->callbacks[ $priority ][ $id ] ) ) {
					$hook->callbacks[ $priority ][ $id ]['function'] = $listener;
				}
			}
		}
	}

	/**
	 * Log a contained throw.
	 *
	 * @param string     $name Event name.
	 * @param \Throwable $e    The throw.
	 *
	 * @return void
	 */
	private static function log_throw( string $name, \Throwable $e ): void {
		automator_log(
			sprintf( 'A listener on automator_event_%s threw after the save landed; the save stands. %s: %s', $name, get_class( $e ), $e->getMessage() ),
			'Recipe_Change_Fanout',
			true
		);
	}

	/**
	 * The field codes an entry reports. A trigger's or action's written meta keys are
	 * filtered to its declared field codes, a `{code}_custom` key (any case) counting as `{code}`;
	 * a block's or block modifier's keys are its field-bag keys already.
	 *
	 * @param array $entry A field entry from `Recipe_Change_Record::take()`.
	 *
	 * @return list<string>
	 */
	private static function field_codes( array $entry ): array {
		if ( '' === $entry['item_code'] ) {
			return $entry['keys'];
		}

		$declared = ( new Declared_Field_Codes_Resolver() )->resolve(
			$entry['integration_code'],
			$entry['item_type'],
			$entry['item_code'],
			array_fill_keys( $entry['keys'], '' )
		);

		$codes = array();
		foreach ( $entry['keys'] as $key ) {
			$base = self::CUSTOM_SUFFIX === strtolower( substr( $key, -strlen( self::CUSTOM_SUFFIX ) ) ) ? substr( $key, 0, -strlen( self::CUSTOM_SUFFIX ) ) : null;
			// No field's own code ends in `_custom`, so the key is always its field's custom value, even when
			// the definition could not be resolved and the degraded declared set carries the companion key itself.
			if ( null !== $base && ( in_array( $base, $declared, true ) || in_array( $key, $declared, true ) ) ) {
				$codes[ $base ] = true;
			} elseif ( null === $base && in_array( $key, $declared, true ) ) {
				$codes[ $key ] = true;
			}
		}

		return array_keys( $codes );
	}
}
