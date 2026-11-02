<?php
/**
 * Small, shared operations on item rows.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Site_Commands\Domain\Output;

/**
 * Filtering and sorting shared by list commands, so each group does not
 * repeat them.
 */
final class Rows {

	/**
	 * Keep rows whose field equals the value; a null value keeps every row.
	 *
	 * @param array<int, array<string, mixed>> $rows  Rows.
	 * @param string                           $field Field.
	 * @param string|null                      $value Wanted value.
	 * @return array<int, array<string, mixed>>
	 */
	public static function where( array $rows, string $field, ?string $value ): array {
		if ( null === $value ) {
			return array_values( $rows );
		}

		return array_values(
			array_filter(
				$rows,
				static function ( array $row ) use ( $field, $value ): bool {
					return (string) ( $row[ $field ] ?? '' ) === $value;
				}
			)
		);
	}

	/**
	 * Sort rows by a field in natural, case-insensitive order, as WordPress lists them.
	 *
	 * @param array<int, array<string, mixed>> $rows  Rows.
	 * @param string                           $field Field.
	 * @return array<int, array<string, mixed>>
	 */
	public static function sort_by( array $rows, string $field ): array {
		usort(
			$rows,
			static function ( array $a, array $b ) use ( $field ): int {
				return strnatcasecmp( (string) ( $a[ $field ] ?? '' ), (string) ( $b[ $field ] ?? '' ) );
			}
		);

		return $rows;
	}
}
