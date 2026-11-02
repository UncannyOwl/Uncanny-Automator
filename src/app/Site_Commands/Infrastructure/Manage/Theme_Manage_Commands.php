<?php
/**
 * The "theme" commands that change the site.
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
use Uncanny_Automator\App\Site_Commands\Infrastructure\Request_Time_Budget;
use Uncanny_Automator\App\Site_Commands\Ports\Site_Command_Group;

/**
 * Install, update and activate themes, as WP-CLI does. Nothing is deleted.
 * Themes come only from WordPress.org, a parent theme's included, or from
 * the updates WordPress already offers.
 */
final class Theme_Manage_Commands implements Site_Command_Group {

	/** Time to replace a theme's files and answer. */
	private const REPLACE_SECONDS = 10.0;

	/** Time an update needs to replace the files, then remove the backup or put it back, and answer. */
	private const UPDATE_REPLACE_SECONDS = self::REPLACE_SECONDS + Change_Session::CLEANUP_SECONDS;

	/** Time an update needs once its download finished. */
	private const AFTER_DOWNLOAD_SECONDS = Change_Session::UNZIP_SECONDS + self::UPDATE_REPLACE_SECONDS;

	/** Time an install needs once its download finished; it has no backup. */
	private const INSTALL_AFTER_DOWNLOAD_SECONDS = Change_Session::UNZIP_SECONDS + self::REPLACE_SECONDS;

	/** Time to download, unpack and replace one theme, and answer. */
	private const UPDATE_SECONDS = self::AFTER_DOWNLOAD_SECONDS + 4.0;

	/** Time to look a theme up on WordPress.org, then install it. */
	private const INSTALL_SECONDS = self::INSTALL_AFTER_DOWNLOAD_SECONDS + 7.0;

	/** Time to switch themes, whose hooks run then, and answer. */
	private const SWITCH_SECONDS = 10.0;

	/** Time kept after checking the site, to answer. */
	private const CHECK_RESERVE_SECONDS = 1.0;

	/** Time to switch themes, check the site, and answer. */
	private const ACTIVATE_SECONDS = self::SWITCH_SECONDS + Health_Check::SECONDS + self::CHECK_RESERVE_SECONDS;

	/** Said of a theme no update source reports on. */
	private const NOT_TRACKED_DETAIL = 'No update source reports on this theme: WordPress.org does not list it, and no update provider on this site answered for it, so its updates cannot be found here. For a premium theme, check its license and update settings. Nothing was changed.';

	private const UPDATE_FIELDS = array( 'name', 'old_version', 'new_version', 'status', 'detail' );

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
		return array(
			Command_Definition::changes( 'theme install', 'Install a theme from WordPress.org by its slug, with its parent theme if it needs one and time allows. Refused if it is already installed; nothing is overwritten.' )
				->with_argument( Command_Argument::required( 'slug', 'WordPress.org theme slug, such as twentytwentyfive.' ) )
				->with_option( Command_Option::flag( 'activate', 'Activate the theme after installing it.' ) )
				->handled_by( array( $this, 'install' ) ),
			Command_Definition::changes( 'theme update', 'Update one theme, or every theme with --all, to the version WordPress offers. Themes are updated one at a time; when too little time is left for the next one, the rest are listed as not started, so run the command again.' )
				->with_argument( Command_Argument::optional( 'theme', 'Installed theme folder name, such as twentytwentyfive. Omit it with --all.' ) )
				->with_option( Command_Option::flag( 'all', 'Update every theme that has an update.' ) )
				->with_option( Command_Option::flag( 'dry-run', 'Only list what would be updated, and why anything would be skipped.' ) )
				->with_fields( self::UPDATE_FIELDS, self::UPDATE_FIELDS, 'Themes' )
				->handled_by( array( $this, 'update' ) ),
			Command_Definition::changes( 'theme activate', 'Switch the site to an installed theme. The previous theme stays installed.' )
				->with_argument( Command_Argument::required( 'theme', 'Installed theme folder name, such as twentytwentyfive.' ) )
				->handled_by( array( $this, 'activate' ) ),
			Command_Definition::changes( 'theme auto-updates enable', 'Turn on WordPress\'s automatic updates for a theme.' )
				->with_argument( Command_Argument::required( 'theme', 'Installed theme folder name, such as twentytwentyfive.' ) )
				->handled_by(
					function ( array $arguments ): Command_Output {
						return $this->switch_auto_updates( $arguments['theme'], true );
					}
				),
			Command_Definition::changes( 'theme auto-updates disable', 'Turn off WordPress\'s automatic updates for a theme.' )
				->with_argument( Command_Argument::required( 'theme', 'Installed theme folder name, such as twentytwentyfive.' ) )
				->handled_by(
					function ( array $arguments ): Command_Output {
						return $this->switch_auto_updates( $arguments['theme'], false );
					}
				),
		);
	}

	/**
	 * The "theme install" command.
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
			throw new Command_Error( sprintf( '"%s" is not a WordPress.org theme slug: use lowercase letters, numbers and hyphens, such as twentytwentyfive. Themes install only from WordPress.org. Nothing was changed.', $slug ) );
		}
		Site_Changes::require_capability( 'install_themes', 'install themes' );
		if ( $activate ) {
			Site_Changes::require_capability( 'switch_themes', 'switch themes' );
		}
		Site_Changes::load_admin_files();
		$existing = wp_get_theme( $slug );
		if ( $existing->exists() && ! $existing->errors() ) {
			throw new Command_Error( sprintf( 'The theme %1$s is already installed (version %2$s). Nothing was changed. Run "theme update %1$s" to update it.', $slug, Site_Changes::text( $existing->get( 'Version' ) ) ) );
		}
		$errors = $existing->errors();
		if ( $existing->exists() && $errors instanceof \WP_Error && 'theme_no_parent' === $errors->get_error_code() ) {
			throw new Command_Error( sprintf( 'The theme %1$s is already installed, but its parent theme %2$s is missing, so the site cannot use it. Nothing was changed. Run "theme install %2$s".', $slug, $existing->get_template() ) );
		}
		if ( $existing->exists() && $errors instanceof \WP_Error ) {
			throw new Command_Error( sprintf( 'The folder wp-content/themes/%1$s holds a theme WordPress cannot use: %2$s. Nothing was changed. Fix or remove that folder on the server, then install again.', $slug, rtrim( Site_Changes::text( $errors->get_error_message() ), '. ' ) ) );
		}
		if ( file_exists( get_theme_root() . '/' . $slug ) ) {
			throw new Command_Error( sprintf( 'The folder wp-content/themes/%s exists but holds no theme WordPress can use, possibly left by an interrupted install. Nothing was changed. Remove that folder from the server, then install again.', $slug ) );
		}
		Site_Changes::require_file_changes( get_theme_root(), 'Appearance › Themes › Add Theme' );
		Change_Session::require_no_other_updates();

		return Change_Session::run(
			$this->budget(),
			function ( Change_Session $session ) use ( $slug, $activate ): Command_Output {
				$session->budget()->require( self::INSTALL_SECONDS, sprintf( 'Installing %s', $slug ), 'Appearance › Themes › Add Theme' );
				$session->lock();
				$session->only_wordpress_org();
				$session->expect( self::INSTALL_AFTER_DOWNLOAD_SECONDS, self::REPLACE_SECONDS );

				$api = themes_api(
					'theme_information',
					array(
						'slug'   => $slug,
						'fields' => array(
							'sections'     => false,
							'tags'         => false,
							'requires'     => true,
							'requires_php' => true,
						),
					)
				);
				if ( is_wp_error( $api ) ) {
					throw new Command_Error( Packages::lookup_failure( $session->explain( $api ), $slug, 'theme' ) );
				}
				Packages::require_installable( $api, $slug, 'theme' );

				// WordPress's theme upgrader sets the skin's $api when it installs a parent theme.
				$skin     = new class() extends \WP_Ajax_Upgrader_Skin {
					/**
					 * The parent theme's information.
					 *
					 * @var mixed
					 */
					public $api;
				};
				$upgrader = new \Theme_Upgrader( $skin );
				$result   = $upgrader->install( (string) $api->download_link );
				$theme    = $upgrader->theme_info();
				$failure  = Site_Changes::outcome( $session->explain( $result ), $skin, Site_Changes::INSTALL );
				if ( ! $theme instanceof \WP_Theme || ! $theme->exists() ) {
					throw new Command_Error( sprintf( 'Installing %s failed: %s', $slug, $failure ?? 'WordPress did not report the installed theme. Check with site_command.' ) );
				}

				$name   = $theme->get_stylesheet();
				$text   = sprintf( 'Installed %s %s (%s).', Site_Changes::text( $theme->get( 'Name' ) ), Site_Changes::text( $theme->get( 'Version' ) ), $name );
				$parent = $theme->get_template();
				if ( $parent !== $name && ! wp_get_theme( $parent )->exists() ) {
					// WordPress installs a missing parent after the theme itself.
					$errors = $skin->get_errors();
					$reason = $errors->has_errors() ? ': ' . rtrim( Site_Changes::text( $errors->get_error_message() ), '. ' ) : '';
					return Command_Output::text( $text )->with_warning( sprintf( 'Its parent theme %1$s could not be installed%2$s, so the site cannot use this theme yet. Run "theme install %1$s". It was not activated.', $parent, $reason ) );
				}
				if ( ! $activate ) {
					return Command_Output::text( $text . ' It is not active; run "theme activate ' . $name . '" to switch to it.' );
				}
				$session->expect( 0.0, 0.0 );
				if ( ! $session->budget()->allows( self::ACTIVATE_SECONDS ) ) {
					return Command_Output::text( $text )->with_warning( 'It was not activated: ' . $session->budget()->shortfall( self::ACTIVATE_SECONDS, 'Switching to it and checking the site' ) . ' Run "theme activate ' . $name . '".' );
				}
				$previous = get_stylesheet();
				$switch   = $this->switch_to( $name );
				if ( get_stylesheet() !== $name ) {
					return Command_Output::text( $text )->with_warning( 'It was installed but not activated: ' . ( $switch ?? 'the site still uses another theme.' ) );
				}
				list( $broke, $unchecked ) = $this->check_site( $session, $name, $previous );
				if ( null !== $broke ) {
					return Command_Output::text( $text . ' The site now uses it.' )->with_warning( $broke )->with_failure();
				}
				$result = Command_Output::text( $text . ' The site now uses it.' );
				if ( null !== $switch ) {
					$result = $result->with_warning( 'WordPress reported a problem while switching to it: ' . $switch );
				}

				return null === $unchecked ? $result : $result->with_warning( $unchecked );
			}
		);
	}

	/**
	 * The "theme update" command.
	 *
	 * @param array<string, string>      $arguments The theme, unless --all.
	 * @param array<string, string|bool> $options   Validated options.
	 * @return Command_Output
	 *
	 * @throws Command_Error When nothing can be updated.
	 */
	public function update( array $arguments, array $options ): Command_Output {
		$all     = true === ( $options['all'] ?? false );
		$dry_run = true === ( $options['dry-run'] ?? false );
		$named   = $arguments['theme'] ?? null;
		if ( ( null !== $named ) === $all ) {
			throw new Command_Error( 'Name one theme, or use --all, but not both. Usage: theme update [<theme>] [--all] [--dry-run]. Nothing was changed.' );
		}
		Site_Changes::require_capability( 'update_themes', 'update themes' );
		Site_Changes::load_admin_files();
		$themes = wp_get_themes();
		if ( null !== $named && ! isset( $themes[ $named ] ) ) {
			throw new Command_Error( sprintf( "The '%s' theme could not be found. Run \"theme list\" in site_command to see installed themes. Nothing was changed.", $named ) );
		}
		$names = null === $named ? array_map( 'strval', array_keys( $themes ) ) : array( $named );
		if ( ! $dry_run ) {
			Site_Changes::require_file_changes( get_theme_root() );
			Change_Session::require_no_other_updates();
		}

		return Change_Session::run(
			$this->budget(),
			function ( Change_Session $session ) use ( $named, $dry_run, $themes, $names ): Command_Output {
				$session->budget()->require( $dry_run ? 5.0 : self::UPDATE_SECONDS, null === $named ? 'Updating themes' : sprintf( 'Updating %s', $named ) );
				if ( ! $dry_run ) {
					$session->lock();
				}
				$installed = null === $named ? '' : Site_Changes::text( $themes[ $named ]->get( 'Version' ) );
				// An update WordPress already offers for the named theme needs no new check.
				list( $current, $warnings ) = null !== $named && Packages::offers_newer( 'update_themes', $named, $installed )
					? array( true, array() )
					: Packages::refresh( 'theme', $session, null === $named ? array() : Packages::update_hosts( 'update_themes', $named, Site_Changes::text( $themes[ $named ]->get( 'UpdateURI' ) ) ) );
				$check                      = get_site_transient( 'update_themes' );

				$rows    = array();
				$pending = array();
				foreach ( $names as $name ) {
					$row                             = array(
						'name'        => $name,
						'old_version' => Site_Changes::text( $themes[ $name ]->get( 'Version' ) ),
						'new_version' => '',
						'status'      => 'unknown',
						'detail'      => 'WordPress returned no usable update information for this installed version. This does not mean the theme is up to date. Check Dashboard › Updates and the theme\'s update settings. For a premium theme, check its provider account and license status. Nothing was changed.',
					);
					list( $state, $version, $offer ) = Packages::classify( $check, $name, $row['old_version'], $current );
					if ( 'up to date' === $state ) {
						if ( null !== $named ) {
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
						$row['detail'] = 'WordPress\'s no-update answer is stale, its refresh failed, or it was checked for a different installed version. Current update availability could not be determined. Check Dashboard › Updates and the theme\'s update settings. Nothing was changed.';
					}
					if ( null === $offer ) {
						$rows[] = $row;
						continue;
					}
					$row['new_version'] = $version;
					$skip               = $this->skip_reason( $themes[ $name ], $offer );
					if ( null === $skip && 'withheld' === $state ) {
						$skip = 'WordPress reports a newer version but has withheld this update. Check Dashboard › Updates and the theme\'s update settings for its requirements.';
					}
					if ( null !== $skip ) {
						$row['status'] = 'skipped';
						$row['detail'] = $skip;
						$rows[]        = $row;
						continue;
					}
					$row['status']    = $dry_run ? 'available' : 'pending';
					$row['detail']    = '';
					$pending[ $name ] = array( count( $rows ), (string) $offer['package'], Packages::is_wordpress_org_offer( $offer ) );
					$rows[]           = $row;
				}

				if ( ! $dry_run ) {
					$updated = array();
					$stopped = null;
					foreach ( $pending as $name => list( $index, $package, $from_wordpress_org ) ) {
						if ( null !== $stopped ) {
							$rows[ $index ]['status'] = 'not started';
							$rows[ $index ]['detail'] = $stopped;
							continue;
						}
						if ( ! $session->budget()->allows( self::UPDATE_SECONDS ) ) {
							$rows[ $index ]['status'] = 'not started';
							$rows[ $index ]['detail'] = $session->budget()->shortfall( self::UPDATE_SECONDS, 'This update' ) . ' ' . $session->budget()->next_step( self::UPDATE_SECONDS );
							$stopped                  = $rows[ $index ]['detail'];
							continue;
						}
						$session->only_wordpress_org( $from_wordpress_org );
						list( $status, $detail, $stop ) = $this->update_theme( $session, (string) $name, $package );
						if ( $stop ) {
							$stopped = Packages::SITE_ERROR === $status
								? sprintf( 'Not attempted: the batch stopped because the site stopped with a PHP fatal error after the theme %s was updated. Recover the site as that row says, then run the command again. Reference: %s.', $name, Health_Check::REF )
								: 'The batch stopped after an earlier update failed or was interrupted. Check the site, then run the command again.';
						}
						$rows[ $index ]['status'] = $status;
						$rows[ $index ]['detail'] = $detail;
						if ( in_array( $status, array( 'updated', Packages::SITE_ERROR ), true ) ) {
							$updated[] = (string) $name;
						}
					}
					$session->expect( 0.0, 0.0 );
					Packages::forget_offers( 'update_themes', $updated );
				}

				if ( null !== $named && Packages::SITE_ERROR === $rows[0]['status'] ) {
					throw new Command_Error( sprintf( 'The theme %s was updated from %s to %s. %s', $rows[0]['name'], $rows[0]['old_version'], $rows[0]['new_version'], $rows[0]['detail'] ) );
				}
				if ( null !== $named && 'failed' === $rows[0]['status'] ) {
					throw new Command_Error( sprintf( 'Updating %s from %s to %s failed: %s', $rows[0]['name'], $rows[0]['old_version'], $rows[0]['new_version'], $rows[0]['detail'] ) );
				}
				if ( null !== $named && in_array( $rows[0]['status'], array( 'skipped', 'not started' ), true ) ) {
					throw new Command_Error( sprintf( '%s was not updated: %s', $rows[0]['name'], $rows[0]['detail'] ) . ( false === strpos( $rows[0]['detail'], 'Nothing was changed.' ) ? ' Nothing was changed.' : '' ) );
				}

				return Packages::output( $rows, $dry_run, $warnings, 'updated', null !== $named );
			}
		);
	}

	/**
	 * The "theme activate" command.
	 *
	 * @param array<string, string>      $arguments The theme.
	 * @param array<string, string|bool> $options   Unused.
	 * @return Command_Output
	 *
	 * @throws Command_Error When it cannot be activated.
	 */
	public function activate( array $arguments, array $options ): Command_Output {
		Site_Changes::require_capability( 'switch_themes', 'switch themes' );
		Site_Changes::load_admin_files();
		$name  = $arguments['theme'];
		$theme = wp_get_theme( $name );
		if ( ! self::is_installed( $name ) ) {
			throw new Command_Error( sprintf( "The '%s' theme could not be found. Run \"theme list\" in site_command to see installed themes. Nothing was changed.", $name ) );
		}
		if ( get_stylesheet() === $theme->get_stylesheet() ) {
			return Command_Output::text( sprintf( 'The site already uses the \'%s\' theme. Nothing was changed.', $name ) );
		}

		return Change_Session::run(
			$this->budget(),
			function ( Change_Session $session ) use ( $name, $theme ): Command_Output {
				$session->budget()->require( self::ACTIVATE_SECONDS, sprintf( 'Switching to %s and checking the site', $name ), 'Appearance › Themes' );
				$session->lock();
				$previous = get_stylesheet();
				$failure  = $this->switch_to( $theme->get_stylesheet() );
				if ( get_stylesheet() !== $theme->get_stylesheet() ) {
					throw new Command_Error( sprintf( 'The site was not switched to \'%s\': %s', $name, $failure ?? 'WordPress reported no error, but the site still uses another theme. Check the site.' ) );
				}
				list( $broke, $unchecked ) = $this->check_site( $session, $theme->get_stylesheet(), $previous );
				if ( null !== $broke ) {
					throw new Command_Error( $broke );
				}
				$result = Command_Output::text( sprintf( 'The site now uses the \'%s\' theme.', $name ) );
				if ( null !== $failure ) {
					$result = $result->with_warning( 'WordPress reported a problem while switching to it: ' . $failure );
				}

				return null === $unchecked ? $result : $result->with_warning( $unchecked );
			}
		);
	}

	/**
	 * Turn a theme's automatic updates on or off, as the Themes screen does.
	 *
	 * @param string $name    Theme folder name.
	 * @param bool   $enabled Whether to turn them on.
	 * @return Command_Output
	 *
	 * @throws Command_Error When the site does not allow it.
	 */
	private function switch_auto_updates( string $name, bool $enabled ): Command_Output {
		Site_Changes::require_capability( 'update_themes', 'change theme auto-updates' );
		if ( ! self::is_installed( $name ) ) {
			throw new Command_Error( sprintf( "The '%s' theme could not be found. Run \"theme list\" in site_command to see installed themes. Nothing was changed.", $name ) );
		}
		Site_Changes::load_admin_files();
		if ( ! wp_is_auto_update_enabled_for_type( 'theme' ) ) {
			throw new Command_Error( 'Automatic updates for themes are turned off on this site, by AUTOMATIC_UPDATER_DISABLED or DISALLOW_FILE_MODS in wp-config.php, or by a plugin. Nothing was changed.' );
		}
		$current = get_site_option( 'auto_update_themes', array() );
		$current = is_array( $current ) ? array_values( array_filter( $current, 'is_string' ) ) : array();
		if ( in_array( $name, $current, true ) === $enabled ) {
			return Command_Output::text( sprintf( 'Auto-updates for the \'%s\' theme are already %s. Nothing was changed.', $name, $enabled ? 'on' : 'off' ) );
		}

		return Change_Session::run(
			$this->budget(),
			static function () use ( $current, $name, $enabled ): Command_Output {
				$next = $enabled ? array_merge( $current, array( $name ) ) : array_values( array_diff( $current, array( $name ) ) );
				update_site_option( 'auto_update_themes', array_values( array_unique( $next ) ) );
				$saved = get_site_option( 'auto_update_themes', array() );
				if ( ( is_array( $saved ) && in_array( $name, $saved, true ) ) !== $enabled ) {
					throw new Command_Error( 'WordPress did not save the change; a theme or plugin may have stopped it. Nothing was changed.' );
				}
				$note = has_filter( 'auto_update_theme' ) ? ' Code on this site filters auto_update_theme, which can override this setting.' : '';

				return Command_Output::text( sprintf( 'Auto-updates for the \'%s\' theme are %s.%s', $name, $enabled ? 'on' : 'off', $note ) );
			}
		);
	}

	/**
	 * Why an offered update must not run here, or null.
	 *
	 * @param \WP_Theme            $theme Installed theme.
	 * @param array<string, mixed> $offer WordPress's update offer.
	 * @return string|null
	 */
	private function skip_reason( \WP_Theme $theme, array $offer ): ?string {
		$requires_php = isset( $offer['requires_php'] ) && is_scalar( $offer['requires_php'] ) ? (string) $offer['requires_php'] : '';
		if ( '' !== $requires_php && ! is_php_version_compatible( $requires_php ) ) {
			return sprintf( 'The new version needs PHP %s; this site runs PHP %s.', $requires_php, PHP_VERSION );
		}
		$requires = isset( $offer['requires'] ) && is_scalar( $offer['requires'] ) ? (string) $offer['requires'] : '';
		if ( '' !== $requires && ! is_wp_version_compatible( $requires ) ) {
			return sprintf( 'The new version needs WordPress %s; this site runs %s.', $requires, get_bloginfo( 'version' ) );
		}
		if ( ! isset( $offer['package'] ) || ! is_string( $offer['package'] ) || '' === $offer['package'] ) {
			return 'WordPress has no download for this update. Premium themes often need their license activated before they can update.';
		}
		if ( Change_Session::has_old_backup( 'themes', $theme->get_stylesheet() ) ) {
			return sprintf( 'WordPress still keeps a backup of this theme in wp-content/upgrade-temp-backup/themes/%s from an earlier update that was cut off, and would put it back if this update failed. Once you have checked that the theme works, remove that folder from the server, then update again.', $theme->get_stylesheet() );
		}
		$updater = new \WP_Automatic_Updater();
		if ( $updater->is_vcs_checkout( $theme->get_stylesheet_directory() ) ) {
			return 'Its folder is a version-control checkout (such as Git), which an update would overwrite.';
		}

		return null;
	}

	/**
	 * Update one theme as the Updates screen does, downloading it first so
	 * maintenance mode lasts only while it is unpacked and replaced. A failed
	 * update's previous version is put back at once.
	 *
	 * @param Change_Session $session The session.
	 * @param string         $name    Theme folder name.
	 * @param string         $package Its update package.
	 * @return array{0: string, 1: string, 2: bool} Status, detail, and whether a batch must stop.
	 */
	private function update_theme( Change_Session $session, string $name, string $package ): array {
		$before   = self::version( $name );
		$skin     = new \WP_Ajax_Upgrader_Skin();
		$upgrader = new \Theme_Upgrader( $skin );
		$in_use   = in_array( $name, array( get_stylesheet(), get_template() ), true );

		$session->expect( self::AFTER_DOWNLOAD_SECONDS, self::UPDATE_REPLACE_SECONDS );
		$download = $session->download( $upgrader, $package, array( 'theme' => $name ) );
		if ( is_wp_error( $download ) ) {
			return array( Change_Session::OUT_OF_TIME === $download->get_error_code() ? 'not started' : 'failed', Site_Changes::failure( $download, array(), Site_Changes::UPDATE ), false );
		}
		$session->own_maintenance();
		try {
			$result = $upgrader->bulk_upgrade( array( $name ), array( 'clear_update_cache' => false ) );
		} catch ( \Throwable $throwable ) {
			return $this->after_throwable( $session, $upgrader, $name, $before, $throwable );
		} finally {
			// An interrupted update must not leave the site in maintenance mode.
			if ( file_exists( ABSPATH . '.maintenance' ) && ! self::maintenance_is_someone_elses() ) {
				$upgrader->maintenance_mode( false );
			}
		}
		$error   = is_wp_error( $skin->result ) ? $skin->result : ( is_array( $result ) && is_wp_error( $result[ $name ] ?? null ) ? $result[ $name ] : null );
		$error   = $session->explain( $error );
		$failure = null === $error ? Site_Changes::outcome( $result, $skin, Site_Changes::UPDATE, $name ) : Site_Changes::failure( $error, $skin->get_upgrade_messages(), Site_Changes::UPDATE );
		// WordPress's theme upgrader reports an update it no longer offers as true.
		$up_to_date = 'up_to_date' === $skin->result || true === $skin->result || ( is_array( $result ) && true === ( $result[ $name ] ?? null ) );
		$outcome    = $session->finish( $upgrader, null === $failure ? null : ( $error ?? new \WP_Error( 'failed', $failure ) ), 'themes', $name, 'theme' );
		wp_clean_themes_cache( false );
		if ( null !== $failure ) {
			$moved = Change_Session::STILL_IN_PLACE !== $outcome;
			return array( ! $moved && $error instanceof \WP_Error && Change_Session::OUT_OF_TIME === $error->get_error_code() ? 'not started' : 'failed', Site_Changes::with_outcome( $failure, $outcome ), $moved );
		}
		if ( $up_to_date ) {
			return array( 'up to date', 'WordPress no longer offers this update. Nothing was changed.', false );
		}
		$now  = self::version( $name );
		$note = $now === $before ? sprintf( 'The files were replaced, but the theme still reports version %s.', $now ) : '';
		if ( ! $in_use ) {
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
						'The new version %1$s is installed and in use, but then %2$s. Nothing was undone. To recover, reinstall the theme %3$s %4$s, the version before this update, for example by uploading its zip under %9$s, or switch to another theme under Appearance › Themes. If wp-admin does not load, rename %5$s over SFTP or the host\'s file manager so its code no longer runs, make sure another working theme is installed, such as a default Twenty-something theme from WordPress.org uploaded into %6$s%10$s, and choose it under Appearance › Themes once the dashboard loads. Reference: %7$s. %8$s',
						$now,
						$detail,
						$name,
						$before,
						self::location( $name ),
						self::themes_folder( $name ),
						Health_Check::REF,
						$outcome,
						self::upload_screen(),
						self::enable_step()
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
	 * @param Change_Session  $session   The session.
	 * @param \Theme_Upgrader $upgrader  The upgrader.
	 * @param string          $name      Theme folder name.
	 * @param string          $before    Version before the update.
	 * @param \Throwable      $throwable What stopped it.
	 * @return array{0: string, 1: string, 2: bool} Status, detail, and whether a batch must stop.
	 */
	private function after_throwable( Change_Session $session, \Theme_Upgrader $upgrader, string $name, string $before, \Throwable $throwable ): array {
		$stopped = sprintf( 'Code on this site stopped the update with %s.', get_class( $throwable ) );
		if ( false !== has_action( 'shutdown', array( $upgrader, 'restore_temp_backup' ) ) ) {
			// WordPress's update had already failed when the code stopped it.
			$outcome = $session->finish( $upgrader, new \WP_Error( 'failed', '' ), 'themes', $name, 'theme' );
			wp_clean_themes_cache( false );
			return array( 'failed', $stopped . ' The update had failed. ' . $outcome . ' Check the site before trying again.', true );
		}
		wp_clean_themes_cache( false );
		$now = self::version( $name );
		if ( $session->files_were_replaced() ) {
			$left = $session->finish( $upgrader, null, 'themes', $name, 'theme' );
			return array( 'updated', trim( $stopped . sprintf( ' The new version %s was already in place; check the theme with site_command.', $now ) . ' ' . $left ), true );
		}
		if ( Change_Session::has_old_backup( 'themes', $name ) && ( $session->moved_aside() || ! is_dir( get_theme_root( $name ) . '/' . $name ) ) ) {
			// WordPress had moved the old version aside, and the new files may be only partly copied.
			$outcome = $session->restore( $upgrader, 'themes', $name, 'theme' );
			wp_clean_themes_cache( false );
			return array( 'failed', $stopped . ' Its files had been moved aside. ' . $outcome . ( '' !== self::version( $name ) ? ' Check the site with site_command.' : '' ), true );
		}
		$outcome = $session->finish( $upgrader, new \WP_Error( 'failed', '' ), 'themes', $name, 'theme' );
		if ( Change_Session::STILL_IN_PLACE !== $outcome ) {
			return array( 'failed', $stopped . ' ' . $outcome . ' Check the site before trying again.', true );
		}
		if ( '' !== $now && $now === $before ) {
			return array( 'failed', $stopped . ' The previous version is still in place. Check the site before trying again.', true );
		}
		if ( '' !== $now ) {
			return array( 'failed', $stopped . sprintf( ' The theme\'s files may be partly replaced: it now reports version %s. Check the site before relying on the theme.', $now ), true );
		}

		return array( 'failed', $stopped . ' The theme\'s files are missing. Restore them from a backup of the site before relying on the theme.', true );
	}

	/**
	 * A theme's installed version, read from its files, or "" when they are
	 * missing. WordPress's theme cache may still describe a folder that was
	 * moved aside, so the file is checked and the cache skipped.
	 *
	 * @param string $name Theme folder name.
	 * @return string
	 */
	private static function version( string $name ): string {
		if ( ! file_exists( get_theme_root( $name ) . '/' . $name . '/style.css' ) ) {
			return '';
		}
		wp_get_theme( $name )->cache_delete();
		$theme = wp_get_theme( $name );

		return $theme->exists() ? Site_Changes::text( $theme->get( 'Version' ) ) : '';
	}

	/**
	 * Whether a name is an installed theme's folder, never a path elsewhere.
	 *
	 * @param string $name Theme folder name.
	 * @return bool
	 */
	private static function is_installed( string $name ): bool {
		return false === strpos( $name, '..' ) && array_key_exists( $name, wp_get_themes() );
	}

	/**
	 * Whether a .maintenance file may belong to another update.
	 *
	 * @return bool
	 */
	private static function maintenance_is_someone_elses(): bool {
		// This command holds WordPress's background updater lock, so only a core update can be running.
		$core = Change_Session::lock_time( 'core_updater' );

		return null !== $core && $core > time() - 15 * MINUTE_IN_SECONDS;
	}

	/**
	 * Switch to a theme, or say why not. A broken theme, one this network
	 * does not allow, or one this site cannot run is refused.
	 *
	 * @param string $stylesheet Theme folder name.
	 * @return string|null
	 */
	private function switch_to( string $stylesheet ): ?string {
		$theme = wp_get_theme( $stylesheet );
		if ( ! $theme->exists() || $theme->errors() ) {
			$errors = $theme->errors();
			return ( $errors instanceof \WP_Error ? rtrim( Site_Changes::text( $errors->get_error_message() ), '. ' ) . '.' : 'The theme is broken.' ) . ' The site still uses its current theme.';
		}
		if ( is_multisite() && ! $theme->is_allowed() ) {
			return 'This network does not allow the theme on this site. Allow it in Network Admin › Themes. The site still uses its current theme.';
		}
		$requirements = validate_theme_requirements( $stylesheet );
		if ( is_wp_error( $requirements ) ) {
			return rtrim( Site_Changes::text( $requirements->get_error_message() ), '. ' ) . '. The site still uses its current theme.';
		}
		$previous = get_stylesheet();
		try {
			switch_theme( $stylesheet );
		} catch ( \Throwable $throwable ) {
			return get_stylesheet() === $stylesheet
				? sprintf( 'code on this site stopped the switch with %s after WordPress had switched; the site now uses this theme, but code that runs after a switch may not have finished. Check the site.', get_class( $throwable ) )
				: sprintf( 'code on this site stopped the switch with %s. The site still uses %s.', get_class( $throwable ), $previous );
		}
		if ( get_stylesheet() !== $stylesheet ) {
			return 'WordPress reported no error, but the site still uses another theme. Check the site.';
		}

		return null;
	}

	/**
	 * Check the site after a theme switch. Nothing is undone: when the site
	 * then stops with a PHP fatal error, the answer says so and how to
	 * recover by hand.
	 *
	 * @param Change_Session $session  The session.
	 * @param string         $theme    The theme the site now uses.
	 * @param string         $previous The theme the site used before.
	 * @return array{0: string|null, 1: string|null} What happened when the site broke, and a warning when it could not be checked.
	 */
	private function check_site( Change_Session $session, string $theme, string $previous ): array {
		$session->expect( self::CHECK_RESERVE_SECONDS, 0.0 );
		list( $health, $detail ) = Health_Check::run( $session, self::CHECK_RESERVE_SECONDS );
		if ( Health_Check::UNCHECKED === $health ) {
			return array( null, sprintf( 'The site was not checked after the switch: %s. Check that the site still loads.', $detail ) );
		}
		if ( Health_Check::HEALTHY === $health ) {
			return array( null, null );
		}

		return array(
			sprintf(
				'The site was switched to the \'%1$s\' theme, but then %2$s. It still uses %1$s; nothing was undone. To recover, switch back to \'%3$s\' under Appearance › Themes. If wp-admin does not load, rename %4$s over SFTP or the host\'s file manager so its code no longer runs, make sure a working theme is installed, such as \'%3$s\' or a default Twenty-something theme from WordPress.org uploaded into %5$s%7$s, and choose it under Appearance › Themes once the dashboard loads. Reference: %6$s.',
				$theme,
				$detail,
				$previous,
				self::location( $theme ),
				self::themes_folder( $theme ),
				Health_Check::REF,
				self::enable_step()
			),
			null,
		);
	}

	/**
	 * Where a theme's zip is uploaded: on a network, only Network Admin can
	 * install themes.
	 *
	 * @return string
	 */
	private static function upload_screen(): string {
		return is_multisite() ? 'Network Admin › Themes › Add Theme › Upload Theme' : 'Appearance › Themes › Add Theme › Upload Theme';
	}

	/**
	 * What an uploaded theme still needs before a site of a network can
	 * choose it.
	 *
	 * @return string
	 */
	private static function enable_step(): string {
		return is_multisite() ? ', network-enable it under Network Admin › Themes' : '';
	}

	/**
	 * Where a theme's files are, as a site owner finds them over SFTP.
	 *
	 * @param string $name Theme folder name.
	 * @return string
	 */
	private static function location( string $name ): string {
		return sprintf( 'the folder %s', Site_Changes::text( get_theme_root( $name ) . '/' . $name ) );
	}

	/**
	 * The folder a theme's files are in, as a site owner finds it over SFTP.
	 *
	 * @param string $name Theme folder name.
	 * @return string
	 */
	private static function themes_folder( string $name ): string {
		return Site_Changes::text( get_theme_root( $name ) );
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
