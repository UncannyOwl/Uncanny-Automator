<?php
/**
 * Site-owner consent for read-only database access by connected AI apps.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Model_Context_Protocol\Tools;

final class Database_Access_Settings {

	public const OPTION_NAME = 'automator_mcp_database_access';

	public const TOOL_NAMES = array(
		'mysql_get_tables',
		'mysql_get_table_columns',
		'mysql_select_from_table',
		'mysql_get_cell_chunk',
	);

	/**
	 * Whether this site belongs to a WordPress network.
	 *
	 * @var bool
	 */
	private bool $is_network;

	/** @var bool Whether this is the network's main site. */
	private bool $is_main_site;

	/**
	 * Constructor.
	 *
	 * @param bool|null $is_network   Network flag override for tests; defaults to is_multisite().
	 * @param bool|null $is_main_site Main site flag override for tests; defaults to is_main_site().
	 */
	public function __construct( ?bool $is_network = null, ?bool $is_main_site = null ) {
		$this->is_network   = $is_network ?? is_multisite();
		$this->is_main_site = $is_main_site ?? is_main_site();
	}

	/**
	 * Whether a built-in tool requires the database consent in addition to its switch.
	 */
	public static function requires_consent( string $name ): bool {
		return in_array( $name, self::TOOL_NAMES, true );
	}

	/**
	 * Database access defaults off on network subsites and on elsewhere.
	 */
	public function is_enabled(): bool {
		if ( $this->is_network && ! $this->is_main_site ) {
			return 'yes' === get_option( self::OPTION_NAME, 'no' );
		}

		return 'no' !== get_option( self::OPTION_NAME, 'yes' );
	}

	/**
	 * Whether only a network administrator may turn database access back on here.
	 *
	 * The database tools read the shared schema of the whole network. Each
	 * site is limited to its own tables, but the decision to expose any of
	 * them to AI apps belongs to whoever administers the network.
	 */
	public function requires_network_administrator(): bool {
		return $this->is_network;
	}

	/**
	 * Subsite database access needs network approval for every caller.
	 *
	 * @since 8.0.0
	 * @return bool
	 */
	public function applies_to_first_party(): bool {
		return $this->is_network && ! $this->is_main_site;
	}

	/**
	 * Whether the current user may turn database access back on for this site.
	 */
	public function current_user_can_grant(): bool {
		return ! $this->requires_network_administrator() || is_super_admin();
	}

	/**
	 * Save the owner's choice after the administrator's request is validated.
	 *
	 * Turning it off is always allowed. Turning it back on, on a network,
	 * requires a super admin and returns false otherwise, without touching
	 * the option.
	 *
	 * @return bool Whether the requested state was saved or already in effect.
	 */
	public function set_enabled( bool $enabled ): bool {
		if ( $enabled && ! $this->current_user_can_grant() ) {
			return false;
		}

		$value = $enabled ? 'yes' : 'no';

		return get_option( self::OPTION_NAME, null ) === $value
			|| update_option( self::OPTION_NAME, $value, false );
	}
}
