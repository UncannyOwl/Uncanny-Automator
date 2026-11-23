<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Integration_Catalog\App_Events;

use Uncanny_Automator\App\Integration_Catalog\App_Events\Value_Objects\App_Event_Summary;

/**
 * Reads a vendor event's data block: the list of events it carries, and the
 * summary any app's delivery gets when the app says nothing more.
 *
 * The list sits under however many `data` wrappers the platform stacked: one
 * per envelope layer. Deliveries queued before a layer was added or removed
 * still carry the old depth, so the list is found by descending `data` keys,
 * never at a fixed depth.
 *
 * @since 8.1.0
 */
final class App_Event_Data {

	/**
	 * How many `data` wrappers deep the list may sit.
	 *
	 * @var int
	 */
	const MAX_DEPTH = 4;

	/**
	 * The delivery's list of events, or null when no level carries one.
	 *
	 * An empty list is a delivery with nothing new (a cursor-only ping); a
	 * missing one is a change in the envelope's shape.
	 *
	 * @since 8.1.0
	 *
	 * @param array<string, mixed> $data The vendor event's data block.
	 *
	 * @return array<int|string, mixed>|null
	 */
	public static function find_events( array $data ): ?array {
		$cursor = $data;

		for ( $depth = 0; $depth < self::MAX_DEPTH; $depth++ ) {
			if ( isset( $cursor['events'] ) && is_array( $cursor['events'] ) ) {
				return $cursor['events'];
			}

			if ( ! isset( $cursor['data'] ) || ! is_array( $cursor['data'] ) ) {
				return null;
			}

			$cursor = $cursor['data'];
		}

		return null;
	}

	/**
	 * The delivery's events that are events: each one an array.
	 *
	 * @since 8.1.0
	 *
	 * @param array<string, mixed> $data The vendor event's data block.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function events( array $data ): array {
		return array_values( array_filter( (array) self::find_events( $data ), 'is_array' ) );
	}

	/**
	 * The summary of a delivery from an app that names none of its changes:
	 * "3 changes", or "No changes".
	 *
	 * @since 8.1.0
	 *
	 * @param array<string, mixed> $data The vendor event's data block.
	 *
	 * @return App_Event_Summary
	 */
	public static function summarize_changes( array $data ): App_Event_Summary {
		$count = count( self::events( $data ) );

		return new App_Event_Summary( self::changes_label( $count ), $count );
	}

	/**
	 * "No changes", or a count of changes the Logs page does not name.
	 *
	 * @since 8.1.0
	 *
	 * @param int $count How many.
	 *
	 * @return string
	 */
	public static function changes_label( int $count ): string {
		if ( 0 === $count ) {
			return esc_html_x( 'No changes', 'App event summary', 'uncanny-automator' );
		}

		/* translators: %d: How many changes of a kind the Logs page does not name. */
		return sprintf( esc_html( _nx( '%d change', '%d changes', $count, 'App event summary', 'uncanny-automator' ) ), $count );
	}
}
