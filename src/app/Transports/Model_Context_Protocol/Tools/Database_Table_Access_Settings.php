<?php
/**
 * Site-owner allowlist for additional database tables.
 *
 * @package Uncanny_Automator
 * @since 8.0.0
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Model_Context_Protocol\Tools;

use WP_Error;

final class Database_Table_Access_Settings {

	public const OPTION_NAME = 'automator_mcp_database_extra_tables';
	public const MAX_TABLES  = 100;

	/**
	 * The extra tables explicitly approved on a single-site installation.
	 *
	 * @since 8.0.0
	 * @return string[]
	 */
	public function tables(): array {
		if ( is_multisite() ) {
			return array();
		}

		$value = get_option( self::OPTION_NAME, array() );
		if ( ! is_array( $value ) || count( $value ) > self::MAX_TABLES ) {
			return array();
		}

		$tables = $this->validate( $value );
		return is_wp_error( $tables ) ? array() : $tables;
	}

	/**
	 * Save exact table names after the owner has reviewed the list.
	 *
	 * @since 8.0.0
	 * @param string[] $tables Requested names.
	 * @return true|WP_Error
	 */
	public function set_tables( array $tables ) {
		if ( is_multisite() || ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'mcp_database_tables_forbidden' );
		}

		$tables = $this->validate( $tables );
		if ( is_wp_error( $tables ) ) {
			return $tables;
		}

		if ( get_option( self::OPTION_NAME, null ) === $tables || update_option( self::OPTION_NAME, $tables, false ) ) {
			return true;
		}

		return new WP_Error( 'mcp_database_tables_not_saved' );
	}

	/**
	 * Refuse invalid lists as a whole instead of saving an unintended name.
	 *
	 * @since 8.0.0
	 * @param array $values Requested names.
	 * @return string[]|WP_Error
	 */
	private function validate( array $values ) {
		if ( count( $values ) > self::MAX_TABLES ) {
			return new WP_Error( 'mcp_database_tables_invalid' );
		}

		$tables = array();
		foreach ( $values as $value ) {
			if ( ! is_string( $value ) || 1 !== preg_match( '/^[A-Za-z0-9_]{1,64}$/D', $value ) ) {
				return new WP_Error( 'mcp_database_tables_invalid' );
			}
			$tables[] = $value;
		}

		return array_values( array_unique( $tables ) );
	}
}
