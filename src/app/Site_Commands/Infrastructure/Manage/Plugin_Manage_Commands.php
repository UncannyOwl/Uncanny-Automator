<?php
/**
 * The "plugin" commands that change the site.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Command errors are plain terminal text for AI clients, never rendered as HTML.

namespace Uncanny_Automator\App\Site_Commands\Infrastructure\Manage;

use Uncanny_Automator\App\Site_Commands\Domain\Command_Argument;
use Uncanny_Automator\App\Site_Commands\Domain\Command_Definition;
use Uncanny_Automator\App\Site_Commands\Domain\Command_Error;
use Uncanny_Automator\App\Site_Commands\Domain\Command_Option;
use Uncanny_Automator\App\Site_Commands\Domain\Output\Command_Output;
use Uncanny_Automator\App\Site_Commands\Domain\Time_Budget;
use Uncanny_Automator\App\Site_Commands\Infrastructure\Commands\Update_Status;
use Uncanny_Automator\App\Site_Commands\Infrastructure\Request_Time_Budget;
use Uncanny_Automator\App\Site_Commands\Ports\Site_Command_Group;

/**
 * Install, update, activate and deactivate plugins, and switch their
 * automatic updates, as WP-CLI does. Nothing is deleted or uninstalled.
 * Plugins come only from WordPress.org, or from the updates WordPress
 * already offers.
 */
final class Plugin_Manage_Commands implements Site_Command_Group {

	/** Time to replace a plugin's files and answer. */
	private const REPLACE_SECONDS = 10.0;

	/** Time an update needs to replace the files, then remove the backup or put it back, and answer. */
	private const UPDATE_REPLACE_SECONDS = self::REPLACE_SECONDS + Change_Session::CLEANUP_SECONDS;

	/** Time an update needs once its download finished. */
	private const AFTER_DOWNLOAD_SECONDS = Change_Session::UNZIP_SECONDS + self::UPDATE_REPLACE_SECONDS;

	/** Time an install needs once its download finished; it has no backup. */
	private const INSTALL_AFTER_DOWNLOAD_SECONDS = Change_Session::UNZIP_SECONDS + self::REPLACE_SECONDS;

	/** Time to download, unpack and replace one plugin, and answer. */
	private const UPDATE_SECONDS = self::AFTER_DOWNLOAD_SECONDS + 3.0;

	/** Time to look a plugin up on WordPress.org, then install it. */
	private const INSTALL_SECONDS = self::INSTALL_AFTER_DOWNLOAD_SECONDS + 7.0;

	/** Time to activate or deactivate a plugin, whose hooks run then, and answer. */
	private const SWITCH_SECONDS = 10.0;

	/** Time kept after checking the site, to answer. */
	private const CHECK_RESERVE_SECONDS = 1.0;

	/** Time to look for functions and classes a plugin would declare twice. */
	private const SCAN_SECONDS = 2.0;

	/** Time to check a plugin's declarations, activate it, check the site, and answer. */
	private const ACTIVATE_SECONDS = self::SCAN_SECONDS + self::SWITCH_SECONDS + Health_Check::SECONDS + self::CHECK_RESERVE_SECONDS;


	/** Fatal error types, as PHP reports them. */
	private const FATAL_TYPES = array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR );

	/** The last activation from here that stopped the request with a fatal error. */
	private const FAILED_ACTIVATION = 'automator_site_manage_failed_activation';

	/**
	 * How long that activation is not tried again while nothing changed: an
	 * hour. A number, not HOUR_IN_SECONDS, so the class loads without WordPress.
	 */
	private const FAILED_ACTIVATION_SECONDS = 3600;

	/** Most files looked at to tell whether a plugin changed since a failed activation. */
	private const STATE_MAX_FILES = 5000;

	private const UPDATE_FIELDS = array( 'name', 'old_version', 'new_version', 'status', 'detail' );

	/** Said of a plugin no update source reports on. */
	private const NOT_TRACKED_DETAIL = 'No update source reports on this plugin: WordPress.org does not list it, and no update provider on this site answered for it, so its updates cannot be found here. For a premium plugin, check its license and update settings. Nothing was changed.';

	/**
	 * The plugin file whose activation is running, so a fatal error in it can
	 * be recorded when the request ends.
	 *
	 * @var string|null
	 */
	private static ?string $activating = null;

	/**
	 * Whether the request's end is watched for a fatal error in an activation.
	 *
	 * @var bool
	 */
	private static bool $watching = false;

	/**
	 * Why the last activation's check for declarations that already exist did
	 * not finish, or null.
	 *
	 * @var string|null
	 */
	private ?string $unscanned = null;


	/**
	 * Starts the time budget of each command.
	 *
	 * @var callable(): Time_Budget
	 */
	private $budget;

	/**
	 * Use the current request's time budget unless another is supplied.
	 *
	 * @param callable(): Time_Budget|null $budget Starts a command's time budget.
	 */
	public function __construct( ?callable $budget = null ) {
		$this->budget = $budget ?? array( Request_Time_Budget::class, 'start' );
	}

	/**
	 * The commands this group offers.
	 *
	 * @return Command_Definition[]
	 */
	public function commands(): array {
		$plugin = Command_Argument::required( 'plugin', 'Installed plugin name, such as akismet, or its file, such as akismet/akismet.php.' );

		return array(
			Command_Definition::changes( 'plugin install', 'Install a plugin from WordPress.org by its slug. Refused if it is already installed; nothing is overwritten.' )
				->with_argument( Command_Argument::required( 'slug', 'WordPress.org plugin slug, such as akismet.' ) )
				->with_option( Command_Option::flag( 'activate', 'Activate the plugin after installing it.' ) )
				->handled_by( array( $this, 'install' ) ),
			Command_Definition::changes( 'plugin update', 'Update one plugin, or every plugin with --all, to the version WordPress offers. Plugins are updated one at a time; when too little time is left for the next one, the rest are listed as not started, so run the command again. Automator does not update itself here.' )
				->with_argument( Command_Argument::optional( 'plugin', 'Installed plugin name, such as akismet, or its file. Omit it with --all.' ) )
				->with_option( Command_Option::flag( 'all', 'Update every plugin that has an update.' ) )
				->with_option( Command_Option::flag( 'dry-run', 'Only list what would be updated, and why anything would be skipped.' ) )
				->with_fields( self::UPDATE_FIELDS, self::UPDATE_FIELDS, 'Plugins' )
				->handled_by( array( $this, 'update' ) ),
			Command_Definition::changes( 'plugin activate', 'Activate an installed plugin. Its activation code runs.' )
				->with_argument( $plugin )
				->handled_by( array( $this, 'activate' ) ),
			Command_Definition::changes( 'plugin deactivate', 'Deactivate a plugin. Its files and settings stay; nothing is deleted. Refused while active plugins depend on it. Automator cannot deactivate itself.' )
				->with_argument( $plugin )
				->handled_by( array( $this, 'deactivate' ) ),
			Command_Definition::changes( 'plugin auto-updates enable', 'Turn on WordPress\'s automatic updates for a plugin.' )
				->with_argument( $plugin )
				->handled_by( array( $this, 'enable_auto_updates' ) ),
			Command_Definition::changes( 'plugin auto-updates disable', 'Turn off WordPress\'s automatic updates for a plugin.' )
				->with_argument( $plugin )
				->handled_by( array( $this, 'disable_auto_updates' ) ),
		);
	}

	/**
	 * The "plugin install" command.
	 *
	 * @param array<string, string>      $arguments The slug.
	 * @param array<string, string|bool> $options   Validated options.
	 * @return Command_Output
	 *
	 * @throws Command_Error When it cannot be installed.
	 */
	public function install( array $arguments, array $options ): Command_Output {
		$slug     = $arguments['slug'];
		$activate = true === ( $options['activate'] ?? false );
		if ( 1 !== preg_match( '/^[a-z0-9][a-z0-9-]{0,199}$/', $slug ) ) {
			throw new Command_Error( sprintf( '"%s" is not a WordPress.org plugin slug: use lowercase letters, numbers and hyphens, such as akismet. Plugins install only from WordPress.org. Nothing was changed.', $slug ) );
		}
		Site_Changes::require_capability( 'install_plugins', 'install plugins' );
		if ( $activate ) {
			Site_Changes::require_capability( 'activate_plugins', 'activate plugins' );
		}
		Site_Changes::load_admin_files();
		$installed = get_plugins( '/' . $slug );
		if ( array() !== $installed ) {
			$file = (string) array_key_first( $installed );
			throw new Command_Error( sprintf( 'The plugin %1$s is already installed (%2$s, version %3$s). Nothing was changed. Run "plugin update %1$s" to update it.', $slug, $slug . '/' . $file, Site_Changes::text( $installed[ $file ]['Version'] ?? 'unknown' ) ) );
		}
		if ( file_exists( WP_PLUGIN_DIR . '/' . $slug ) ) {
			throw new Command_Error( sprintf( 'The folder wp-content/plugins/%s exists but holds no plugin WordPress can read, possibly left by an interrupted install. Nothing was changed. Remove that folder from the server, then install again.', $slug ) );
		}
		Site_Changes::require_file_changes( WP_PLUGIN_DIR, 'Plugins › Add Plugin' );
		Change_Session::require_no_other_updates();

		return Change_Session::run(
			$this->budget(),
			function ( Change_Session $session ) use ( $slug, $activate ): Command_Output {
				$session->budget()->require( self::INSTALL_SECONDS, sprintf( 'Installing %s', $slug ), 'Plugins › Add Plugin' );
				$session->lock();
				$session->only_wordpress_org();
				$session->expect( self::INSTALL_AFTER_DOWNLOAD_SECONDS, self::REPLACE_SECONDS );

				$api = plugins_api(
					'plugin_information',
					array(
						'slug'   => $slug,
						'fields' => array(
							'sections'     => false,
							'requires'     => true,
							'requires_php' => true,
						),
					)
				);
				if ( is_wp_error( $api ) ) {
					throw new Command_Error( Packages::lookup_failure( $session->explain( $api ), $slug, 'plugin' ) );
				}
				Packages::require_installable( $api, $slug, 'plugin' );

				// WordPress deletes its last update check after an install. It builds a new one
				// in a follow-up check that only wp-admin hooks, and that a command removes
				// when it is hooked. Nothing would rebuild it here, so the last check is kept,
				// as "plugin update" keeps it.
				$skin     = new \WP_Ajax_Upgrader_Skin();
				$upgrader = new \Plugin_Upgrader( $skin );
				$result   = $upgrader->install( (string) $api->download_link, array( 'clear_update_cache' => false ) );
				$failure  = Site_Changes::outcome( $session->explain( $result ), $skin, Site_Changes::INSTALL );
				if ( null !== $failure ) {
					throw new Command_Error( sprintf( 'Installing %s failed: %s', $slug, $failure ) );
				}
				$session->expect( 0.0, 0.0 );

				$file   = (string) $upgrader->plugin_info();
				$data   = '' !== $file ? get_plugin_data( WP_PLUGIN_DIR . '/' . $file, false, false ) : array();
				$output = sprintf( 'Installed %s %s (%s).', Site_Changes::text( $data['Name'] ?? $api->name ?? $slug ), Site_Changes::text( $data['Version'] ?? $api->version ?? '' ), '' !== $file ? $file : $slug );
				if ( ! $activate ) {
					return Command_Output::text( $output . ' It is not active; run "plugin activate ' . $slug . '" to activate it.' );
				}
				if ( '' === $file ) {
					return Command_Output::text( $output )->with_warning( 'It was not activated: WordPress could not tell which file in the package is the plugin. Run "plugin activate ' . $slug . '".' );
				}
				if ( ! $session->budget()->allows( self::ACTIVATE_SECONDS ) ) {
					return Command_Output::text( $output )->with_warning( 'It was not activated: ' . $session->budget()->shortfall( self::ACTIVATE_SECONDS, 'Activating it and checking the site' ) . ' Run "plugin activate ' . $slug . '".' );
				}
				$activation = $this->activate_file( $file, $session, true );
				if ( ! is_plugin_active( $file ) ) {
					return Command_Output::text( $output )->with_warning( 'It was installed but not activated: ' . ( $activation ?? 'WordPress reported no error, but the plugin is not active.' ) );
				}
				list( $broke, $unchecked ) = $this->check_site( $session, $file );
				$result                    = $this->with_unscanned( Command_Output::text( $output . ' Activated.' ) );
				if ( null !== $broke ) {
					return $result->with_warning( $broke )->with_failure();
				}
				if ( null !== $activation ) {
					$result = $result->with_warning( 'WordPress reported a problem while activating it: ' . $activation );
				}

				return null === $unchecked ? $result : $result->with_warning( $unchecked );
			}
		);
	}

	/**
	 * The "plugin update" command.
	 *
	 * @param array<string, string>      $arguments The plugin, unless --all.
	 * @param array<string, string|bool> $options   Validated options.
	 * @return Command_Output
	 *
	 * @throws Command_Error When nothing can be updated.
	 */
	public function update( array $arguments, array $options ): Command_Output {
		$all     = true === ( $options['all'] ?? false );
		$dry_run = true === ( $options['dry-run'] ?? false );
		$named   = $arguments['plugin'] ?? null;
		if ( ( null !== $named ) === $all ) {
			throw new Command_Error( 'Name one plugin, or use --all, but not both. Usage: plugin update [<plugin>] [--all] [--dry-run]. Nothing was changed.' );
		}
		Site_Changes::require_capability( 'update_plugins', 'update plugins' );
		Site_Changes::load_admin_files();
		$plugins = get_plugins();
		$files   = null === $named ? array_keys( $plugins ) : array( $this->find( $named, $plugins ) );
		if ( ! $dry_run ) {
			Site_Changes::require_file_changes( WP_PLUGIN_DIR );
			Change_Session::require_no_other_updates();
		}

		return Change_Session::run(
			$this->budget(),
			function ( Change_Session $session ) use ( $named, $dry_run, $plugins, $files ): Command_Output {
				$session->budget()->require( $dry_run ? 5.0 : self::UPDATE_SECONDS, null === $named ? 'Updating plugins' : sprintf( 'Updating %s', self::name( $files[0] ) ) );
				if ( ! $dry_run ) {
					$session->lock();
				}
				$installed = (string) ( $plugins[ $files[0] ]['Version'] ?? '' );
				// An update WordPress already offers for the named plugin needs no new check.
				list( $current, $warnings ) = null !== $named && Packages::offers_newer( 'update_plugins', $files[0], $installed )
					? array( true, array() )
					: Packages::refresh( 'plugin', $session, null === $named ? array() : Packages::update_hosts( 'update_plugins', $files[0], (string) ( $plugins[ $files[0] ]['UpdateURI'] ?? '' ) ) );
				$check                      = get_site_transient( 'update_plugins' );

				list( $rows, $pending ) = $this->plan( $files, $plugins, $check, $current, null !== $named, $dry_run );
				if ( ! $dry_run ) {
					$updated = $this->run_updates( $session, $rows, $pending );
					$session->expect( 0.0, 0.0 );
					Packages::forget_offers( 'update_plugins', $updated );
				}

				if ( null !== $named && Packages::SITE_ERROR === $rows[0]['status'] ) {
					throw new Command_Error( sprintf( '%s was updated from %s to %s. %s', $rows[0]['name'], $rows[0]['old_version'], $rows[0]['new_version'], $rows[0]['detail'] ) );
				}
				if ( null !== $named && 'failed' === $rows[0]['status'] ) {
					throw new Command_Error( sprintf( 'Updating %s from %s to %s failed: %s', $rows[0]['name'], $rows[0]['old_version'], $rows[0]['new_version'], $rows[0]['detail'] ) );
				}
				if ( null !== $named && in_array( $rows[0]['status'], array( 'skipped', 'not started' ), true ) ) {
					throw new Command_Error( sprintf( '%s was not updated (installed %s, offered %s): %s', $rows[0]['name'], $rows[0]['old_version'], $rows[0]['new_version'], $rows[0]['detail'] ) . ( false === strpos( $rows[0]['detail'], 'Nothing was changed.' ) ? ' Nothing was changed.' : '' ) );
				}

				return Packages::output( $rows, $dry_run, $warnings, 'updated', null !== $named );
			}
		);
	}

	/**
	 * The rows to report and the updates to run.
	 *
	 * @param string[]                            $files   Plugin files to consider.
	 * @param array<string, array<string, mixed>> $plugins Installed plugins.
	 * @param mixed                               $check   WordPress's full update check, including explicit no-update answers.
	 * @param bool                                $checked Whether refreshing the check reported no failures.
	 * @param bool                                $named   Whether one plugin was named, so it is listed even without an update.
	 * @param bool                                $dry_run Whether nothing changes.
	 * @return array{0: array<int, array<string, string>>, 1: array<string, int>} Rows, and row index by file to update.
	 */
	private function plan( array $files, array $plugins, $check, bool $checked, bool $named, bool $dry_run ): array {
		$rows    = array();
		$pending = array();
		foreach ( $files as $file ) {
			$row                             = array(
				'name'        => self::name( $file ),
				'old_version' => Site_Changes::text( $plugins[ $file ]['Version'] ?? '' ),
				'new_version' => '',
				'status'      => 'unknown',
				'detail'      => 'WordPress returned no usable update information for this installed version. This does not mean the plugin is up to date. Check Dashboard › Updates and the plugin\'s update settings. For a premium plugin, check its provider account and license status. Nothing was changed.',
			);
			list( $state, $version, $offer ) = Packages::classify( $check, $file, $row['old_version'], $checked );
			if ( 'up to date' === $state ) {
				if ( $named ) {
					$row['status'] = 'up to date';
					$row['detail'] = '';
					$rows[]        = $row;
				}
				continue;
			}
			if ( Packages::NOT_TRACKED === $state ) {
				$row['status'] = Packages::NOT_TRACKED;
				$row['detail'] = self::NOT_TRACKED_DETAIL;
			} elseif ( 'stale' === $state ) {
				$row['detail'] = 'WordPress\'s no-update answer is stale, its refresh failed, or it was checked for a different installed version. Current update availability could not be determined. Check Dashboard › Updates and the plugin\'s update settings. Nothing was changed.';
			}
			if ( null === $offer ) {
				$rows[] = $row;
				continue;
			}
			$row['new_version'] = $version;
			$skip               = $this->skip_reason( $file, $offer );
			if ( null === $skip && 'withheld' === $state ) {
				$skip = 'WordPress reports a newer version but has withheld this update. Check Dashboard › Updates and the plugin\'s update settings for its requirements.';
			}
			if ( null !== $skip ) {
				$row['status'] = 'skipped';
				$row['detail'] = $skip;
				$rows[]        = $row;
				continue;
			}
			$row['status']    = $dry_run ? 'available' : 'pending';
			$row['detail']    = '';
			$pending[ $file ] = count( $rows );
			$rows[]           = $row;
		}

		return array( $rows, $pending );
	}

	/**
	 * Update the pending plugins one at a time while time allows.
	 *
	 * @param Change_Session                    $session The session.
	 * @param array<int, array<string, string>> $rows    Result rows, updated in place.
	 * @param array<string, int>                $pending Row index by plugin file.
	 * @return string[] Updated plugin files.
	 */
	private function run_updates( Change_Session $session, array &$rows, array $pending ): array {
		$updated = array();
		$stopped = null;
		foreach ( $pending as $file => $index ) {
			if ( null !== $stopped ) {
				$rows[ $index ]['status'] = 'not started';
				$rows[ $index ]['detail'] = $stopped;
				continue;
			}
			$budget = $session->budget();
			if ( ! $budget->allows( self::UPDATE_SECONDS ) ) {
				$rows[ $index ]['status'] = 'not started';
				$rows[ $index ]['detail'] = $budget->shortfall( self::UPDATE_SECONDS, 'This update' ) . ' ' . $budget->next_step( self::UPDATE_SECONDS );
				$stopped                  = $rows[ $index ]['detail'];
				continue;
			}
			list( $status, $detail, $stop ) = $this->update_file( $session, (string) $file );
			if ( $stop ) {
				$stopped = Packages::SITE_ERROR === $status
					? sprintf( 'Not attempted: the batch stopped because the site stopped with a PHP fatal error after %s was updated. Recover the site as that row says, then run the command again. Reference: %s.', self::name( (string) $file ), Health_Check::REF )
					: 'The batch stopped after an earlier update failed or was interrupted. Check the site, then run the command again.';
			}
			$rows[ $index ]['status'] = $status;
			$rows[ $index ]['detail'] = $detail;
			if ( in_array( $status, array( 'updated', Packages::SITE_ERROR ), true ) ) {
				$updated[] = (string) $file;
			}
		}

		return $updated;
	}

	/**
	 * The "plugin activate" command.
	 *
	 * @param array<string, string>      $arguments The plugin.
	 * @param array<string, string|bool> $options   Unused.
	 * @return Command_Output
	 *
	 * @throws Command_Error When it cannot be activated.
	 */
	public function activate( array $arguments, array $options ): Command_Output {
		Site_Changes::require_capability( 'activate_plugins', 'activate plugins' );
		Site_Changes::load_admin_files();
		$file = $this->find( $arguments['plugin'], get_plugins() );
		$name = self::name( $file );
		Site_Changes::require_capability( 'activate_plugin', sprintf( 'activate %s', $name ), $file );
		if ( is_plugin_active_for_network( $file ) ) {
			return Command_Output::text( sprintf( 'Plugin \'%s\' is already active across the network. Nothing was changed.', $name ) );
		}
		if ( is_plugin_active( $file ) ) {
			return Command_Output::text( sprintf( 'Plugin \'%s\' is already active. Nothing was changed.', $name ) );
		}

		return Change_Session::run(
			$this->budget(),
			function ( Change_Session $session ) use ( $file, $name ): Command_Output {
				$session->budget()->require( self::ACTIVATE_SECONDS, sprintf( 'Activating %s and checking the site', $name ), 'Plugins' );
				$session->lock();
				$failure = $this->activate_file( $file, $session );
				if ( ! is_plugin_active( $file ) ) {
					throw new Command_Error( sprintf( 'Plugin \'%s\' was not activated: %s', $name, $failure ?? 'WordPress reported no error, but the plugin is not active.' ) );
				}
				list( $broke, $unchecked ) = $this->check_site( $session, $file );
				if ( null !== $broke ) {
					throw new Command_Error( $broke . ( null === $this->unscanned ? '' : ' ' . $this->unscanned_note() ) );
				}
				$result = $this->with_unscanned( Command_Output::text( sprintf( 'Plugin \'%s\' activated.', $name ) ) );
				if ( null !== $failure ) {
					$result = $result->with_warning( 'WordPress reported a problem while activating it: ' . $failure );
				}

				return null === $unchecked ? $result : $result->with_warning( $unchecked );
			}
		);
	}

	/**
	 * The "plugin deactivate" command.
	 *
	 * @param array<string, string>      $arguments The plugin.
	 * @param array<string, string|bool> $options   Unused.
	 * @return Command_Output
	 *
	 * @throws Command_Error When it cannot be deactivated.
	 */
	public function deactivate( array $arguments, array $options ): Command_Output {
		Site_Changes::require_capability( 'activate_plugins', 'deactivate plugins' );
		Site_Changes::load_admin_files();
		$file = $this->find( $arguments['plugin'], get_plugins() );
		$name = self::name( $file );
		Site_Changes::require_capability( 'deactivate_plugin', sprintf( 'deactivate %s', $name ), $file );
		if ( self::is_automator( $file ) ) {
			throw new Command_Error( sprintf( 'Automator cannot deactivate %s, because this connection runs through it. Nothing was changed. Deactivate it from Plugins in WordPress.', $name ) );
		}
		if ( is_plugin_active_for_network( $file ) ) {
			throw new Command_Error( sprintf( 'Plugin \'%s\' is active across the network. Nothing was changed. Deactivate it from Network Admin › Plugins.', $name ) );
		}
		if ( ! is_plugin_active( $file ) ) {
			return Command_Output::text( sprintf( 'Plugin \'%s\' is not active. Nothing was changed.', $name ) );
		}
		$dependents = self::active_dependents( $file );
		if ( array() !== $dependents ) {
			throw new Command_Error( sprintf( 'Plugin \'%1$s\' is required by active plugins: %2$s. Nothing was changed. Deactivate those first.', $name, implode( ', ', $dependents ) ) );
		}

		return Change_Session::run(
			$this->budget(),
			function ( Change_Session $session ) use ( $file, $name ): Command_Output {
				$session->budget()->require( self::SWITCH_SECONDS, sprintf( 'Deactivating %s', $name ), 'Plugins' );
				$session->lock();
				try {
					deactivate_plugins( $file );
				} catch ( \Throwable $throwable ) {
					throw new Command_Error(
						is_plugin_active( $file )
							? sprintf( 'Plugin \'%1$s\' is still active: its deactivation code stopped with %2$s before WordPress removed it from the active plugins. Check the site, then deactivate it from Plugins in WordPress.', $name, get_class( $throwable ) )
							: sprintf( 'Plugin \'%1$s\' was deactivated, but code that runs on deactivation stopped with %2$s, so it may not have finished. Its files and settings were kept. Check the site.', $name, get_class( $throwable ) )
					);
				}
				if ( is_plugin_active( $file ) ) {
					throw new Command_Error( sprintf( 'Plugin \'%s\' is still active: WordPress ran its deactivation, but the plugin stayed in the active list, possibly because other code keeps it active. Check the site.', $name ) );
				}
				// As on the Plugins screen, it is listed as recently active.
				update_option( 'recently_activated', array( $file => time() ) + (array) get_option( 'recently_activated', array() ), false );

				return Command_Output::text( sprintf( 'Plugin \'%s\' deactivated. Its files and settings were kept.', $name ) );
			}
		);
	}

	/**
	 * The "plugin auto-updates enable" command.
	 *
	 * @param array<string, string>      $arguments The plugin.
	 * @param array<string, string|bool> $options   Unused.
	 * @return Command_Output
	 */
	public function enable_auto_updates( array $arguments, array $options ): Command_Output {
		return $this->switch_auto_updates( $arguments['plugin'], true );
	}

	/**
	 * The "plugin auto-updates disable" command.
	 *
	 * @param array<string, string>      $arguments The plugin.
	 * @param array<string, string|bool> $options   Unused.
	 * @return Command_Output
	 */
	public function disable_auto_updates( array $arguments, array $options ): Command_Output {
		return $this->switch_auto_updates( $arguments['plugin'], false );
	}

	/**
	 * Turn a plugin's automatic updates on or off, as the Plugins screen does.
	 *
	 * @param string $plugin  Plugin name or file.
	 * @param bool   $enabled Whether to turn them on.
	 * @return Command_Output
	 *
	 * @throws Command_Error When the site does not allow it.
	 */
	private function switch_auto_updates( string $plugin, bool $enabled ): Command_Output {
		Site_Changes::require_capability( 'update_plugins', 'change plugin auto-updates' );
		Site_Changes::load_admin_files();
		$file = $this->find( $plugin, get_plugins() );
		$name = self::name( $file );
		if ( ! wp_is_auto_update_enabled_for_type( 'plugin' ) ) {
			throw new Command_Error( 'Automatic updates for plugins are turned off on this site, by AUTOMATIC_UPDATER_DISABLED or DISALLOW_FILE_MODS in wp-config.php, or by a plugin. Nothing was changed.' );
		}
		$option  = Update_Status::auto_update_option( 'plugin' );
		$current = get_site_option( $option, array() );
		$current = is_array( $current ) ? array_values( array_filter( $current, 'is_string' ) ) : array();
		if ( in_array( $file, $current, true ) === $enabled ) {
			return Command_Output::text( sprintf( 'Auto-updates for \'%s\' are already %s. Nothing was changed.', $name, $enabled ? 'on' : 'off' ) );
		}

		return Change_Session::run(
			$this->budget(),
			static function () use ( $option, $current, $file, $name, $enabled ): Command_Output {
				$next = $enabled ? array_merge( $current, array( $file ) ) : array_values( array_diff( $current, array( $file ) ) );
				update_site_option( $option, array_values( array_unique( $next ) ) );
				$saved = get_site_option( $option, array() );
				if ( ( is_array( $saved ) && in_array( $file, $saved, true ) ) !== $enabled ) {
					throw new Command_Error( 'WordPress did not save the change; a plugin may have stopped it. Nothing was changed.' );
				}
				$filter = Update_Status::auto_update_filter( 'plugin' );
				$note   = has_filter( $filter ) ? sprintf( ' Code on this site filters %s, which can override this setting.', $filter ) : '';

				return Command_Output::text( sprintf( 'Auto-updates for \'%s\' are %s.%s', $name, $enabled ? 'on' : 'off', $note ) );
			}
		);
	}

	/**
	 * Why an offered update must not run here, or null.
	 *
	 * @param string               $file  Plugin file.
	 * @param array<string, mixed> $offer WordPress's update offer.
	 * @return string|null
	 */
	private function skip_reason( string $file, array $offer ): ?string {
		if ( self::is_automator( $file ) ) {
			return sprintf( 'Automator cannot replace its own files while it runs this command. Update it from Dashboard › Updates, or turn on its automatic updates with "plugin auto-updates enable %s".', self::name( $file ) );
		}
		$requires_php = isset( $offer['requires_php'] ) && is_scalar( $offer['requires_php'] ) ? (string) $offer['requires_php'] : '';
		if ( '' !== $requires_php && ! is_php_version_compatible( $requires_php ) ) {
			return sprintf( 'The new version needs PHP %s; this site runs PHP %s.', $requires_php, PHP_VERSION );
		}
		$requires = isset( $offer['requires'] ) && is_scalar( $offer['requires'] ) ? (string) $offer['requires'] : '';
		if ( '' !== $requires && ! is_wp_version_compatible( $requires ) ) {
			return sprintf( 'The new version needs WordPress %s; this site runs %s.', $requires, get_bloginfo( 'version' ) );
		}
		if ( ! isset( $offer['package'] ) || ! is_string( $offer['package'] ) || '' === $offer['package'] ) {
			return 'WordPress has no download for this update. Check the plugin\'s update or license settings and its provider account, then check Dashboard › Updates again. Automator cannot determine the license status from a missing download.';
		}
		if ( false === strpos( $file, '/' ) ) {
			return 'WordPress keeps no backup of a plugin that is a single file while it updates it, so it is not updated here. Update it from Dashboard › Updates.';
		}
		if ( Change_Session::has_old_backup( 'plugins', dirname( $file ) ) ) {
			return sprintf( 'WordPress still keeps a backup of this plugin in wp-content/upgrade-temp-backup/plugins/%s from an earlier update that was cut off, and would put it back if this update failed. Once you have checked that the plugin works, remove that folder from the server, then update again.', dirname( $file ) );
		}
		$updater = new \WP_Automatic_Updater();
		if ( $updater->is_vcs_checkout( WP_PLUGIN_DIR . '/' . dirname( $file ) ) ) {
			return 'Its folder is a version-control checkout (such as Git), which an update would overwrite.';
		}

		return null;
	}

	/**
	 * Update one plugin as the Updates screen does. It is downloaded first,
	 * so maintenance mode, which WordPress turns on for an active plugin,
	 * lasts only while its files are unpacked and replaced.
	 *
	 * A failed update's previous version is put back at once, so no later
	 * update in a batch runs while this plugin is broken.
	 *
	 * @param Change_Session $session The session.
	 * @param string         $file    Plugin file.
	 * @return array{0: string, 1: string, 2: bool} Status, detail, and whether a batch must stop.
	 */
	private function update_file( Change_Session $session, string $file ): array {
		$offers     = get_site_transient( 'update_plugins' );
		$offer      = is_object( $offers ) && isset( $offers->response[ $file ] ) ? (object) $offers->response[ $file ] : null;
		$before     = self::version( $file );
		$was_active = is_plugin_active( $file );
		$skin       = new \WP_Ajax_Upgrader_Skin();
		$upgrader   = new \Plugin_Upgrader( $skin );
		$hook_extra = array( 'plugin' => $file );

		$session->expect( self::AFTER_DOWNLOAD_SECONDS, self::UPDATE_REPLACE_SECONDS );
		$session->only_wordpress_org( Packages::is_wordpress_org_offer( $offer ) );
		$download = $session->download( $upgrader, (string) ( $offer->package ?? '' ), $hook_extra );
		if ( is_wp_error( $download ) ) {
			return array_merge( self::failed_step( $download, Site_Changes::download_failure( $download ) ), array( false ) );
		}
		$session->own_maintenance();
		try {
			$result = $upgrader->bulk_upgrade( array( $file ), array( 'clear_update_cache' => false ) );
		} catch ( \Throwable $throwable ) {
			return $this->after_throwable( $session, $upgrader, $file, $before, $throwable );
		} finally {
			// An interrupted update must not leave the site in maintenance mode.
			if ( file_exists( ABSPATH . '.maintenance' ) && ! self::maintenance_is_someone_elses() ) {
				$upgrader->maintenance_mode( false );
			}
		}
		$error      = is_wp_error( $skin->result ) ? $skin->result : ( is_array( $result ) && is_wp_error( $result[ $file ] ?? null ) ? $result[ $file ] : null );
		$error      = $session->explain( $error );
		$failure    = null === $error ? Site_Changes::outcome( $result, $skin, Site_Changes::UPDATE, $file ) : Site_Changes::failure( $error, $skin->get_upgrade_messages(), Site_Changes::UPDATE );
		$up_to_date = 'up_to_date' === $skin->result || ( is_array( $result ) && true === ( $result[ $file ] ?? null ) );
		$outcome    = $session->finish( $upgrader, null === $failure ? null : ( $error ?? new \WP_Error( 'failed', $failure ) ), 'plugins', dirname( $file ), 'plugin' );
		if ( null !== $failure ) {
			wp_clean_plugins_cache( false );
			$moved = Change_Session::STILL_IN_PLACE !== $outcome;
			$step  = self::failed_step( $moved ? null : $error, Site_Changes::with_outcome( $failure, $outcome ) );
			return array( $step[0], $step[1], $moved );
		}
		if ( $up_to_date ) {
			return array( 'up to date', 'WordPress no longer offers this update. Nothing was changed.', false );
		}
		$now  = self::version( $file );
		$note = $now === $before ? sprintf( 'The files were replaced, but the plugin still reports version %s.', $now ) : '';
		if ( ! $was_active ) {
			return array( 'updated', trim( $note . ' ' . $outcome ), false );
		}
		$session->expect( self::CHECK_RESERVE_SECONDS, 0.0 );
		list( $health, $detail ) = Health_Check::run( $session, self::CHECK_RESERVE_SECONDS );
		if ( Health_Check::FATAL === $health ) {
			// Nothing is undone here; the rest of a batch is not attempted.
			return array(
				Packages::SITE_ERROR,
				trim(
					sprintf(
						'The new version %1$s is installed and active, but then %2$s. Nothing was undone. To recover, reinstall %3$s %4$s, the version before this update, for example by uploading its zip under %8$s › Add Plugin › Upload Plugin, or deactivate %3$s under %9$s. If wp-admin does not load, rename %5$s over SFTP or the host\'s file manager so WordPress no longer loads it; the Plugins screen then lists it as deactivated. Reference: %6$s. %7$s',
						$now,
						$detail,
						self::name( $file ),
						$before,
						self::location( $file ),
						Health_Check::REF,
						$outcome,
						is_multisite() ? 'Network Admin › Plugins' : 'Plugins',
						self::plugins_screen( $file )
					)
				),
				true,
			);
		}
		$unchecked = Health_Check::UNCHECKED === $health ? sprintf( 'The site was not checked after the update: %s. Check that the site still loads.', $detail ) : '';

		return array( 'updated', trim( $note . ' ' . $outcome . ' ' . $unchecked ), false );
	}

	/**
	 * What an update that code on the site interrupted left behind. The
	 * batch stops after it.
	 *
	 * @param Change_Session   $session    The session.
	 * @param \Plugin_Upgrader $upgrader   The upgrader.
	 * @param string           $file       Plugin file.
	 * @param string           $before     Version before the update.
	 * @param \Throwable       $throwable  What stopped it.
	 * @return array{0: string, 1: string, 2: bool} Status, detail, and whether a batch must stop.
	 */
	private function after_throwable( Change_Session $session, \Plugin_Upgrader $upgrader, string $file, string $before, \Throwable $throwable ): array {
		$stopped = sprintf( 'Code on this site stopped the update with %s.', get_class( $throwable ) );
		$slug    = dirname( $file );
		if ( false !== has_action( 'shutdown', array( $upgrader, 'restore_temp_backup' ) ) ) {
			// WordPress's update had already failed when the code stopped it.
			$outcome = $session->finish( $upgrader, new \WP_Error( 'failed', '' ), 'plugins', $slug, 'plugin' );
			wp_clean_plugins_cache( false );
			return array( 'failed', $stopped . ' The update had failed. ' . $outcome . ' Check the site before trying again.', true );
		}
		wp_clean_plugins_cache( false );
		$now = self::version( $file );
		if ( $session->files_were_replaced() ) {
			$left = $session->finish( $upgrader, null, 'plugins', $slug, 'plugin' );
			return array( 'updated', trim( $stopped . sprintf( ' The new version %s was already in place; check the plugin with site_command.', $now ) . ' ' . $left ), true );
		}
		if ( Change_Session::has_old_backup( 'plugins', $slug ) && ( $session->moved_aside() || ! is_dir( WP_PLUGIN_DIR . '/' . $slug ) ) ) {
			// WordPress had moved the old version aside, and the new files may be only partly copied.
			$outcome = $session->restore( $upgrader, 'plugins', $slug, 'plugin' );
			wp_clean_plugins_cache( false );
			return array( 'failed', $stopped . ' Its files had been moved aside. ' . $outcome . ( '' !== self::version( $file ) ? ' Check the site with site_command.' : '' ), true );
		}
		$outcome = $session->finish( $upgrader, new \WP_Error( 'failed', '' ), 'plugins', $slug, 'plugin' );
		if ( Change_Session::STILL_IN_PLACE !== $outcome ) {
			return array( 'failed', $stopped . ' ' . $outcome . ' Check the site before trying again.', true );
		}
		if ( '' !== $now && $now === $before ) {
			return array( 'failed', $stopped . ' The previous version is still in place. Check the site before trying again.', true );
		}
		if ( '' !== $now ) {
			return array( 'failed', $stopped . sprintf( ' The plugin\'s files may be partly replaced: it now reports version %s. Check the site before relying on the plugin.', $now ), true );
		}

		return array( 'failed', $stopped . ' The plugin\'s files are missing. Restore them from a backup of the site before relying on the plugin.', true );
	}

	/**
	 * A plugin's installed version, read from its file, or "" when it is missing.
	 *
	 * @param string $file Plugin file.
	 * @return string
	 */
	private static function version( string $file ): string {
		if ( ! file_exists( WP_PLUGIN_DIR . '/' . $file ) ) {
			return '';
		}
		$data = get_plugin_data( WP_PLUGIN_DIR . '/' . $file, false, false );

		return Site_Changes::text( $data['Version'] ?? '' );
	}

	/**
	 * A step's status: not started when it was refused for lack of time.
	 *
	 * @param mixed  $error  The step's error.
	 * @param string $detail Why it failed.
	 * @return array{0: string, 1: string}
	 */
	private static function failed_step( $error, string $detail ): array {
		if ( $error instanceof \WP_Error && Change_Session::OUT_OF_TIME === $error->get_error_code() ) {
			return array( 'not started', $detail );
		}

		return array( 'failed', $detail );
	}

	/**
	 * Whether a .maintenance file may belong to a core update started elsewhere.
	 *
	 * @return bool
	 */
	private static function maintenance_is_someone_elses(): bool {
		// This command holds WordPress's background updater lock, so only a core update can be running.
		$core = Change_Session::lock_time( 'core_updater' );

		return null !== $core && $core > time() - 15 * MINUTE_IN_SECONDS;
	}

	/**
	 * Activate a plugin, or say why it was not, and what that left.
	 *
	 * @param string         $file      Plugin file.
	 * @param Change_Session $session   The session, whose time bounds the check for declarations.
	 * @param bool           $installed Whether this command just installed it.
	 * @return string|null
	 */
	private function activate_file( string $file, Change_Session $session, bool $installed = false ): ?string {
		$this->unscanned = null;
		if ( ! current_user_can( 'activate_plugin', $file ) ) {
			return $installed
				? 'plugin_activation_denied: The plugin was installed but was not activated because your WordPress account cannot activate it.'
				: 'plugin_activation_denied: Your WordPress account cannot activate this plugin.';
		}
		if ( is_multisite() && is_network_only_plugin( $file ) ) {
			return 'it can only be activated across the whole network. It is not active. Activate it from Network Admin › Plugins.';
		}
		$requirements = validate_plugin_requirements( $file );
		if ( is_wp_error( $requirements ) ) {
			return Site_Changes::text( $requirements->get_error_message() ) . ' It is not active.';
		}
		$earlier = self::earlier_fatal( $file );
		if ( null !== $earlier ) {
			return $earlier;
		}
		// The check leaves the time activating the plugin and checking the site need.
		list( $conflicts, $this->unscanned ) = Plugin_Declarations::check(
			$file,
			static function () use ( $session ): float {
				return $session->budget()->remaining() - ( self::ACTIVATE_SECONDS - self::SCAN_SECONDS );
			}
		);
		if ( array() !== $conflicts ) {
			return sprintf(
				'it declares %s, so loading it would stop the site with a PHP fatal error. This usually means two copies of one plugin are installed, such as its free and paid editions, or an old copy beside the current one; deactivate the other copy first. It is not active. Nothing was changed.',
				implode( '; ', array_slice( $conflicts, 0, 5 ) ) . ( count( $conflicts ) > 5 ? sprintf( '; and %d more', count( $conflicts ) - 5 ) : '' )
			);
		}
		self::watch_activation( $file );
		try {
			$result = activate_plugin( $file );
		} catch ( \Throwable $throwable ) {
			return sprintf( 'its activation code stopped with %s.%s', get_class( $throwable ), is_plugin_active( $file ) ? ' It is listed as active; check the site.' : ' It is not active.' );
		} finally {
			// Reached unless PHP stopped the request; then the request's end records it.
			self::$activating = null;
		}
		if ( is_wp_error( $result ) && 'unexpected_output' !== $result->get_error_code() ) {
			return Site_Changes::text( $result->get_error_message() ) . ( is_plugin_active( $file ) ? ' It is listed as active; check the site.' : ' It is not active.' );
		}
		if ( ! is_plugin_active( $file ) ) {
			return 'WordPress reported no error, but the plugin is not active. Its activation code may have run; check the site.';
		}
		$failed = get_option( self::FAILED_ACTIVATION );
		if ( is_array( $failed ) && ( $failed['plugin'] ?? '' ) === $file ) {
			delete_option( self::FAILED_ACTIVATION );
		}

		return null;
	}

	/**
	 * The output with a warning when the check for declarations that already
	 * exist did not finish.
	 *
	 * @param Command_Output $output The output.
	 * @return Command_Output
	 */
	private function with_unscanned( Command_Output $output ): Command_Output {
		return null === $this->unscanned ? $output : $output->with_warning( $this->unscanned_note() );
	}

	/**
	 * Why the plugin was activated without a finished check for declarations
	 * that already exist, as a sentence.
	 *
	 * @return string
	 */
	private function unscanned_note(): string {
		return sprintf( 'Automator did not finish checking whether it declares functions or classes that already exist, because %s, so it was activated without that check.', (string) $this->unscanned );
	}

	/**
	 * Check the site after a plugin was activated. Nothing is undone: when
	 * the site then stops with a PHP fatal error, the answer says so and how
	 * to recover by hand.
	 *
	 * @param Change_Session $session The session.
	 * @param string         $file    Plugin file.
	 * @return array{0: string|null, 1: string|null} What happened when the site broke, and a warning when it could not be checked.
	 */
	private function check_site( Change_Session $session, string $file ): array {
		$session->expect( self::CHECK_RESERVE_SECONDS, 0.0 );
		list( $health, $detail ) = Health_Check::run( $session, self::CHECK_RESERVE_SECONDS );
		if ( Health_Check::UNCHECKED === $health ) {
			return array( null, sprintf( 'The site was not checked after the activation: %s. Check that the site still loads.', $detail ) );
		}
		if ( Health_Check::HEALTHY === $health ) {
			return array( null, null );
		}

		return array(
			sprintf(
				'Plugin \'%1$s\' was activated, but then %2$s. It is still active; nothing was undone. To recover, deactivate %1$s under %5$s in WordPress. If wp-admin does not load, rename %3$s over SFTP or the host\'s file manager so WordPress no longer loads it; the Plugins screen then lists it as deactivated. Reference: %4$s.',
				self::name( $file ),
				$detail,
				self::location( $file ),
				Health_Check::REF,
				self::plugins_screen( $file )
			),
			null,
		);
	}

	/**
	 * The screen that can deactivate a plugin: only Network Admin can for one
	 * active across a network.
	 *
	 * @param string $file Plugin file.
	 * @return string
	 */
	private static function plugins_screen( string $file ): string {
		return is_multisite() && is_plugin_active_for_network( $file ) ? 'Network Admin › Plugins' : 'Plugins';
	}

	/**
	 * Where a plugin's files are, as a site owner finds them over SFTP.
	 *
	 * @param string $file Plugin file.
	 * @return string
	 */
	private static function location( string $file ): string {
		return '.' === dirname( $file )
			? sprintf( 'the file %s', Site_Changes::text( WP_PLUGIN_DIR . '/' . $file ) )
			: sprintf( 'the folder %s', Site_Changes::text( WP_PLUGIN_DIR . '/' . dirname( $file ) ) );
	}

	/**
	 * Remember which plugin is being activated, so a fatal error that stops
	 * the request while its code loads is recorded when the request ends.
	 *
	 * @param string $file Plugin file.
	 * @return void
	 */
	private static function watch_activation( string $file ): void {
		self::$activating = $file;
		if ( ! self::$watching ) {
			self::$watching = true;
			register_shutdown_function( array( self::class, 'after_activation' ) );
		}
	}

	/**
	 * At the end of the request: if a plugin's activation was stopped by a
	 * PHP fatal error, record it.
	 *
	 * @return void
	 */
	public static function after_activation(): void {
		$file             = self::$activating;
		self::$activating = null;
		$error            = error_get_last();
		if ( null !== $file && is_array( $error ) && in_array( $error['type'] ?? 0, self::FATAL_TYPES, true ) ) {
			self::record_fatal( $file, $error );
		}
	}

	/**
	 * Record a fatal error that stopped a plugin's activation, so the next
	 * activation explains it instead of stopping the site's answer again.
	 * Nothing is undone.
	 *
	 * @param string               $file  Plugin file.
	 * @param array<string, mixed> $error The error, from error_get_last().
	 * @return void
	 */
	public static function record_fatal( string $file, array $error ): void {
		try {
			update_option(
				self::FAILED_ACTIVATION,
				array(
					'plugin' => $file,
					'time'   => time(),
					'error'  => Health_Check::safe_error( $error ),
					'state'  => self::activation_state( $file ),
				),
				false
			);
		} catch ( \Throwable $failure ) {
			// The request is ending after a fatal error; nothing more can be done.
			unset( $failure );
		}
	}

	/**
	 * Why a plugin is not activated again: its last activation from here, in
	 * the last hour, stopped the request with a fatal error, and neither its
	 * files nor the active plugins and theme changed since.
	 *
	 * @param string $file Plugin file.
	 * @return string|null
	 */
	private static function earlier_fatal( string $file ): ?string {
		$failed = get_option( self::FAILED_ACTIVATION );
		if ( ! is_array( $failed ) || ( $failed['plugin'] ?? '' ) !== $file || ! is_numeric( $failed['time'] ?? null ) ) {
			return null;
		}
		$time = (int) $failed['time'];
		if ( $time < time() - self::FAILED_ACTIVATION_SECONDS || ( $failed['state'] ?? '' ) !== self::activation_state( $file ) ) {
			return null;
		}

		return sprintf(
			'its last activation from here, at %1$s UTC, stopped the site\'s answer with a PHP fatal error: %2$s. It was left inactive. Ask the host or developer to check the site\'s PHP error log, or change what caused it, before activating it again; it is not tried again before %3$s UTC while the plugin, the active plugins and the theme stay as they are. Nothing was changed.',
			gmdate( 'Y-m-d H:i', $time ),
			Health_Check::describe( is_array( $failed['error'] ?? null ) ? $failed['error'] : array() ),
			gmdate( 'H:i', $time + self::FAILED_ACTIVATION_SECONDS )
		);
	}

	/**
	 * What an activation depends on: the plugin's files, the active plugins
	 * and the theme.
	 *
	 * @param string $file Plugin file.
	 * @return string
	 */
	private static function activation_state( string $file ): string {
		clearstatcache();

		return md5( wp_json_encode( array( self::files_state( $file ), get_option( 'active_plugins' ), get_site_option( 'active_sitewide_plugins' ), get_stylesheet() ) ) );
	}

	/**
	 * How many files a plugin has and when the newest changed, so a fix in
	 * any of them, not only the main file, counts as a change. A folder too
	 * large to look through in passing counts as changed every time.
	 *
	 * @param string $file Plugin file.
	 * @return string
	 */
	private static function files_state( string $file ): string {
		$path = WP_PLUGIN_DIR . '/' . $file;
		if ( '.' === dirname( $file ) ) {
			return file_exists( $path ) ? filemtime( $path ) . ':' . filesize( $path ) : 'missing';
		}
		$files  = 0;
		$newest = 0;
		try {
			$walk = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( dirname( $path ), \FilesystemIterator::SKIP_DOTS ) );
			foreach ( $walk as $entry ) {
				if ( ++$files > self::STATE_MAX_FILES ) {
					return uniqid( 'unknown', true );
				}
				$newest = max( $newest, (int) $entry->getMTime() );
			}
		} catch ( \Throwable $failure ) {
			return uniqid( 'unknown', true );
		}

		return $files . ':' . $newest;
	}

	/**
	 * Names of active plugins that require this one, as the Plugins screen
	 * knows them.
	 *
	 * @param string $file Plugin file.
	 * @return string[]
	 */
	private static function active_dependents( string $file ): array {
		if ( ! class_exists( '\WP_Plugin_Dependencies' ) ) {
			return array();
		}
		\WP_Plugin_Dependencies::initialize();
		if ( ! \WP_Plugin_Dependencies::has_active_dependents( $file ) ) {
			return array();
		}

		return array_map( array( Site_Changes::class, 'text' ), (array) \WP_Plugin_Dependencies::get_dependent_names( $file ) );
	}

	/**
	 * Whether a plugin file is Automator itself or Automator Pro, wherever
	 * their folders are.
	 *
	 * @param string $file Plugin file.
	 * @return bool
	 */
	private static function is_automator( string $file ): bool {
		$own = array( plugin_basename( AUTOMATOR_BASE_FILE ) );
		if ( defined( 'AUTOMATOR_PRO_FILE' ) ) {
			$own[] = plugin_basename( (string) AUTOMATOR_PRO_FILE );
		}

		return in_array( $file, $own, true ) || in_array( self::name( $file ), array( 'uncanny-automator', 'uncanny-automator-pro' ), true );
	}

	/**
	 * An installed regular plugin by name or file, as WP-CLI finds it.
	 *
	 * @param string                              $name    Plugin name or file.
	 * @param array<string, array<string, mixed>> $plugins Installed plugins.
	 * @return string The plugin file.
	 *
	 * @throws Command_Error When it is not installed.
	 */
	private function find( string $name, array $plugins ): string {
		foreach ( array_keys( $plugins ) as $file ) {
			if ( $file === $name || self::name( $file ) === $name ) {
				return $file;
			}
		}
		$hint = array_key_exists( $name, get_mu_plugins() ) || array_key_exists( $name, get_dropins() )
			? ' Must-use plugins and drop-ins cannot be changed here.'
			: ' Run "plugin list" in site_command to see installed plugins.';

		throw new Command_Error( sprintf( "The '%s' plugin could not be found.%s Nothing was changed.", $name, $hint ) );
	}

	/**
	 * A plugin's name, as WP-CLI uses it: its folder, or its file without .php.
	 *
	 * @param string $file Plugin file.
	 * @return string
	 */
	private static function name( string $file ): string {
		$folder = dirname( $file );

		return '.' === $folder ? basename( $file, '.php' ) : $folder;
	}

	/**
	 * A fresh time budget for one command.
	 *
	 * @return Time_Budget
	 */
	private function budget(): Time_Budget {
		return call_user_func( $this->budget );
	}
}
