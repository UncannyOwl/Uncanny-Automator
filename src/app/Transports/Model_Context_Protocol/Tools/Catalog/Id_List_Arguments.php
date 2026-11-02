<?php
/**
 * Include and exclude ID lists for the site readers.
 *
 * @package Uncanny_Automator
 * @since 8.0.0
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Model_Context_Protocol\Tools\Catalog;

/**
 * Turns a reader's comma-separated `include` and `exclude` IDs into query arguments.
 *
 * WordPress ignores excluded IDs whenever included IDs are set: WP_Query drops
 * `post__not_in` when `post__in` is given, and WP_User_Query and
 * WP_Term_Query drop `exclude` when `include` is given. An Agent that sends
 * both expects neither list ignored, so the excluded IDs are taken out of the
 * included ones before the query runs.
 *
 * @since 8.0.0
 */
trait Id_List_Arguments {

	/**
	 * The query arguments for the include and exclude lists.
	 *
	 * @param array  $params      Tool parameters, with optional `include` and `exclude` lists.
	 * @param string $include_key The query argument for included IDs, such as post__in.
	 * @param string $exclude_key The query argument for excluded IDs, such as post__not_in.
	 *
	 * @return array<string, int[]>|null The arguments to add, or null when every included ID is also excluded, so nothing can match.
	 */
	private function id_list_query_args( array $params, string $include_key, string $exclude_key ): ?array {
		$exclude = empty( $params['exclude'] ) ? array() : $this->parse_id_list( $params['exclude'] );

		if ( empty( $params['include'] ) ) {
			return empty( $exclude ) ? array() : array( $exclude_key => $exclude );
		}

		$include = array_values( array_diff( $this->parse_id_list( $params['include'] ), $exclude ) );

		return empty( $include ) ? null : array( $include_key => $include );
	}

	/**
	 * The IDs in a comma-separated list.
	 *
	 * @param mixed $value Comma-separated IDs.
	 *
	 * @return int[]
	 */
	private function parse_id_list( $value ): array {
		return array_map( 'intval', explode( ',', (string) $value ) );
	}
}
