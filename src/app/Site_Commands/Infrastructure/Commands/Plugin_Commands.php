<?php
/**
 * The "plugin" command group.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Command errors are plain terminal text for AI clients, never rendered as HTML.

namespace Uncanny_Automator\App\Site_Commands\Infrastructure\Commands;

use Uncanny_Automator\App\Events\Dispatcher;
use Uncanny_Automator\App\Site_Commands\Domain\Command_Argument;
use Uncanny_Automator\App\Site_Commands\Domain\Command_Definition;
use Uncanny_Automator\App\Site_Commands\Domain\Command_Error;
use Uncanny_Automator\App\Site_Commands\Domain\Command_Option;
use Uncanny_Automator\App\Site_Commands\Domain\Output\Command_Output;
use Uncanny_Automator\App\Site_Commands\Domain\Output\Rows;
use Uncanny_Automator\App\Site_Commands\Ports\Site_Command_Group;

/**
 * Installed plugins, including must-use plugins and drop-ins, as WP-CLI lists them.
 */
final class Plugin_Commands implements Site_Command_Group {

	private const STATUSES       = array( 'active', 'active-network', 'inactive', 'must-use', 'dropin' );
	private const DEFAULT_FIELDS = array( 'name', 'status', 'update', 'version', 'update_version', 'auto_update' );
	private const FIELDS         = array( 'name', 'title', 'status', 'update', 'version', 'update_version', 'auto_update', 'file', 'author', 'description', 'requires', 'requires_php' );
	private const GET_FIELDS     = array( 'name', 'title', 'author', 'version', 'description', 'status' );
	/**
	 * Entries the last listing could not read.
	 *
	 * @var int
	 */
	private int $skipped = 0;

	/**
	 * Whether the last listing had an update check to answer from.
	 *
	 * @var bool
	 */
	private bool $updates_known = true;

	private const STATUS_CODES = array(
		'active'         => 'A',
		'active-network' => 'N',
		'inactive'       => 'I',
		'must-use'       => 'M',
		'dropin'         => 'D',
	);

	/**
	 * The commands this group offers.
	 *
	 * @return Command_Definition[]
	 */
	public function commands(): array {
		$plugin = Command_Argument::required( 'plugin', 'Plugin name, such as akismet, or its file, such as akismet/akismet.php.' );

		return array(
			Command_Definition::reads( 'plugin list', 'List installed plugins. The update fields reflect WordPress\'s last update check.' )
				->with_option( Command_Option::choice( 'status', 'Only plugins with this status.', self::STATUSES ) )
				->with_fields( self::DEFAULT_FIELDS, self::FIELDS, 'Plugins' )
				->handled_by( array( $this, 'list_plugins' ) ),
			Command_Definition::reads( 'plugin get', 'Show details about one installed plugin.' )
				->with_argument( $plugin )
				->with_record_fields( self::GET_FIELDS, self::FIELDS )
				->handled_by( array( $this, 'get' ) ),
			Command_Definition::reads( 'plugin status', 'Summarise installed plugins, or show the status of one plugin.' )
				->with_argument( Command_Argument::optional( 'plugin', 'Plugin name, such as akismet, or its file, such as akismet/akismet.php.' ) )
				->handled_by( array( $this, 'status' ) ),
			Command_Definition::reads( 'plugin is-active', 'Check whether a plugin is active. Prints yes or no. Must-use plugins always run; a drop-in is active when WordPress loads it, so advanced-cache.php needs WP_CACHE.' )
				->with_argument( $plugin )
				->handled_by( array( $this, 'is_active' ) ),
			Command_Definition::reads( 'plugin is-installed', 'Check whether a plugin is installed. Prints yes or no.' )
				->with_argument( $plugin )
				->handled_by( array( $this, 'is_installed' ) ),
		);
	}

	/**
	 * The "plugin list" command: regular plugins in WordPress's order, then
	 * must-use plugins, then drop-ins.
	 *
	 * @param array<string, string>      $arguments Unused.
	 * @param array<string, string|bool> $options   Validated options.
	 * @return Command_Output
	 */
	public function list_plugins( array $arguments, array $options ): Command_Output {
		$status = isset( $options['status'] ) ? (string) $options['status'] : null;

		$rows = $this->rows();

		$output = Values::with_skipped( Command_Output::rows( Rows::where( $rows, 'status', $status ) )->with_stats( $this->summary( $rows ) ), $this->skipped );

		return $this->updates_known ? $output : $output->with_warning( Update_Status::NOT_CHECKED );
	}

	/**
	 * Every installed plugin by status, whatever --status shows, and how many
	 * can be updated, from WordPress's last update check.
	 *
	 * @param array<int, array<string, scalar>> $rows Every installed plugin.
	 * @return string
	 */
	private function summary( array $rows ): string {
		$statuses = array_count_values( array_column( $rows, 'status' ) );
		$updates  = array_count_values( array_map( 'strval', array_column( $rows, 'update' ) ) );
		$parts    = array();
		foreach ( self::STATUSES as $name ) {
			if ( isset( $statuses[ $name ] ) ) {
				$parts[] = $statuses[ $name ] . ' ' . $name;
			}
		}
		// Unreadable entries are installed too; the total says it leaves them out.
		$line = sprintf( 'Installed: %d%s (%s). Updates available: %s.', count( $rows ), 0 === $this->skipped ? '' : sprintf( ' readable, %d unreadable', $this->skipped ), implode( ', ', $parts ), $this->updates_known ? (string) ( $updates['available'] ?? 0 ) : 'unknown' );
		if ( isset( $updates['unknown'] ) && $this->updates_known ) {
			$line .= sprintf( ' Update status unknown for %d: WordPress\'s last update check holds a version that is not one.', $updates['unknown'] );
		}
		if ( isset( $updates['unavailable'] ) ) {
			$line .= sprintf( ' %d more need a newer WordPress or PHP.', $updates['unavailable'] );
		}

		return $line;
	}

	/**
	 * The "plugin get" command.
	 *
	 * @param array<string, string>      $arguments Named arguments.
	 * @param array<string, string|bool> $options   Unused.
	 * @return Command_Output
	 */
	public function get( array $arguments, array $options ): Command_Output {
		return Command_Output::record( $this->find( $arguments['plugin'] ) );
	}

	/**
	 * The "plugin status" command, in WP-CLI's layout.
	 *
	 * @param array<string, string>      $arguments Named arguments.
	 * @param array<string, string|bool> $options   Unused.
	 * @return Command_Output
	 */
	public function status( array $arguments, array $options ): Command_Output {
		if ( isset( $arguments['plugin'] ) ) {
			$plugin = $this->find( $arguments['plugin'] );

			return Command_Output::text(
				implode(
					"\n",
					array(
						'Plugin ' . $plugin['name'] . ' details:',
						'    Name: ' . $plugin['title'],
						'    Status: ' . ucwords( str_replace( '-', ' ', (string) $plugin['status'] ) ),
						'    Version: ' . $plugin['version'],
						'    Author: ' . $plugin['author'],
						'    Description: ' . $plugin['description'],
					)
				)
			);
		}

		$rows  = $this->rows();
		$width = 0;
		foreach ( $rows as $row ) {
			$width = max( $width, strlen( (string) $row['name'] ) );
		}
		$updates = false;
		$lines   = array( count( $rows ) . ' installed plugins:' );
		$codes   = array();
		foreach ( $rows as $row ) {
			$update                                        = in_array( $row['update'], array( 'available', 'unavailable' ), true ) ? 'U' : ' ';
			$codes[ self::STATUS_CODES[ $row['status'] ] ] = true;
			if ( 'U' === $update ) {
				$updates = true;
			}
			$version = (string) $row['version'];
			$lines[] = rtrim( ' ' . $update . self::STATUS_CODES[ $row['status'] ] . ' ' . str_pad( (string) $row['name'], $width ) . ' ' . $version );
		}
		$lines[] = '';
		// As in WP-CLI, the legend lists only the codes shown, in order of appearance.
		$names  = array(
			'A' => 'Active',
			'I' => 'Inactive',
			'M' => 'Must Use',
			'N' => 'Network Active',
			'D' => 'Drop-In',
		);
		$legend = array();
		foreach ( array_keys( $codes ) as $code ) {
			$legend[] = $code . ' = ' . $names[ $code ];
		}
		if ( $updates ) {
			$legend[] = 'U = Update Available';
		}
		$lines[] = 'Legend: ' . implode( ', ', $legend );

		return Command_Output::text( implode( "\n", $lines ) );
	}

	/**
	 * The "plugin is-active" command.
	 *
	 * @param array<string, string>      $arguments Named arguments.
	 * @param array<string, string|bool> $options   Unused.
	 * @return Command_Output
	 */
	public function is_active( array $arguments, array $options ): Command_Output {
		$plugin = $this->lookup( $arguments['plugin'] );
		if ( null === $plugin ) {
			return Command_Output::text( 'no' );
		}
		if ( 'dropin' === $plugin['status'] ) {
			$known = _get_dropins();

			return Command_Output::text( self::dropin_runs( is_array( $known[ $plugin['file'] ] ?? null ) ? ( $known[ $plugin['file'] ][1] ?? null ) : null ) ? 'yes' : 'no' );
		}

		// Must-use plugins always run, so they are active.
		return Command_Output::text( 'inactive' !== $plugin['status'] ? 'yes' : 'no' );
	}

	/**
	 * Whether WordPress loads a drop-in, by the condition _get_dropins() gives
	 * it, as the Plugins screen decides: true, or the name of a constant that
	 * must be defined and true, such as WP_CACHE for advanced-cache.php.
	 *
	 * @param mixed $condition The drop-in's load condition.
	 * @return bool
	 */
	public static function dropin_runs( $condition ): bool {
		return true === $condition || ( is_string( $condition ) && '' !== $condition && defined( $condition ) && (bool) constant( $condition ) );
	}

	/**
	 * The "plugin is-installed" command.
	 *
	 * @param array<string, string>      $arguments Named arguments.
	 * @param array<string, string|bool> $options   Unused.
	 * @return Command_Output
	 */
	public function is_installed( array $arguments, array $options ): Command_Output {
		return Command_Output::text( null !== $this->lookup( $arguments['plugin'] ) ? 'yes' : 'no' );
	}

	/**
	 * A plugin by name, or a WP-CLI style error.
	 *
	 * @param string $name Plugin name.
	 * @return array<string, scalar>
	 *
	 * @throws Command_Error When it is not installed.
	 */
	private function find( string $name ): array {
		$plugin = $this->lookup( $name );
		if ( null === $plugin ) {
			throw new Command_Error( sprintf( "The '%s' plugin could not be found.", $name ) );
		}

		return $plugin;
	}

	/**
	 * A plugin by name, or null.
	 *
	 * @param string $name Plugin name.
	 * @return array<string, scalar>|null
	 */
	private function lookup( string $name ): ?array {
		foreach ( $this->rows() as $row ) {
			// As in WP-CLI, a plugin is found by its name or its file.
			if ( $row['name'] === $name || $row['file'] === $name ) {
				return $row;
			}
		}

		return null;
	}

	/**
	 * Every installed plugin: regular plugins, must-use plugins, then drop-ins.
	 *
	 * @return array<int, array<string, scalar>>
	 *
	 * @throws Command_Error When the active plugin lists are unreadable.
	 */
	private function rows(): array {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$transient           = get_site_transient( 'update_plugins' );
		$this->updates_known = Update_Status::known( $transient );
		$offered             = Update_Status::entries( $transient, 'response' );
		$withheld            = Update_Status::entries( $transient, 'no_update' );
		$auto_updates        = get_site_option( Update_Status::auto_update_option( 'plugin' ), array() );
		$auto_updates        = is_array( $auto_updates ) ? $auto_updates : array();

		// Statuses come from these options; unreadable ones must not turn every
		// plugin into "inactive".
		if ( ! is_array( get_option( 'active_plugins', array() ) ) || ( is_multisite() && ! is_array( get_site_option( 'active_sitewide_plugins', array() ) ) ) ) {
			throw new Command_Error( 'WordPress\'s list of active plugins is unreadable, so plugin statuses cannot be reported.' );
		}

		// The same filter the Plugins screen applies; a misbehaving callback
		// falls back to the unfiltered list rather than inventing plugins.
		$plugins = Dispatcher::filter( 'all_plugins', get_plugins() );
		$plugins = is_array( $plugins ) ? $plugins : get_plugins();

		$rows          = array();
		$this->skipped = 0;
		foreach ( $plugins as $file => $data ) {
			if ( ! is_array( $data ) ) {
				++$this->skipped;
				continue;
			}
			$file   = (string) $file;
			$status = 'inactive';
			if ( is_multisite() && is_plugin_active_for_network( $file ) ) {
				$status = 'active-network';
			} elseif ( is_plugin_active( $file ) ) {
				$status = 'active';
			}
			$row  = $this->row( $file, $data, $status );
			$info = $offered[ $file ] ?? $withheld[ $file ] ?? array();

			list( $row['update'], $row['update_version'] ) = $this->updates_known ? Update_Status::status( $offered[ $file ] ?? null, $withheld[ $file ] ?? null, (string) $row['version'] ) : array( 'unknown', '' );

			$row['auto_update']  = in_array( $file, $auto_updates, true ) ? 'on' : 'off';
			$row['requires']     = '' !== ( $info['requires'] ?? '' ) ? $info['requires'] : Values::text( $data['RequiresWP'] ?? '' );
			$row['requires_php'] = '' !== ( $info['requires_php'] ?? '' ) ? $info['requires_php'] : Values::text( $data['RequiresPHP'] ?? '' );
			$rows[]              = $row;
		}
		foreach ( array(
			'must-use' => get_mu_plugins(),
			'dropin' => get_dropins(),
		) as $status => $found ) {
			foreach ( $found as $file => $data ) {
				if ( is_array( $data ) ) {
					$rows[] = $this->row( (string) $file, $data, $status );
				} else {
					++$this->skipped;
				}
			}
		}

		return $rows;
	}

	/**
	 * One plugin row. Must-use plugins and drop-ins are never update-checked,
	 * so their update is false rather than a claimed "none".
	 *
	 * @param string $file   Plugin file relative to its directory.
	 * @param array  $data   Plugin headers.
	 * @param string $status Plugin status.
	 * @return array<string, scalar>
	 */
	private function row( string $file, array $data, string $status ): array {
		$directory = dirname( $file );
		$name      = '.' === $directory ? basename( $file, '.php' ) : $directory;
		if ( 'dropin' === $status ) {
			$name = $file;
		}

		return array(
			'name'           => $name,
			'title'          => Values::text( $data['Title'] ?? '' ),
			'status'         => $status,
			'update'         => false,
			'version'        => 'dropin' === $status ? '' : Values::text( $data['Version'] ?? '' ),
			'update_version' => '',
			'auto_update'    => 'off',
			'file'           => $file,
			'author'         => wp_strip_all_tags( Values::text( $data['Author'] ?? '' ) ),
			'description'    => wp_strip_all_tags( Values::text( $data['Description'] ?? '' ) ),
			'requires'       => '',
			'requires_php'   => '',
		);
	}
}
