<?php
/**
 * Information Schema Query Trait.
 *
 * Shared query builder for MySQL tools that introspect information_schema.
 *
 * @package Uncanny_Automator
 * @since 7.0.0
 */

declare(strict_types=1);

namespace Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog;

/**
 * Trait Information_Schema_Query.
 */
trait Information_Schema_Query {

	/**
	 * Prepare an information_schema query for one or more table names.
	 *
	 * @param string $query_template SQL template containing {table_placeholders}.
	 * @param array  $table_names    Sanitized table names.
	 * @return string
	 */
	private function prepare_information_schema_query( string $query_template, array $table_names ): string {
		global $wpdb;

		$table_placeholders = implode( ', ', array_fill( 0, count( $table_names ), '%s' ) );
		$query              = str_replace( '{table_placeholders}', $table_placeholders, $query_template );

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- The placeholder-expanded template is prepared before it is returned.
		return $wpdb->prepare(
			$query,
			array_merge( array( DB_NAME ), array_values( $table_names ) )
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
	}
}
