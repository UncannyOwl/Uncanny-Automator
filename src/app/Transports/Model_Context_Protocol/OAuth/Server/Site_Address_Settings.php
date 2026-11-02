<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Model_Context_Protocol\OAuth\Server;

/**
 * Where this site's address can be changed.
 *
 * Settings › General shows the address fields only on a single site. On a
 * network, a network administrator changes a site's address under Network
 * Admin › Sites › Edit site. WordPress shows the main site's address there
 * read-only, so only the host can change it. Configuration constants can
 * also control the addresses regardless of site type.
 *
 * @since 8.0.0
 */
final class Site_Address_Settings {

	public const SETTINGS_GENERAL = 'settings_general';

	public const NETWORK_EDIT_SITE = 'network_edit_site';

	public const HOST = 'host';

	public const CONFIGURATION = 'configuration';

	/**
	 * Whether this site belongs to a WordPress network.
	 *
	 * @var bool
	 */
	private bool $is_network;

	/**
	 * Whether this site is its network's main site.
	 *
	 * @var bool
	 */
	private bool $is_main_site;

	/** @var bool */
	private bool $is_config_managed;

	/**
	 * Constructor.
	 *
	 * @param bool|null $is_network   Network flag override for tests; defaults to is_multisite().
	 * @param bool|null $is_main_site Main site flag override for tests; defaults to is_main_site().
	 * @param bool|null $is_config_managed Configuration flag override for tests.
	 */
	public function __construct( ?bool $is_network = null, ?bool $is_main_site = null, ?bool $is_config_managed = null ) {
		$this->is_network        = $is_network ?? is_multisite();
		$this->is_main_site      = $is_main_site ?? is_main_site();
		$this->is_config_managed = $is_config_managed ?? ( defined( 'WP_HOME' ) || defined( 'WP_SITEURL' ) );
	}

	/**
	 * Where this site's address can be changed: one of the class constants.
	 *
	 * @return string
	 */
	public function location(): string {
		if ( $this->is_config_managed ) {
			return self::CONFIGURATION;
		}

		if ( ! $this->is_network ) {
			return self::SETTINGS_GENERAL;
		}

		return $this->is_main_site ? self::HOST : self::NETWORK_EDIT_SITE;
	}
}
