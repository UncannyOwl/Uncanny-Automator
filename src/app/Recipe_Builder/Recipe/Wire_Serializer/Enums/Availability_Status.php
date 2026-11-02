<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Recipe_Builder\Recipe\Wire_Serializer\Enums;

/**
 * Availability_Status Enum.
 *
 * Values of a node's wire `availability.status` (`Wire_Key::STATUS`), stamped
 * by `Recipe_Entity_Availability_Resolver` from the site's license and active
 * plugins.
 */
final class Availability_Status {

	/**
	 * The node runs on this site.
	 */
	const USABLE = 'usable';

	/**
	 * The node cannot run on this site; `availability.message` says why and what the run does with it.
	 */
	const UNAVAILABLE = 'unavailable';

	/**
	 * Every availability status, in declaration order.
	 *
	 * @return list<string>
	 */
	public static function get_all(): array {
		return array(
			self::USABLE,
			self::UNAVAILABLE,
		);
	}

	/**
	 * Whether `$status` is a known availability status.
	 *
	 * @param string $status Candidate status.
	 * @return bool True when the status is an availability status.
	 */
	public static function is_valid( string $status ): bool {
		return in_array( $status, self::get_all(), true );
	}
}
