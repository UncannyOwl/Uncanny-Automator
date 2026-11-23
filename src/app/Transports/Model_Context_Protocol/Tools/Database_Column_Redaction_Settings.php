<?php
/**
 * Site-owner list of database columns and stored settings the MCP database tools redact.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Model_Context_Protocol\Tools;

use Uncanny_Automator\App\Events\Dispatcher;
use WP_Error;

/**
 * Table column redaction.
 *
 * Automator withholds only credentials it knows for certain (see
 * Catalog\Database_Secrets). No one can predict which columns or settings every
 * other plugin uses for secrets or personal data, so the site owner, and any
 * extension, names them here. One rule per line:
 *
 * - `table:column` redacts that column in that table, e.g. `wp_myplugin_keys:secret`.
 * - `column` redacts a column of that name in every table, e.g. `api_token`.
 * - `table:prefix*` redacts, in a table that stores settings as name/value pairs
 *   (option_name/option_value or meta_key/meta_value), the value of every row whose
 *   name starts with the prefix, e.g. `wp_options:myplugin_*`. A full name with
 *   `*` redacts that one setting, e.g. `wp_options:ai1wm_secret_key*`.
 * - `prefix*` does the same in every such table.
 *
 * A redacted column can still be selected, and reads as [REDACTED]; its value
 * never leaves the database. It cannot be used to filter, join, sort, group or in a
 * value aggregate. A redacted setting keeps its name visible; its value reads as
 * [REDACTED], and queries that filter, sort, group or aggregate by the value column
 * leave the row out.
 *
 * Extensions add rules with the `automator_mcp_database_redacted_columns` filter,
 * which receives the owner's list and returns it with lines appended, in the same
 * syntax. The filter only adds: every line the owner saved applies whatever it
 * returns, and it never changes the saved list.
 *
 * @since 8.1.0
 */
final class Database_Column_Redaction_Settings {

	public const OPTION_NAME = 'automator_mcp_database_redacted_columns';
	public const FILTER      = 'automator_mcp_database_redacted_columns';
	public const MAX_RULES   = 200;

	/**
	 * One rule: an optional table, then a column, or a setting-name prefix ending in *.
	 *
	 * Table and column names are SQL identifiers. A prefix may also hold hyphens and
	 * dots, which WordPress setting names use.
	 */
	private const RULE_PATTERN = '/^(?:([A-Za-z0-9_]{1,64}):)?(?:([A-Za-z0-9_]{1,64})|([A-Za-z0-9_.-]{1,191})\*)$/D';

	/** The column pairs of tables that store settings as name/value rows. */
	private const SETTING_COLUMNS = array(
		'option_name' => 'option_value',
		'meta_key'    => 'meta_value',
	);

	/**
	 * The rules the site owner saved, in the order entered.
	 *
	 * @return string[]
	 */
	public function saved_rules(): array {
		$value = get_option( self::OPTION_NAME, array() );
		if ( ! is_array( $value ) ) {
			return array();
		}
		$rules = $this->validate( $value );
		return is_wp_error( $rules ) ? array() : $rules;
	}

	/**
	 * The rules in force: the owner's, plus any an extension adds.
	 *
	 * The owner's rules always apply, even if a filter returns a list without them. An
	 * extension's invalid entry is dropped rather than failing the owner's rules.
	 *
	 * @return array<int,array{table:string|null,column:string|null,prefix:string|null}> Lower-cased names.
	 */
	public function rules(): array {
		$saved   = $this->saved_rules();
		$added   = Dispatcher::filter( self::FILTER, $saved );
		$entries = array_merge( $saved, is_array( $added ) ? $added : array() );
		$rules   = array();
		foreach ( $entries as $entry ) {
			$rule = is_string( $entry ) ? $this->parse( $entry ) : null;
			if ( null !== $rule ) {
				$rules[] = $rule;
			}
		}
		return $rules;
	}

	/**
	 * Whether a column is redacted in any of the given tables.
	 *
	 * @param string   $column Column name, without a table qualifier.
	 * @param string[] $tables Tables the column may belong to.
	 * @return bool
	 */
	public function is_redacted( string $column, array $tables ): bool {
		$column = strtolower( $column );
		$tables = array_map( 'strtolower', $tables );
		foreach ( $this->rules() as $rule ) {
			if ( $rule['column'] === $column && ( null === $rule['table'] || in_array( $rule['table'], $tables, true ) ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * The setting-name prefixes redacted in any of the given tables.
	 *
	 * @param string[] $tables Tables a setting row may belong to.
	 * @return string[] Lower-cased prefixes.
	 */
	public function setting_prefixes( array $tables ): array {
		$tables   = array_map( 'strtolower', $tables );
		$prefixes = array();
		foreach ( $this->rules() as $rule ) {
			if ( null !== $rule['prefix'] && ( null === $rule['table'] || in_array( $rule['table'], $tables, true ) ) ) {
				$prefixes[] = $rule['prefix'];
			}
		}
		return array_values( array_unique( $prefixes ) );
	}

	/**
	 * Save the owner's rules after they reviewed the list.
	 *
	 * @param string[] $rules Requested rules.
	 * @return true|WP_Error
	 */
	public function set_rules( array $rules ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'mcp_database_redaction_forbidden' );
		}

		$rules = $this->validate( $rules );
		if ( is_wp_error( $rules ) ) {
			return $rules;
		}

		// A setting rule for a table that keeps no name/value rows would never match.
		foreach ( $rules as $rule ) {
			$parsed = $this->parse( $rule );
			if ( null !== $parsed && null !== $parsed['prefix'] && null !== $parsed['table'] && ! $this->stores_settings( $parsed['table'] ) ) {
				return new WP_Error( 'mcp_database_redaction_not_settings_table', '', array( 'rule' => $rule ) );
			}
		}

		if ( get_option( self::OPTION_NAME, null ) === $rules || update_option( self::OPTION_NAME, $rules, false ) ) {
			return true;
		}

		return new WP_Error( 'mcp_database_redaction_not_saved' );
	}

	/**
	 * @param string $entry One rule.
	 * @return array{table:string|null,column:string|null,prefix:string|null}|null
	 */
	private function parse( string $entry ): ?array {
		if ( 1 !== preg_match( self::RULE_PATTERN, trim( $entry ), $parts ) ) {
			return null;
		}
		return array(
			'table'  => '' !== $parts[1] ? strtolower( $parts[1] ) : null,
			'column' => '' !== ( $parts[2] ?? '' ) ? strtolower( $parts[2] ) : null,
			'prefix' => '' !== ( $parts[3] ?? '' ) ? strtolower( $parts[3] ) : null,
		);
	}

	/**
	 * Whether a table stores settings as name/value rows, judged by its columns.
	 *
	 * @param string $table Table name.
	 * @return bool
	 */
	private function stores_settings( string $table ): bool {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Live schema check while the owner saves the list.
		$columns = $wpdb->get_col( $wpdb->prepare( 'SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s', $table ) );
		$columns = array_map( 'strtolower', (array) $columns );
		foreach ( self::SETTING_COLUMNS as $name => $value ) {
			if ( in_array( $name, $columns, true ) && in_array( $value, $columns, true ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Refuse an invalid list as a whole instead of saving a rule the owner did not mean.
	 *
	 * @param array $values Requested rules.
	 * @return string[]|WP_Error
	 */
	private function validate( array $values ) {
		if ( count( $values ) > self::MAX_RULES ) {
			return new WP_Error( 'mcp_database_redaction_invalid' );
		}

		$rules = array();
		foreach ( $values as $value ) {
			if ( ! is_string( $value ) || null === $this->parse( $value ) ) {
				return new WP_Error( 'mcp_database_redaction_invalid', '', array( 'rule' => is_string( $value ) ? trim( $value ) : '' ) );
			}
			$rules[] = trim( $value );
		}

		return array_values( array_unique( $rules ) );
	}
}
