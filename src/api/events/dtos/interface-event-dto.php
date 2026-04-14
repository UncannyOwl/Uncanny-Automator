<?php
declare(strict_types=1);

namespace Uncanny_Automator\Api\Events\Dtos;

/**
 * Contract for event DTO payloads.
 */
interface Event_Dto {

	/**
	 * Convert the event payload to an array.
	 *
	 * @return array<string,mixed>
	 */
	public function to_array(): array;
}
