<?php
/**
 * Dropbox Event Fanout
 *
 * @package Uncanny_Automator
 * @author  Uncanny Owl
 */

namespace Uncanny_Automator\Integrations\Dropbox;

use Uncanny_Automator\App\Integration_Catalog\App_Events\App_Event_Data;
use Uncanny_Automator\App\Integration_Catalog\App_Events\Value_Objects\App_Event_Summary;
use Uncanny_Automator\App_Integrations\App_Event_Fanout;

/**
 * Fires one WordPress hook per Dropbox change the platform delivers, one hook
 * per kind of change, so each trigger listens only to its own.
 *
 * The platform classifies Dropbox's change feed and delivers, per event:
 *
 *     [
 *         'event_type'           => 'created' | 'updated' | 'renamed' | 'moved' | 'deleted',
 *         'folder_path'          => '/Documents',       // the folder the item is in now
 *         'entry'                => [ '.tag', 'id', 'name', 'path_display', ... ],
 *         'previous_path'        => '/Inbox/report.pdf', // renamed and moved only
 *     ]
 *
 * Every hook passes ( $entry, $folder_path, $previous_path ).
 *
 * @since 8.0.0
 */
final class Dropbox_Event_Fanout extends App_Event_Fanout {

	/**
	 * Get integration slug.
	 *
	 * @return string
	 */
	protected function get_integration_slug(): string {
		return 'dropbox';
	}

	/**
	 * The integration code Dropbox's triggers carry.
	 *
	 * @since 8.1.0
	 *
	 * @return string
	 */
	public function get_integration_code(): string {
		return 'DROPBOX';
	}

	/**
	 * What a delivery says on the Logs page: its changes counted by kind
	 * ("2 created, 1 deleted"), and the names of the first items changed.
	 *
	 * @since 8.1.0
	 *
	 * @param array<string, mixed> $data The vendor event's data block.
	 *
	 * @return App_Event_Summary
	 */
	public function summarize( array $data ): App_Event_Summary {

		$events = App_Event_Data::events( $data );

		if ( array() === $events ) {
			return App_Event_Data::summarize_changes( $data );
		}

		$counts = array();
		$items  = array();

		foreach ( $events as $event ) {
			$type            = (string) ( $event['event_type'] ?? '' );
			$counts[ $type ] = ( $counts[ $type ] ?? 0 ) + 1;
			$items[]         = self::item_name( $event );
		}

		$parts = array();

		foreach ( $counts as $type => $count ) {
			$parts[] = self::count_label( (string) $type, $count );
		}

		return new App_Event_Summary( implode( ', ', $parts ), count( $events ), $items );
	}

	/**
	 * @param array<string, mixed> $event
	 * @param array<string, mixed> $envelope Full vendor_event data block (unused here).
	 *
	 * @return void
	 */
	protected function dispatch_event( array $event, array $envelope ): void {

		unset( $envelope );

		$hooks = array(
			'created' => 'automator_dropbox_item_created',
			'updated' => 'automator_dropbox_file_updated',
			'renamed' => 'automator_dropbox_item_renamed',
			'moved'   => 'automator_dropbox_item_moved',
			'deleted' => 'automator_dropbox_item_deleted',
		);

		$event_type = (string) ( $event['event_type'] ?? '' );
		$entry      = isset( $event['entry'] ) && is_array( $event['entry'] ) ? $event['entry'] : array();

		if ( ! isset( $hooks[ $event_type ] ) || empty( $entry ) ) {
			return;
		}

		do_action(
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- Hook name is picked from a fixed list of automator_dropbox_ hooks.
			$hooks[ $event_type ],
			$entry,
			(string) ( $event['folder_path'] ?? '' ),
			(string) ( $event['previous_path'] ?? '' )
		);
	}

	/**
	 * One kind of change with its count.
	 *
	 * @param string $type  The change's `event_type`.
	 * @param int    $count How many.
	 *
	 * @return string
	 */
	private static function count_label( string $type, int $count ): string {
		switch ( $type ) {
			case 'created':
				/* translators: %d: How many Dropbox items were created. */
				return sprintf( esc_html( _nx( '%d created', '%d created', $count, 'App event summary', 'uncanny-automator' ) ), $count );
			case 'updated':
				/* translators: %d: How many Dropbox files were updated. */
				return sprintf( esc_html( _nx( '%d updated', '%d updated', $count, 'App event summary', 'uncanny-automator' ) ), $count );
			case 'renamed':
				/* translators: %d: How many Dropbox items were renamed. */
				return sprintf( esc_html( _nx( '%d renamed', '%d renamed', $count, 'App event summary', 'uncanny-automator' ) ), $count );
			case 'moved':
				/* translators: %d: How many Dropbox items were moved. */
				return sprintf( esc_html( _nx( '%d moved', '%d moved', $count, 'App event summary', 'uncanny-automator' ) ), $count );
			case 'deleted':
				/* translators: %d: How many Dropbox items were deleted. */
				return sprintf( esc_html( _nx( '%d deleted', '%d deleted', $count, 'App event summary', 'uncanny-automator' ) ), $count );
		}

		return App_Event_Data::changes_label( $count );
	}

	/**
	 * The changed item's name: its own, or the last part of its path.
	 *
	 * @param array<string, mixed> $event One delivered change.
	 *
	 * @return string
	 */
	private static function item_name( array $event ): string {
		$entry = isset( $event['entry'] ) && is_array( $event['entry'] ) ? $event['entry'] : array();
		$name  = (string) ( $entry['name'] ?? '' );

		if ( '' === $name && isset( $entry['path_display'] ) ) {
			$name = wp_basename( (string) $entry['path_display'] );
		}

		return $name;
	}
}
