<?php
/**
 * MySQL Get Cell Chunk Tool.
 *
 * Retrieves one column value in bounded chunks for large-text debugging.
 *
 * @package Uncanny_Automator
 * @since 7.2.4
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Model_Context_Protocol\Tools\Catalog;

use Uncanny_Automator\App\Recipe_Builder\Shared\User\Value_Objects\User_Context;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\Json_Rpc_Response;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\Tools\Abstract_MCP_Tool;

/**
 * MySQL Get Cell Chunk Tool.
 */
class Mysql_Get_Cell_Chunk_Tool extends Abstract_MCP_Tool {

	use Information_Schema_Query;

	/**
	 * Maximum chunk size.
	 *
	 * @var int
	 */
	private const MAX_CHUNK_CHARS = 32496;

	/**
	 * Sensitive columns that must never be returned.
	 *
	 * @var string[]
	 */
	private const REDACTED_COLUMNS = array(
		'user_pass',
		'user_activation_key',
	);

	/**
	 * {@inheritDoc}
	 */
	public function get_name() {
		return 'mysql_get_cell_chunk';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_description() {
		return 'Get a bounded text chunk from one database cell. '
			. 'Use this only when mysql_select_from_table preview truncates a long value. '
			. 'Provide table, key_column, key_value, and target_column to identify the row and column. '
			. 'Then page through the value using character offset and length. Binary columns are not supported. '
			. 'Restricted authentication columns cannot be used as either the row key or the target.';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function schema_definition() {
		return array(
			'type'       => 'object',
			'properties' => array(
				'table'         => array(
					'type'        => 'string',
					'description' => 'Table name (no alias), e.g. "wp_options".',
				),
				'key_column'    => array(
					'type'        => 'string',
					'description' => 'Column used to find the row, e.g. "option_name" or "ID".',
				),
				'key_value'     => array(
					'type'        => array( 'string', 'integer', 'number' ),
					'description' => 'Row key value matched against key_column.',
				),
				'target_column' => array(
					'type'        => 'string',
					'description' => 'Column whose value should be chunked.',
				),
				'offset'        => array(
					'type'        => 'integer',
					'description' => 'Character offset to start from.',
					'minimum'     => 0,
					'default'     => 0,
				),
				'length'        => array(
					'type'        => 'integer',
					'description' => 'Chunk length in characters (max 32496).',
					'minimum'     => 1,
					'maximum'     => self::MAX_CHUNK_CHARS,
					'default'     => 8000,
				),
			),
			'required'   => array( 'table', 'key_column', 'key_value', 'target_column' ),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	protected function output_schema_definition(): ?array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'table'          => array( 'type' => 'string' ),
				'key_column'     => array( 'type' => 'string' ),
				'key_value'      => array( 'type' => array( 'string', 'integer', 'number' ) ),
				'target_column'  => array( 'type' => 'string' ),
				'value_chunk'    => array( 'type' => array( 'string', 'null' ) ),
				'total_chars'    => array( 'type' => 'integer' ),
				'offset'         => array( 'type' => 'integer' ),
				'returned_chars' => array( 'type' => 'integer' ),
				'has_more'       => array( 'type' => 'boolean' ),
				'next_offset'    => array( 'type' => array( 'integer', 'null' ) ),
			),
			'required'   => array(
				'table',
				'key_column',
				'key_value',
				'target_column',
				'value_chunk',
				'total_chars',
				'offset',
				'returned_chars',
				'has_more',
				'next_offset',
			),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	protected function execute_tool( User_Context $user_context, array $params ) {
		global $wpdb;

		$table         = $this->sanitize_identifier( (string) ( $params['table'] ?? '' ) );
		$key_column    = $this->sanitize_identifier( (string) ( $params['key_column'] ?? '' ) );
		$key_value     = $params['key_value'];
		$target_column = $this->sanitize_identifier( (string) ( $params['target_column'] ?? '' ) );
		$offset        = max( 0, (int) ( $params['offset'] ?? 0 ) );
		$length        = max( 1, min( (int) ( $params['length'] ?? 8000 ), self::MAX_CHUNK_CHARS ) );

		if ( '' === $table || '' === $key_column || '' === $target_column ) {
			return Json_Rpc_Response::create_error_response( 'table, key_column, and target_column must be valid SQL identifiers.' );
		}

		if ( in_array( strtolower( $target_column ), self::REDACTED_COLUMNS, true ) ) {
			return Json_Rpc_Response::create_error_response( 'target_column is restricted and cannot be retrieved.' );
		}

		// Filtering on a secret leaks whether a guessed value matches, even when
		// the returned target column is harmless. Match the SELECT predicate policy.
		if ( in_array( strtolower( $key_column ), self::REDACTED_COLUMNS, true ) ) {
			return Json_Rpc_Response::create_error_response( 'key_column is restricted and cannot be used to select a row.' );
		}

		$boundary = $this->get_table_boundary_response( array( $table ), $user_context );
		if ( null !== $boundary ) {
			return $boundary;
		}

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared -- The query string is prepared inside prepare_information_schema_query().
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Live information_schema read for the table browser, must not be cached. The query is prepared inside prepare_information_schema_query().
		$table_check = $wpdb->get_var(
			$this->prepare_information_schema_query(
				'SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = %s AND TABLE_NAME IN ({table_placeholders})',
				array( $table )
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared
		$failure = $this->get_query_failure_response();
		if ( null !== $failure ) {
			return $failure;
		}
		if ( empty( $table_check ) ) {
			return Json_Rpc_Response::create_error_response( sprintf( 'Table not found: %s', $table ) );
		}

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared -- The query string is prepared inside prepare_information_schema_query().
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Live information_schema read for the table browser, must not be cached. The query is prepared inside prepare_information_schema_query().
		$columns = $wpdb->get_results(
			$this->prepare_information_schema_query(
				'SELECT COLUMN_NAME, DATA_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME IN ({table_placeholders})',
				array( $table )
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared
		$failure = $this->get_query_failure_response();
		if ( null !== $failure ) {
			return $failure;
		}
		$column_types = wp_list_pluck( (array) $columns, 'DATA_TYPE', 'COLUMN_NAME' );
		if ( ! isset( $column_types[ $key_column ], $column_types[ $target_column ] ) ) {
			return Json_Rpc_Response::create_error_response(
				sprintf(
					'Column not found. key_column=%s target_column=%s table=%s',
					$key_column,
					$target_column,
					$table
				)
			);
		}

		// Binary SUBSTRING/CHAR_LENGTH count bytes, while this contract pages
		// text characters. A byte slice can split UTF-8 or be invalid JSON text;
		// refuse binary storage rather than return overlapping or corrupt chunks.
		$binary_types = array( 'binary', 'varbinary', 'tinyblob', 'blob', 'mediumblob', 'longblob', 'bit', 'geometry', 'point', 'linestring', 'polygon', 'multipoint', 'multilinestring', 'multipolygon', 'geometrycollection' );
		if ( in_array( strtolower( $column_types[ $target_column ] ), $binary_types, true ) ) {
			return Json_Rpc_Response::create_error_response( 'target_column uses binary storage; this tool only supports text character chunks.' );
		}

		list( $key_placeholder, $prepared_key_value ) = $this->resolve_key_binding( $key_value );

		$start_position = $offset + 1; // MySQL SUBSTRING() positions are 1-based.

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- Table/column identifiers are sanitized and validated against information_schema; dynamic placeholders are still prepared.
		$table_ref = $this->qualify_table_name( $table );
		$sql       = "SELECT CHAR_LENGTH(`{$target_column}`) AS total_chars, SUBSTRING(`{$target_column}`, %d, %d) AS value_chunk FROM {$table_ref} WHERE `{$key_column}` = {$key_placeholder} LIMIT 1";
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Live row read for the table browser, must not be cached. The table and column identifiers are sanitized and checked against information_schema; every value is a prepared placeholder.
		$row       = $wpdb->get_row(
			$wpdb->prepare(
				$sql,
				array(
					$start_position,
					$length,
					$prepared_key_value,
				)
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared

		if ( null === $row ) {
			if ( ! empty( $wpdb->last_error ) ) {
				return Json_Rpc_Response::create_error_response( 'Query failed: ' . $wpdb->last_error );
			}
			return Json_Rpc_Response::create_error_response( 'Row not found for the provided key_column/key_value.' );
		}

		$chunk       = $row['value_chunk'] ?? null;
		$string      = null === $chunk ? null : (string) $chunk;
		$total_chars = null === $row['total_chars'] ? 0 : (int) $row['total_chars'];

		if ( null === $string ) {
			$payload = array(
				'table'          => $table,
				'key_column'     => $key_column,
				'key_value'      => $key_value,
				'target_column'  => $target_column,
				'value_chunk'    => null,
				'total_chars'    => 0,
				'offset'         => $offset,
				'returned_chars' => 0,
				'has_more'       => false,
				'next_offset'    => null,
			);

			return Json_Rpc_Response::create_success_response( 'Cell value is null', $payload );
		}

		if ( $offset >= $total_chars ) {
			$payload = array(
				'table'          => $table,
				'key_column'     => $key_column,
				'key_value'      => $key_value,
				'target_column'  => $target_column,
				'value_chunk'    => '',
				'total_chars'    => $total_chars,
				'offset'         => $offset,
				'returned_chars' => 0,
				'has_more'       => false,
				'next_offset'    => null,
			);

			return Json_Rpc_Response::create_success_response( 'Offset is beyond the end of value', $payload );
		}

		// Use the database's character units for both SQL slicing and pagination,
		// including PHP installations without mbstring.
		$returned    = min( $length, $total_chars - $offset );
		$next_offset = $offset + $returned;
		$has_more    = $next_offset < $total_chars;

		$payload = array(
			'table'          => $table,
			'key_column'     => $key_column,
			'key_value'      => $key_value,
			'target_column'  => $target_column,
			'value_chunk'    => $string,
			'total_chars'    => $total_chars,
			'offset'         => $offset,
			'returned_chars' => $returned,
			'has_more'       => $has_more,
			'next_offset'    => $has_more ? $next_offset : null,
		);

		return Json_Rpc_Response::create_success_response(
			sprintf( 'Retrieved chunk (%d chars)', $returned ),
			$payload
		);
	}

	/**
	 * Sanitize SQL identifier.
	 *
	 * @since 7.2.4
	 *
	 * @param string $identifier Raw identifier.
	 * @return string
	 */
	private function sanitize_identifier( string $identifier ): string {
		$clean = preg_replace( '/[^a-zA-Z0-9_]/', '', trim( $identifier ) );
		return is_string( $clean ) ? $clean : '';
	}

	/**
	 * Resolve SQL placeholder/value pair for key binding.
	 *
	 * Uses strict runtime type checks:
	 * - int   => %d
	 * - float => %f
	 * - other => %s
	 *
	 * @since 7.2.4
	 *
	 * @param mixed $key_value Row key value.
	 * @return array{0:string,1:string|int|float}
	 */
	private function resolve_key_binding( $key_value ): array {
		if ( is_int( $key_value ) ) {
			return array( '%d', $key_value );
		}

		if ( is_float( $key_value ) ) {
			return array( '%f', $key_value );
		}

		return array( '%s', (string) $key_value );
	}
}
