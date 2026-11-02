<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Infrastructure\Database\Interfaces;

use Uncanny_Automator\App\Infrastructure\Exceptions\Store_Exception;

/**
 * A log table whose stored error messages can be walked by ID and rewritten in place.
 *
 * One implementation per table: the store that owns the table. A caller walks the table a bounded
 * window at a time with scan_error_messages(), reads a row it wants to change with
 * find_error_message(), and writes it back with replace_error_message(), which refuses when the row
 * no longer holds the text the caller read.
 *
 * @since 8.0.0
 */
interface Error_Message_Rewrite_Store {

	/**
	 * The next rows after an ID, in ID order, and whether each one's error message contains the fragments.
	 *
	 * Reads at most `$rows` rows, so the cost of one call does not grow with the table.
	 *
	 * @since 8.0.0
	 *
	 * @param int      $after_id  Rows with a greater ID are read; 0 starts at the first row.
	 * @param int      $rows      How many rows to read, at least 1.
	 * @param string[] $fragments Literal text the message must contain, in this order, at least one.
	 *
	 * @return array<int, bool> Row ID => whether its error message contains every fragment in order. Empty when no row follows `$after_id`.
	 *
	 * @throws \InvalidArgumentException When `$rows` is below 1 or `$fragments` is empty.
	 * @throws Store_Exception When the query fails.
	 */
	public function scan_error_messages( int $after_id, int $rows, array $fragments ): array;

	/**
	 * A row's error message.
	 *
	 * @since 8.0.0
	 *
	 * @param int $id Row ID.
	 *
	 * @return string|null The message, '' when the row has none, null when no row has that ID.
	 *
	 * @throws Store_Exception When the query fails.
	 */
	public function find_error_message( int $id ): ?string;

	/**
	 * Replace a row's error message, only while it still holds the text the caller read.
	 *
	 * @since 8.0.0
	 *
	 * @param int    $id          Row ID.
	 * @param string $expected    The message as the caller read it.
	 * @param string $replacement The new message.
	 *
	 * @return bool True when the row was rewritten; false when it is gone or its message has changed since.
	 *
	 * @throws Store_Exception When the query fails.
	 */
	public function replace_error_message( int $id, string $expected, string $replacement ): bool;
}
