<?php
/**
 * Dropbox Event Fanout
 *
 * @package Uncanny_Automator
 * @author  Uncanny Owl
 */

namespace Uncanny_Automator\Integrations\Dropbox;

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
}
