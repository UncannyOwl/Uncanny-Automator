<?php
declare(strict_types=1);

namespace Uncanny_Automator\Api\Events;

use Uncanny_Automator\Api\Events\Dtos\Event_Dto;

/**
 * Central dispatcher for API events.
 *
 * Event consumers receive a single DTO payload so transport layers and
 * extensions can share one stable event shape.
 */
final class Dispatcher {

	/**
	 * Dispatch an API event.
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
