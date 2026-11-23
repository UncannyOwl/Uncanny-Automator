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
use Uncanny_Automator\App\Transports\Model_Context_Protocol\Tools\Database_Column_Redaction_Settings;
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
	 * Values larger than this, in bytes, are cut by MySQL. Smaller ones are read whole, as before (QA D102).
	 */
	private const SLICED_READ_BYTES = 1048576;



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

		if ( Database_Secrets::is_secret_column( $target_column ) ) {
			return Json_Rpc_Response::create_error_response( 'target_column is restricted and cannot be retrieved. Password hashes, post passwords, activation keys and WooCommerce API keys are never returned.' );
		}

		// Filtering on a secret leaks whether a guessed value matches, even when
		// the returned target column is harmless. Match the SELECT predicate policy.
		if ( Database_Secrets::is_secret_column( $key_column ) ) {
			return Json_Rpc_Response::create_error_response( 'key_column is restricted and cannot be used to select a row. Use the table\'s ID or name column.' );
		}

		// The owner's table column redaction (see Database_Column_Redaction_Settings).
		$redaction = new Database_Column_Redaction_Settings();
		if ( $redaction->is_redacted( $target_column, array( $table ) ) ) {
			return Json_Rpc_Response::create_error_response( 'target_column is redacted on this site (Table column redaction) and cannot be retrieved.' );
		}
		if ( $redaction->is_redacted( $key_column, array( $table ) ) ) {
			return Json_Rpc_Response::create_error_response( 'key_column is redacted on this site (Table column redaction) and cannot be used to select a row. Use the table\'s ID or name column.' );
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
					'Column not found. key_column=%s target_column=%s table=%s. Use database column names from list_columns, not a SELECT alias such as body for post_content.',
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

		if ( $this->is_non_numeric_string( $key_value ) && $this->is_numeric_column_type( $column_types[ $key_column ] ) ) {
			return Json_Rpc_Response::create_error_response( sprintf( 'key_value must be numeric for key_column %s.', $key_column ) );
		}
		list( $key_placeholder, $prepared_key_value ) = $this->resolve_key_binding( $key_value );

		// A stored setting is a credential or not by its row's name, so read the name with it.
		$name_column = Database_Secrets::name_column_for( $target_column );
		$name_select = null !== $name_column && isset( $column_types[ $name_column ] ) ? ", `{$name_column}` AS setting_name" : '';
		$prefixes    = $redaction->setting_prefixes( array( $table ) );

		// Finding a row by a setting's value would confirm a guessed secret, so redacted rows
		// are left out of that lookup and read as not found.
		$key_name_column = Database_Secrets::name_column_for( $key_column );
		$key_exclusion   = null !== $key_name_column && isset( $column_types[ $key_name_column ] ) ? ' AND ' . Database_Secrets::sql_not_secret_setting( '`' . $key_name_column . '`', $prefixes ) : '';

		// Key formats are masked at their own length, so an offset means the same place in the
		// stored value here and in a read_rows preview. A value up to SLICED_READ_BYTES is read
		// whole. A larger one is cut by MySQL with a margin around the page, so PHP never holds
		// all of it, and the page is masked exactly as the whole value would be (QA D102).
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- Table/column identifiers are sanitized and validated against information_schema; dynamic placeholders are still prepared.
		$table_ref = $this->qualify_table_name( $table );
		$column    = "`{$target_column}`";
		$where     = "WHERE `{$key_column}` = {$key_placeholder}{$key_exclusion}";
		$sql       = "SELECT LENGTH({$column}) AS value_bytes, CHAR_LENGTH({$column}) AS total_chars{$name_select} FROM {$table_ref} {$where} LIMIT 2";
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Live row read for the table browser, must not be cached. The table and column identifiers are sanitized and checked against information_schema; every value is a prepared placeholder.
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, array( $prepared_key_value ) ), ARRAY_A );
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared

		if ( ! empty( $wpdb->last_error ) ) {
			return Json_Rpc_Response::create_error_response( 'Query failed: ' . $wpdb->last_error );
		}
		if ( empty( $rows ) ) {
			return Json_Rpc_Response::create_error_response( 'Row not found for the provided key_column/key_value.' );
		}
		if ( count( $rows ) > 1 ) {
			return Json_Rpc_Response::create_error_response( 'The supplied key matches more than one row. Use a key column and value that identify one row, such as a primary key.' );
		}
		$row = $rows[0];

		if ( '' !== $name_select && Database_Secrets::is_secret_setting( $row['setting_name'] ?? '' ) ) {
			return Json_Rpc_Response::create_error_response( 'This value is stored under a credential name and is never returned. Tell the user it is withheld; do not try to read it another way.' );
		}
		if ( '' !== $name_select && Database_Secrets::is_secret_setting( $row['setting_name'] ?? '', $prefixes ) ) {
			return Json_Rpc_Response::create_error_response( 'This setting is redacted on this site (Table column redaction) and its value cannot be retrieved.' );
		}

		$string      = null;
		$total_chars = (int) $row['total_chars'];
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Same identifiers and placeholders as the query above; the key placeholder is inside $where.
		if ( null !== $row['value_bytes'] && $offset >= $total_chars ) {
			$string = '';
		} elseif ( (int) $row['value_bytes'] > self::SLICED_READ_BYTES ) {
			// The window holds the longest key after the page, so a key that starts in the page is whole,
			// and text before the page to find a point outside every key. Most pages need little of it;
			// a page that follows an unclosed key header needs more, so look further back once.
			$margin = Database_Secrets::MAX_FORMAT_BYTES;
			foreach ( array( 2, 8 ) as $reach ) {
				$from  = max( 0, $offset - $reach * $margin );
				$slice = $wpdb->get_row( $wpdb->prepare( "SELECT CHAR_LENGTH({$column}) AS total_chars, SUBSTRING({$column}, %d, %d) AS value_window FROM {$table_ref} {$where} LIMIT 1", array( $from + 1, ( $offset - $from ) + $length + $margin, $prepared_key_value ) ), ARRAY_A );
				if ( null === ( $slice['total_chars'] ?? null ) ) {
					break;
				}
				$total_chars = (int) $slice['total_chars'];
				if ( $offset >= $total_chars ) {
					$string = '';
					break;
				}
				$window = Database_Secrets::mask_window( (string) $slice['value_window'], 0 === $from, $offset - $from );
				if ( null !== $window ) {
					$string = (string) mb_substr( $window, $offset - $from, $length, 'UTF-8' );
					break;
				}
			}
		} elseif ( null !== $row['value_bytes'] ) {
			// Enforce the byte limit in this query too, since the value could have grown.
			$value = $wpdb->get_row( $wpdb->prepare( "SELECT {$column} AS full_value FROM {$table_ref} {$where} AND LENGTH({$column}) <= %d LIMIT 1", array( $prepared_key_value, self::SLICED_READ_BYTES ) ), ARRAY_A );
			if ( null !== ( $value['full_value'] ?? null ) ) {
				$full        = (string) Database_Secrets::redact_cell( (string) $value['full_value'] );
				$total_chars = (int) mb_strlen( $full, 'UTF-8' );
				$string      = (string) mb_substr( $full, $offset, $length, 'UTF-8' );
			}
		}
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		if ( ! empty( $wpdb->last_error ) ) {
			return Json_Rpc_Response::create_error_response( 'Query failed: ' . $wpdb->last_error );
		}
		if ( null !== $row['value_bytes'] && null === $string ) {
			return Json_Rpc_Response::create_error_response( 'This page could not be read with credential masking within the cell read limit.' );
		}

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

			return Json_Rpc_Response::create_success_response( 0 === $total_chars ? 'Cell value is empty' : 'Offset is beyond the end of value', $payload );
		}

		// WordPress supplies mb_strlen() and mb_substr() where mbstring is missing.
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
