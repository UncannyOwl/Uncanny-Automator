<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Recipe_Logs\App_Events\Services;

use Uncanny_Automator\App\Infrastructure\Connections\Connection_Registry;
use Uncanny_Automator\App\Infrastructure\Database\Database;
use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Platform_Inbox_Store;
use Uncanny_Automator\App\Integration_Catalog\App_Events\App_Event_Sources;

/**
 * Whether the Logs page has app events to offer: the App events tab shows
 * only on a site that has one to list, or an app that can send one.
 *
 * A site has app events when the inbox holds a `vendor_event` row, or when
 * an app that sends events has an active connection. The apps that send
 * events are the sources registered on the request
 * (`automator_app_event_sources()`): on the Logs page, that is every app
 * whose integration loaded, which a published recipe using the app makes
 * happen. An app whose triggers no published recipe uses is not sent events,
 * so its connection alone does not bring the tab.
 *
 * @since 8.1.0
 */
final class App_Events_Availability {

	/** @var Platform_Inbox_Store */
	private $inbox;

	/** @var App_Event_Sources */
	private $sources;

	/** @var Connection_Registry */
	private $connections;

	/**
	 * @param Platform_Inbox_Store|null $inbox       The inbox store.
	 * @param App_Event_Sources|null    $sources     The apps that send events.
	 * @param Connection_Registry|null  $connections The site's app connections.
	 */
	public function __construct(
		?Platform_Inbox_Store $inbox = null,
		?App_Event_Sources $sources = null,
		?Connection_Registry $connections = null
	) {
		$this->inbox       = $inbox ?? Database::get_platform_inbox_store();
		$this->sources     = $sources ?? automator_app_event_sources();
		$this->connections = $connections ?? new Connection_Registry();
	}

	/**
	 * Whether the site has app events, or an app connected that sends them.
	 *
	 * @since 8.1.0
	 *
	 * @return bool
	 */
	public function has_app_events(): bool {
		if ( $this->inbox->has_app_events() ) {
			return true;
		}

		foreach ( $this->sources->all() as $source ) {
			if ( array() !== $this->connections->list_for_integration( $source->get_platform_slug(), true ) ) {
				return true;
			}
		}

		return false;
	}
}
