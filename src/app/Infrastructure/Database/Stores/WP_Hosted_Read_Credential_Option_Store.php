<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Infrastructure\Database\Stores;

use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Hosted_Read_Credential_Option_Store;

/**
 * Reads the credential option past the WordPress option cache.
 *
 * @since 8.0.0
 */
final class WP_Hosted_Read_Credential_Option_Store implements Hosted_Read_Credential_Option_Store {

	/** @var \wpdb */
	private $wpdb;

	/**
	 * @param \wpdb $wpdb WordPress database adapter.
	 */
	public function __construct( $wpdb ) {
		$this->wpdb = $wpdb;
	}

	/**
	 * @return array|null An empty array means the option is absent; null means an unproven read.
	 */
	public function read_confirmed(): ?array {
		$wpdb = $this->wpdb;
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$query = $wpdb->prepare( "SELECT option_value FROM `{$wpdb->options}` WHERE option_name = %s", 'automator_mcp_hosted_read_credentials_v1' );
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- A cleanup acknowledgement needs a fresh, prepared read.
		$row = $this->wpdb->get_row( $query, ARRAY_A );
		if ( '' !== $this->wpdb->last_error ) {
			return null;
		}
		if ( null === $row ) {
			return array();
		}
		if ( ! is_array( $row ) || ! is_string( $row['option_value'] ?? null ) ) {
			return null;
		}

		$value = automator_safe_unserialize( $row['option_value'] );
		return is_array( $value ) ? $value : null;
	}
}
