<?php
/**
 * The "language" commands that change the site.
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
 * Translation updates WordPress offers, installed through its own language
 * pack upgrader, one at a time. WordPress itself is not updated here; its
 * minor releases install automatically or from Dashboard › Updates.
 */
final class Language_Manage_Commands implements Site_Command_Group {

	/** Time installing one translation needs once its download finished. */
	private const INSTALL_SECONDS = 3.0;

	/** Time to download, unpack and install one translation, and answer. */
	private const TRANSLATION_SECONDS = Change_Session::UNZIP_SECONDS + self::INSTALL_SECONDS + 3.0;

	private const FIELDS = array( 'type', 'name', 'language', 'version', 'status', 'detail' );

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
		$for = static function ( string $type, string $example ): Command_Definition {
			return Command_Definition::changes( 'language ' . $type . ' update', sprintf( 'Install the translation updates WordPress offers for one %1$s, or for every %1$s with --all. Translations are installed one at a time; when too little time is left, the rest are listed as not started, so run the command again.', $type ) )
				->with_argument( Command_Argument::optional( $type, sprintf( 'Installed %s folder name, such as %s. Omit it with --all.', $type, $example ) ) )
				->with_option( Command_Option::flag( 'all', sprintf( 'Update the translations of every %s.', $type ) ) )
				->with_option( Command_Option::flag( 'dry-run', 'Only list the translation updates.' ) )
				->with_fields( self::FIELDS, self::FIELDS, 'Translations' );
		};

		return array(
			Command_Definition::changes( 'language core install', 'Install a language for WordPress from WordPress.org, so the site can switch to it with "site switch-language <locale>".' )
				->with_argument( Command_Argument::required( 'locale', 'Language code, such as de_DE or fr_FR.' ) )
				->handled_by( array( $this, 'install_core_language' ) ),
			Command_Definition::changes( 'language core update', 'Install the translation updates WordPress offers for WordPress itself.' )
				->with_option( Command_Option::flag( 'dry-run', 'Only list the translation updates.' ) )
				->with_fields( self::FIELDS, self::FIELDS, 'Translations' )
				->handled_by( array( $this, 'update_core_translations' ) ),
			$for( 'plugin', 'akismet' )->handled_by( array( $this, 'update_plugin_translations' ) ),
			$for( 'theme', 'twentytwentyfive' )->handled_by( array( $this, 'update_theme_translations' ) ),
		);
	}

	/**
	 * The "language core install" command.
	 *
	 * @param array<string, string>      $arguments The locale.
	 * @param array<string, string|bool> $options   Unused.
	 * @return Command_Output
	 *
	 * @throws Command_Error When it cannot be installed.
	 */
	public function install_core_language( array $arguments, array $options ): Command_Output {
		$locale = $arguments['locale'];
		if ( 1 !== preg_match( '/^[a-z]{2,3}(_[A-Z]{2})?(_[a-z0-9]+)?$/', $locale ) ) {
			throw new Command_Error( sprintf( '"%s" is not a language code. Use one such as de_DE or fr_FR. Nothing was changed.', $locale ) );
		}
		if ( 'en_US' === $locale ) {
			return Command_Output::text( 'English (United States) is built into WordPress, so there is nothing to install. Nothing was changed.' );
		}
		Site_Changes::require_capability( 'install_languages', 'install languages' );
		if ( in_array( $locale, get_available_languages(), true ) ) {
			return Command_Output::text( sprintf( 'The language %1$s is already installed. Nothing was changed. Run "site switch-language %1$s" to use it, or "language core update" for its updates.', $locale ) );
		}
		Site_Changes::require_file_changes( WP_CONTENT_DIR, 'Settings › General' );
		Change_Session::require_no_other_updates();

		return Change_Session::run(
			$this->budget(),
			function ( Change_Session $session ) use ( $locale ): Command_Output {
				$session->budget()->require( self::TRANSLATION_SECONDS + 3.0, sprintf( 'Installing the language %s', $locale ), 'Settings › General' );
				$session->lock();
				$session->only_wordpress_org();
				$session->expect( self::TRANSLATION_SECONDS, 0.0 );

				require_once ABSPATH . 'wp-admin/includes/translation-install.php';
				$translations = wp_get_available_translations();
				if ( ! isset( $translations[ $locale ] ) ) {
					$failed = $session->failed_requests()['api.wordpress.org'] ?? null;
					throw new Command_Error(
						null !== $failed && array() === $translations
							? sprintf( 'WordPress.org could not be reached to look up the language %1$s: %2$s. Nothing was changed. Try again later.', $locale, rtrim( $failed, '. ' ) )
							: sprintf( 'WordPress.org has no WordPress translation for %s. Nothing was changed. Language codes look like de_DE or pt_BR.', $locale )
					);
				}
				$pack       = (object) $translations[ $locale ];
				$pack->type = 'core';
				$pack->slug = 'default';

				list( $status, $detail ) = $this->install_pack( $session, $pack );
				if ( 'updated' !== $status ) {
					throw new Command_Error( sprintf( 'The language %s was not installed: %s', $locale, $detail ) );
				}
				$name = Site_Changes::text( $translations[ $locale ]['english_name'] ?? $locale );

				return Command_Output::text( sprintf( 'Installed %1$s (%2$s). The site does not use it yet; run "site switch-language %2$s" to switch to it.', $name, $locale ) );
			}
		);
	}

	/**
	 * The "language core update" command.
	 *
	 * @param array<string, string>      $arguments Unused.
	 * @param array<string, string|bool> $options   Validated options.
	 * @return Command_Output
	 */
	public function update_core_translations( array $arguments, array $options ): Command_Output {
		return $this->update( 'core', null, true === ( $options['dry-run'] ?? false ) );
	}

	/**
	 * The "language plugin update" command.
	 *
	 * @param array<string, string>      $arguments The plugin, unless --all.
	 * @param array<string, string|bool> $options   Validated options.
	 * @return Command_Output
	 */
	public function update_plugin_translations( array $arguments, array $options ): Command_Output {
		return $this->update( 'plugin', $this->one_or_all( 'plugin', $arguments, $options ), true === ( $options['dry-run'] ?? false ) );
	}

	/**
	 * The "language theme update" command.
	 *
	 * @param array<string, string>      $arguments The theme, unless --all.
	 * @param array<string, string|bool> $options   Validated options.
	 * @return Command_Output
	 */
	public function update_theme_translations( array $arguments, array $options ): Command_Output {
		return $this->update( 'theme', $this->one_or_all( 'theme', $arguments, $options ), true === ( $options['dry-run'] ?? false ) );
	}

	/**
	 * The named item, or null for --all.
	 *
	 * @param string                     $type      "plugin" or "theme".
	 * @param array<string, string>      $arguments Arguments.
	 * @param array<string, string|bool> $options   Options.
	 * @return string|null
	 *
	 * @throws Command_Error When neither or both are given, or the item is not installed.
	 */
	private function one_or_all( string $type, array $arguments, array $options ): ?string {
		$named = $arguments[ $type ] ?? null;
		if ( ( true === ( $options['all'] ?? false ) ) === ( null !== $named ) ) {
			throw new Command_Error( sprintf( 'Name one %1$s, or use --all, but not both. Usage: language %1$s update [<%1$s>] [--all] [--dry-run]. Nothing was changed.', $type ) );
		}
		if ( null !== $named && ! self::installed( $type, $named ) ) {
			throw new Command_Error( sprintf( 'No %1$s named %2$s is installed. Run "%1$s list" in site_command to see installed %1$ss. Nothing was changed.', $type, $named ) );
		}

		return $named;
	}

	/**
	 * Whether a plugin or theme with this folder name is installed.
	 *
	 * @param string $type "plugin" or "theme".
	 * @param string $name Folder name.
	 * @return bool
	 */
	private static function installed( string $type, string $name ): bool {
		if ( 'theme' === $type ) {
			return false === strpos( $name, '..' ) && array_key_exists( $name, wp_get_themes() );
		}
		Site_Changes::load_admin_files();
		foreach ( array_keys( get_plugins() ) as $file ) {
			if ( dirname( $file ) === $name || basename( $file, '.php' ) === $name ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Install the translation updates WordPress offers, one at a time.
	 *
	 * @param string      $type    "core", "plugin" or "theme".
	 * @param string|null $slug    Only this plugin or theme, or null for all.
	 * @param bool        $dry_run Only list them.
	 * @return Command_Output
	 */
	private function update( string $type, ?string $slug, bool $dry_run ): Command_Output {
		Site_Changes::require_capability( 'update_languages', 'update translations' );
		if ( ! $dry_run ) {
			Site_Changes::require_file_changes( WP_CONTENT_DIR );
			Change_Session::require_no_other_updates();
		}

		return Change_Session::run(
			$this->budget(),
			function ( Change_Session $session ) use ( $type, $slug, $dry_run ): Command_Output {
				$session->budget()->require( $dry_run ? 5.0 : self::TRANSLATION_SECONDS, sprintf( 'Updating %s translations', $type ) );
				if ( ! $dry_run ) {
					$session->lock();
				}
				list( , $warnings ) = Packages::refresh( $type, $session );

				$rows    = array();
				$pending = array();
				foreach ( wp_get_translation_updates() as $pack ) {
					if ( ! is_object( $pack ) || ( $pack->type ?? '' ) !== $type ) {
						continue;
					}
					$name = 'core' === $type ? 'default' : Site_Changes::text( $pack->slug ?? '' );
					if ( null !== $slug && $name !== $slug ) {
						continue;
					}
					$pending[] = array( $pack, count( $rows ) );
					$rows[]    = array(
						'type'     => $type,
						'name'     => $name,
						'language' => Site_Changes::text( $pack->language ?? '' ),
						'version'  => Site_Changes::text( $pack->version ?? '' ),
						'status'   => $dry_run ? 'available' : 'pending',
						'detail'   => '',
					);
				}
				if ( array() === $rows ) {
					$output = Command_Output::text( sprintf( 'WordPress offers no translation updates for %s. Nothing was changed.', null === $slug ? ( 'core' === $type ? 'WordPress itself' : 'any ' . $type ) : $slug ) );
					foreach ( $warnings as $warning ) {
						$output = $output->with_warning( $warning );
					}
					return $output;
				}

				if ( ! $dry_run ) {
					$updated = array();
					foreach ( $pending as list( $pack, $index ) ) {
						if ( ! $session->budget()->allows( self::TRANSLATION_SECONDS ) ) {
							$rows[ $index ]['status'] = 'not started';
							$rows[ $index ]['detail'] = $session->budget()->shortfall( self::TRANSLATION_SECONDS, 'This translation' ) . ' ' . $session->budget()->next_step( self::TRANSLATION_SECONDS );
							continue;
						}
						list( $rows[ $index ]['status'], $rows[ $index ]['detail'] ) = $this->install_pack( $session, $pack );
						if ( 'updated' === $rows[ $index ]['status'] ) {
							$updated[] = $pack;
						}
					}
					$session->expect( 0.0, 0.0 );
					self::forget_translations( $type, $updated );
				}

				return Packages::output( $rows, $dry_run, $warnings, 'updated' );
			}
		);
	}

	/**
	 * Install one translation pack.
	 *
	 * @param Change_Session $session The session.
	 * @param object         $pack    WordPress's translation update.
	 * @return array{0: string, 1: string} Status and detail.
	 */
	private function install_pack( Change_Session $session, $pack ): array {
		$skin     = new \WP_Ajax_Upgrader_Skin();
		$upgrader = new \Language_Pack_Upgrader( $skin );
		$session->expect( self::TRANSLATION_SECONDS - 3.0, self::INSTALL_SECONDS );
		try {
			$result = $upgrader->upgrade( $pack, array( 'clear_update_cache' => false ) );
		} catch ( \Throwable $throwable ) {
			return array( 'failed', sprintf( 'Code on this site stopped the installation with %s. The translation\'s old files may have been removed; run the command again to install it.', get_class( $throwable ) ) );
		}
		$error   = $session->explain( is_wp_error( $skin->result ) ? $skin->result : ( is_wp_error( $result ) ? $result : null ) );
		$failure = null === $error ? Site_Changes::outcome( $result, $skin, Site_Changes::TRANSLATION ) : Site_Changes::failure( $error, $skin->get_upgrade_messages(), Site_Changes::TRANSLATION );
		if ( null === $failure ) {
			return array( 'updated', '' );
		}

		return array( $error instanceof \WP_Error && Change_Session::OUT_OF_TIME === $error->get_error_code() ? 'not started' : 'failed', $failure );
	}

	/**
	 * Drop installed translations from WordPress's update offers.
	 *
	 * @param string   $type  "core", "plugin" or "theme".
	 * @param object[] $packs Installed translation packs.
	 * @return void
	 */
	private static function forget_translations( string $type, array $packs ): void {
		$transients = array(
			'core'   => 'update_core',
			'plugin' => 'update_plugins',
			'theme'  => 'update_themes',
		);
		if ( array() === $packs ) {
			return;
		}
		$current = get_site_transient( $transients[ $type ] );
		if ( ! is_object( $current ) || ! isset( $current->translations ) || ! is_array( $current->translations ) ) {
			return;
		}
		$current->translations = array_values(
			array_filter(
				$current->translations,
				static function ( $translation ) use ( $packs ): bool {
					$translation = (object) $translation;
					foreach ( $packs as $pack ) {
						if ( ( $translation->slug ?? '' ) === ( $pack->slug ?? '' ) && ( $translation->language ?? '' ) === ( $pack->language ?? '' ) ) {
							return false;
						}
					}
					return true;
				}
			)
		);
		set_site_transient( $transients[ $type ], $current );
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
