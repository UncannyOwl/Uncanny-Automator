<?php
/**
 * The site owner's switch for site management by connected AI apps.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Model_Context_Protocol\Tools;

/**
 * The site_manage tool installs, updates and switches plugins and themes,
 * and changes other site settings. It is on by default, for every caller,
 * Uncanny Agent included, and off once the site owner turns it off. AI apps
 * cannot change the switch themselves; see Mcp_Settings_Guard. On a network
 * those changes affect every site, so only a super administrator may turn it
 * back on.
 */
final class Site_Management_Settings {

	public const OPTION_NAME = 'automator_mcp_site_management';

	public const TOOL_NAME = 'site_manage';

	/**
	 * Whether this site belongs to a WordPress network.
	 *
	 * @var bool
	 */
	private bool $is_network;

	/**
	 * Whether the current user is a super administrator.
	 *
	 * @var callable(): bool
	 */
	private $is_super_admin;

	/**
	 * Constructor.
	 *
	 * @param bool|null             $is_network     Network flag override for tests; defaults to is_multisite().
	 * @param callable(): bool|null $is_super_admin Super administrator check; defaults to is_super_admin().
	 */
	public function __construct( ?bool $is_network = null, ?callable $is_super_admin = null ) {
		$this->is_network     = $is_network ?? is_multisite();
		$this->is_super_admin = $is_super_admin ?? 'is_super_admin';
	}

	/**
	 * Whether a built-in tool is governed by this switch.
	 *
	 * @param string $name Tool name.
	 * @return bool
	 */
	public static function requires_consent( string $name ): bool {
		return self::TOOL_NAME === $name;
	}

	/**
	 * Whether site management is on: always, unless the owner turned it off.
	 *
	 * @return bool
	 */
	public function is_enabled(): bool {
		return 'no' !== get_option( self::OPTION_NAME, 'yes' );
	}

	/**
	 * Whether the current user may turn site management back on.
	 *
	 * @return bool
	 */
	public function current_user_can_grant(): bool {
		return ! $this->is_network || true === (bool) call_user_func( $this->is_super_admin );
	}

	/**
	 * Save the owner's choice. Turning it off is always allowed; turning it
	 * back on, on a network, needs a super administrator and returns false
	 * otherwise.
	 *
	 * @param bool $enabled Whether to turn it on.
	 * @return bool Whether the requested state was saved or already in effect.
	 */
	public function set_enabled( bool $enabled ): bool {
		if ( $enabled && ! $this->current_user_can_grant() ) {
			return false;
		}
		$value = $enabled ? 'yes' : 'no';

		return get_option( self::OPTION_NAME, 'yes' ) === $value
			|| update_option( self::OPTION_NAME, $value, false );
	}
}
