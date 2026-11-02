<?php
/**
 * Site-owner consent for reading the site's code and files, and the files
 * and folders AI apps may never open.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Model_Context_Protocol\Tools;

use Uncanny_Automator\App\Site_Commands\Domain\Files\Sandbox\Denylist;

/**
 * The file commands of site_command (ls, find, grep, cat and the others, and
 * core verify-checksums) are on unless the owner turned them off. On a
 * network, one choice covers every site, since they share the files. Files known
 * to hold credentials, such as wp-config.php, are never opened whatever this
 * says; the owner's entries add to that list and never shorten it. Trade-off:
 * other files open and only recognisable credentials are kept from output, so
 * these entries are how an owner protects a file no check could recognise.
 */
final class File_Reading_Settings {

	public const OPTION_NAME = 'automator_mcp_file_reading';

	public const EXCLUSIONS_OPTION_NAME = 'automator_mcp_file_reading_exclusions';

	/**
	 * Most entries the owner may add.
	 */
	public const MAX_EXCLUSIONS = 100;

	/**
	 * Whether this site belongs to a WordPress network.
	 *
	 * @var bool
	 */
	private bool $is_network;

	/**
	 * Constructor.
	 *
	 * @param bool|null $is_network Network flag override for tests; defaults to is_multisite().
	 */
	public function __construct( ?bool $is_network = null ) {
		$this->is_network = $is_network ?? is_multisite();
	}

	/**
	 * File reading is on unless the owner turned it off.
	 *
	 * @return bool
	 */
	public function is_enabled(): bool {
		return 'no' !== $this->read( self::OPTION_NAME, 'yes' );
	}

	/**
	 * Whether only a network administrator may turn file reading back on here.
	 *
	 * Every site of a network shares the same WordPress files, so the
	 * decision to let AI apps read them belongs to whoever administers the
	 * network.
	 *
	 * @return bool
	 */
	public function requires_network_administrator(): bool {
		return $this->is_network;
	}

	/**
	 * Whether the current user may turn file reading back on for this site.
	 *
	 * @return bool
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
	 * @param bool $enabled The requested state.
	 * @return bool Whether the requested state was saved or already in effect.
	 */
	public function set_enabled( bool $enabled ): bool {
		if ( $enabled && ! $this->current_user_can_grant() ) {
			return false;
		}

		$value = $enabled ? 'yes' : 'no';

		return $this->read( self::OPTION_NAME, 'yes' ) === $value
			|| $this->write( self::OPTION_NAME, $value );
	}

	/**
	 * The files and folders the owner added, as stored.
	 *
	 * @return string[]
	 */
	public function exclusions(): array {
		$stored = $this->read( self::EXCLUSIONS_OPTION_NAME, array() );
		if ( ! is_array( $stored ) ) {
			return array();
		}

		return self::normalize( $stored );
	}

	/**
	 * Save the files and folders AI apps may never open.
	 *
	 * Removing an entry lets AI apps open those files again, so on a network
	 * only a super admin may remove one; adding is always allowed.
	 *
	 * @param string[] $entries What the owner typed, one entry each.
	 * @return bool Whether the list was saved or already in effect.
	 */
	public function set_exclusions( array $entries ): bool {
		$entries = self::normalize( $entries );
		$current = $this->exclusions();
		if ( array() !== array_diff( $current, $entries ) && ! $this->current_user_can_grant() ) {
			return false;
		}
		if ( $current === $entries ) {
			return true;
		}

		return $this->write( self::EXCLUSIONS_OPTION_NAME, $entries );
	}

	/**
	 * A stored choice. On a network the choices live on the main site,
	 * because every site shares the files they protect.
	 *
	 * @param string $name     Option name.
	 * @param mixed  $fallback Value when unset.
	 * @return mixed
	 */
	private function read( string $name, $fallback ) {
		return is_multisite() ? get_blog_option( get_main_site_id(), $name, $fallback ) : get_option( $name, $fallback );
	}

	/**
	 * Store a choice where read() finds it, without autoloading it.
	 *
	 * @param string $name  Option name.
	 * @param mixed  $value Value.
	 * @return bool
	 */
	private function write( string $name, $value ): bool {
		if ( ! is_multisite() ) {
			return update_option( $name, $value, false );
		}
		switch_to_blog( get_main_site_id() );
		try {
			return update_option( $name, $value, false );
		} finally {
			restore_current_blog();
		}
	}

	/**
	 * Entries as stored: usable() up to MAX_EXCLUSIONS.
	 *
	 * @param array $entries Raw entries.
	 * @return string[]
	 */
	public static function normalize( array $entries ): array {
		return array_slice( self::usable( $entries ), 0, self::MAX_EXCLUSIONS );
	}

	/**
	 * Entries cleaned, without duplicates or malformed ones, in the owner's
	 * order, however many.
	 *
	 * @param array $entries Raw entries.
	 * @return string[]
	 */
	public static function usable( array $entries ): array {
		$clean = array();
		foreach ( $entries as $entry ) {
			$entry = is_string( $entry ) ? Denylist::owner_entry( $entry ) : null;
			if ( null !== $entry && ! in_array( $entry, $clean, true ) ) {
				$clean[] = $entry;
			}
		}

		return $clean;
	}

	/**
	 * Entries that would be dropped because they cannot be used, so the
	 * screen can say which.
	 *
	 * @param string[] $entries What the owner typed.
	 * @return string[]
	 */
	public static function rejected( array $entries ): array {
		$rejected = array();
		foreach ( $entries as $entry ) {
			if ( is_string( $entry ) && '' !== trim( $entry ) && null === Denylist::owner_entry( $entry ) ) {
				$rejected[] = trim( $entry );
			}
		}

		return $rejected;
	}
}
