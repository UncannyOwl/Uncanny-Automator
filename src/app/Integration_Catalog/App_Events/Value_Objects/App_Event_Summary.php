<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Integration_Catalog\App_Events\Value_Objects;

/**
 * What one delivery of an app's events says: a line such as
 * "2 created, 1 deleted", how many changes it held, and the names of the
 * first items it changed.
 *
 * @since 8.1.0
 */
final class App_Event_Summary {

	/**
	 * Most item names a summary keeps.
	 *
	 * @var int
	 */
	const MAX_ITEMS = 5;

	/**
	 * @var string
	 */
	private $summary;

	/**
	 * @var int
	 */
	private $changes;

	/**
	 * @var string[]
	 */
	private $items;

	/**
	 * @param string   $summary The line that says what changed.
	 * @param int      $changes How many changes the delivery held.
	 * @param string[] $items   Names of the items it changed, first ones first.
	 */
	public function __construct( string $summary, int $changes, array $items = array() ) {
		$names = array();

		foreach ( $items as $item ) {
			$name = is_scalar( $item ) ? trim( (string) $item ) : '';

			if ( '' !== $name ) {
				$names[] = $name;
			}
		}

		$this->summary = $summary;
		$this->changes = max( 0, $changes );
		$this->items   = array_slice( $names, 0, self::MAX_ITEMS );
	}

	/**
	 * @return string The line that says what changed.
	 */
	public function get_summary(): string {
		return $this->summary;
	}

	/**
	 * @return int How many changes the delivery held.
	 */
	public function get_changes(): int {
		return $this->changes;
	}

	/**
	 * @return string[] Up to five item names.
	 */
	public function get_items(): array {
		return $this->items;
	}
}
