<?php
/**
 * The "core" command group.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Command errors are plain terminal text for AI clients, never rendered as HTML.

namespace Uncanny_Automator\App\Site_Commands\Infrastructure\Commands;

use Uncanny_Automator\App\Site_Commands\Domain\Command_Definition;
use Uncanny_Automator\App\Site_Commands\Domain\Command_Error;
use Uncanny_Automator\App\Site_Commands\Domain\Command_Option;
use Uncanny_Automator\App\Site_Commands\Domain\Output\Command_Output;
use Uncanny_Automator\App\Site_Commands\Ports\Site_Command_Group;

/**
 * WordPress core facts.
 */
final class Core_Commands implements Site_Command_Group {

	/** The responses WordPress.org gives in an update check. */
	private const OFFER_RESPONSES = array( 'latest', 'upgrade', 'autoupdate', 'development' );

	/**
	 * The commands this group offers.
	 *
	 * @return Command_Definition[]
	 */
	public function commands(): array {
		return array(
			Command_Definition::reads( 'core version', 'Show the installed WordPress version.' )
				->with_option( Command_Option::flag( 'extra', 'Also show the database revision, TinyMCE version and package language.' ) )
				->handled_by( array( $this, 'version' ) ),
			Command_Definition::reads( 'core check-update', 'List newer WordPress versions from WordPress\'s last update check.' )
				->with_fields( array( 'version', 'update_type', 'package_url' ), array( 'version', 'update_type', 'package_url' ), 'Updates' )
				->handled_by( array( $this, 'check_update' ) ),
			Command_Definition::reads( 'core is-installed', 'Check whether WordPress is installed. Prints yes or no.' )
				->handled_by( array( $this, 'is_installed' ) ),
		);
	}

	/**
	 * The "core check-update" command. Reads the update_core transient; it
	 * does not contact WordPress.org, so it reports only what WordPress's last
	 * check found, and says so when that check is missing or out of date.
	 *
	 * @param array<string, string>      $arguments Unused.
	 * @param array<string, string|bool> $options   Validated options.
	 * @return Command_Output
	 *
	 * @throws Command_Error When the stored update check holds data WordPress.org never sends.
	 */
	public function check_update( array $arguments, array $options ): Command_Output {
		$installed = str_replace( '-src', '', $this->installed_version() );
		$transient = get_site_transient( 'update_core' );
		if ( ! is_object( $transient ) || ! is_array( $transient->updates ?? null ) || ! Values::whole_number( $transient->last_checked ?? null ) || ! is_string( $transient->version_checked ?? null ) ) {
			return Command_Output::text( 'WordPress has not completed an update check yet, so update information is not available.' );
		}
		$checked = str_replace( '-src', '', $transient->version_checked );
		if ( $checked !== $installed ) {
			return Command_Output::text( sprintf( 'WordPress\'s last update check was made for version %s, and %s is installed now. Update information will be current after WordPress checks again.', $checked, $installed ) );
		}

		// As in WP-CLI, keep the newest offer of each type. Every offer must be
		// one WordPress.org sends; anything else means the data was changed.
		$latest = false;
		$newest = array();
		foreach ( $transient->updates as $offer ) {
			$offer    = is_object( $offer ) ? get_object_vars( $offer ) : $offer;
			$response = is_array( $offer ) ? ( $offer['response'] ?? null ) : null;
			$version  = is_array( $offer ) ? Values::text( $offer['version'] ?? '' ) : '';
			if ( ! in_array( $response, self::OFFER_RESPONSES, true ) || 1 !== preg_match( '/^[0-9]+(\.[0-9]+)*(-[0-9A-Za-z.-]+)?$/', $version ) ) {
				throw new Command_Error( 'WordPress\'s last update check holds data that cannot be read, so updates cannot be reported. Another plugin may have changed it.' );
			}
			if ( 'latest' === $response ) {
				$latest = true;
				continue;
			}
			// Development builds are offered only to sites already running one.
			if ( 'development' === $response || 1 !== preg_match( '/^[0-9]+(\.[0-9]+)*$/', $version ) || ! version_compare( $version, $installed, '>' ) ) {
				continue;
			}
			$type = $this->major( $version ) === $this->major( $installed ) ? 'minor' : 'major';
			if ( isset( $newest[ $type ] ) && ! version_compare( $version, $newest[ $type ]['version'], '>' ) ) {
				continue;
			}
			$packages        = $offer['packages'] ?? null;
			$packages        = is_object( $packages ) ? get_object_vars( $packages ) : ( is_array( $packages ) ? $packages : array() );
			$newest[ $type ] = array(
				'version'     => $version,
				'update_type' => $type,
				'package_url' => Values::text( ! empty( $packages['partial'] ) ? $packages['partial'] : ( $packages['full'] ?? $offer['download'] ?? '' ) ),
			);
		}
		if ( array() === $newest && ! isset( $options['format'] ) && ! isset( $options['field'] ) && ! isset( $options['fields'] ) ) {
			return Command_Output::text( $latest ? 'Success: WordPress is at the latest version.' : 'WordPress\'s last update check offered no newer version.' );
		}

		return Command_Output::rows( array_values( array_filter( array( $newest['major'] ?? null, $newest['minor'] ?? null ) ) ) );
	}


	/**
	 * The "core is-installed" command.
	 *
	 * @param array<string, string>      $arguments Unused.
	 * @param array<string, string|bool> $options   Unused.
	 * @return Command_Output
	 */
	public function is_installed( array $arguments, array $options ): Command_Output {
		return Command_Output::text( is_blog_installed() ? 'yes' : 'no' );
	}

	/**
	 * The installed version, read from version.php.
	 *
	 * @return string
	 */
	private function installed_version(): string {
		$wp_version = '';
		require ABSPATH . WPINC . '/version.php';

		return (string) $wp_version;
	}

	/**
	 * Major version, such as "6.9" for "6.9.1".
	 *
	 * @param string $version Version.
	 * @return string
	 */
	private function major( string $version ): string {
		return implode( '.', array_slice( explode( '.', $version ), 0, 2 ) );
	}

	/**
	 * The "core version" command.
	 *
	 * Reads version.php like WP-CLI, because a plugin can overwrite the
	 * $wp_version global at runtime.
	 *
	 * @param array<string, string>      $arguments Unused.
	 * @param array<string, string|bool> $options   Validated options.
	 * @return Command_Output
	 */
	public function version( array $arguments, array $options ): Command_Output {
		$wp_version      = '';
		$wp_db_version   = 0;
		$tinymce_version = '';
		require ABSPATH . WPINC . '/version.php';

		if ( true !== ( $options['extra'] ?? false ) ) {
			return Command_Output::text( (string) $wp_version );
		}

		$language = empty( $wp_local_package ) ? 'en_US' : (string) $wp_local_package;
		$tinymce  = 1 === preg_match( '/(\d)(\d+)-/', (string) $tinymce_version, $match )
			? $match[1] . '.' . $match[2] . ' (' . $tinymce_version . ')'
			: (string) $tinymce_version;

		return Command_Output::text(
			implode(
				"\n",
				array(
					'WordPress version: ' . $wp_version,
					'Database revision: ' . $wp_db_version,
					'TinyMCE version:   ' . $tinymce,
					'Package language:  ' . $language,
				)
			)
		);
	}
}
