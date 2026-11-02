<?php
/**
 * The "site" commands that change the site's settings.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Command errors are plain terminal text for AI clients, never rendered as HTML.

namespace Uncanny_Automator\App\Site_Commands\Infrastructure\Manage;

use Uncanny_Automator\App\Site_Commands\Domain\Command_Argument;
use Uncanny_Automator\App\Site_Commands\Domain\Command_Definition;
use Uncanny_Automator\App\Site_Commands\Domain\Command_Error;
use Uncanny_Automator\App\Site_Commands\Domain\Output\Command_Output;
use Uncanny_Automator\App\Site_Commands\Domain\Time_Budget;
use Uncanny_Automator\App\Site_Commands\Infrastructure\Request_Time_Budget;
use Uncanny_Automator\App\Site_Commands\Ports\Site_Command_Group;

/**
 * Two settings from Settings › Reading and Settings › General, changed as
 * those screens change them, and as easily changed back.
 */
final class Site_Settings_Commands implements Site_Command_Group {

	/** Time to change a setting, whose hooks run then, and answer. */
	private const QUICK_SECONDS = 3.0;

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
			Command_Definition::changes( 'site search-engines allow', 'Let search engines index the site, as Settings › Reading does when "Discourage search engines" is off.' )
				->handled_by(
					function (): Command_Output {
						return $this->search_engines( true );
					}
				),
			Command_Definition::changes( 'site search-engines discourage', 'Ask search engines not to index the site, as Settings › Reading does with "Discourage search engines". Search engines decide whether to follow the request.' )
				->handled_by(
					function (): Command_Output {
						return $this->search_engines( false );
					}
				),
			Command_Definition::changes( 'site switch-language', 'Switch the site\'s language, as Settings › General does. The language must be installed; run "language core install <locale>" first. Users who chose their own language keep it.' )
				->with_argument( Command_Argument::required( 'locale', 'Language code, such as de_DE or en_US.' ) )
				->handled_by( array( $this, 'switch_language' ) ),
		);
	}

	/**
	 * Allow or discourage search engines.
	 *
	 * @param bool $allow Whether to allow them.
	 * @return Command_Output
	 */
	private function search_engines( bool $allow ): Command_Output {
		Site_Changes::require_capability( 'manage_options', 'change the site\'s search engine visibility' );
		$value = (string) get_option( 'blog_public' );
		if ( ! in_array( $value, array( '0', '1' ), true ) ) {
			// Privacy plugins, such as Restricted Site Access, keep other values here.
			throw new Command_Error( sprintf( 'This site\'s visibility setting (blog_public) is "%s", which a privacy plugin such as Restricted Site Access sets, not WordPress\'s own on or off. Nothing was changed, so the site\'s privacy is as before. Change it in that plugin\'s settings.', Site_Changes::text( $value ) ) );
		}
		if ( ( '1' === $value ) === $allow ) {
			return Command_Output::text( $allow ? 'Search engines are already allowed to index the site. Nothing was changed.' : 'Search engines are already asked not to index the site. Nothing was changed.' );
		}

		return Change_Session::run(
			$this->budget(),
			static function ( Change_Session $session ) use ( $allow ): Command_Output {
				$session->budget()->require( self::QUICK_SECONDS, 'Changing search engine visibility' );
				update_option( 'blog_public', $allow ? '1' : '0' );
				self::require_saved( 'blog_public', $allow ? '1' : '0', $allow ? 'search engines are still asked not to index the site' : 'search engines may still index the site' );

				return Command_Output::text(
					$allow
						? 'Search engines may index the site again. They pick up the change on their next visit; run "site search-engines discourage" to undo it.'
						: 'Search engines are now asked not to index the site. Pages already indexed may stay listed for a while; run "site search-engines allow" to undo it.'
				);
			}
		);
	}

	/**
	 * The "site switch-language" command.
	 *
	 * @param array<string, string>      $arguments The locale.
	 * @param array<string, string|bool> $options   Unused.
	 * @return Command_Output
	 *
	 * @throws Command_Error When the language is not installed.
	 */
	public function switch_language( array $arguments, array $options ): Command_Output {
		$locale = $arguments['locale'];
		if ( 1 !== preg_match( '/^[a-z]{2,3}(_[A-Z]{2})?(_[a-z0-9]+)?$/', $locale ) ) {
			throw new Command_Error( sprintf( '"%s" is not a language code. Use one such as de_DE, fr_FR or en_US. Nothing was changed.', $locale ) );
		}
		Site_Changes::require_capability( 'manage_options', 'change the site\'s language' );
		$current = self::configured_language();
		if ( $current === $locale ) {
			return Command_Output::text( sprintf( 'The site already uses %s. Nothing was changed.', $locale ) );
		}
		if ( 'en_US' !== $locale && ! in_array( $locale, get_available_languages(), true ) ) {
			throw new Command_Error( sprintf( 'The language %1$s is not installed. Run "language core install %1$s" first. Nothing was changed.', $locale ) );
		}

		return Change_Session::run(
			$this->budget(),
			static function ( Change_Session $session ) use ( $locale, $current ): Command_Output {
				$session->budget()->require( self::QUICK_SECONDS, 'Switching the site\'s language' );
				update_option( 'WPLANG', 'en_US' === $locale ? '' : $locale );
				self::require_saved( 'WPLANG', 'en_US' === $locale ? '' : $locale, sprintf( 'the site still uses %s', $current ) );

				$output = Command_Output::text( sprintf( 'The site\'s language is now %1$s (it was %2$s). Users who chose their own language keep it. Run "site switch-language %2$s" to undo it.', $locale, $current ) );
				// Multilingual plugins may choose the language themselves.
				$GLOBALS['locale'] = null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- WordPress works the site's language out again from it.
				$effective         = get_locale();

				return $effective === $locale ? $output : $output->with_warning( sprintf( 'WordPress reports the site\'s language as %s: code on this site, such as a multilingual plugin, chooses it.', $effective ) );
			}
		);
	}

	/**
	 * The site's language as Settings › General sets it, worked out as
	 * WordPress does before any plugin filters it.
	 *
	 * @return string
	 */
	private static function configured_language(): string {
		global $wp_local_package;
		$language = isset( $wp_local_package ) ? (string) $wp_local_package : '';
		if ( defined( 'WPLANG' ) ) {
			$language = (string) WPLANG;
		}
		$own = get_option( 'WPLANG', false );
		if ( false === $own && is_multisite() ) {
			$own = get_site_option( 'WPLANG', false );
		}
		if ( false !== $own ) {
			$language = (string) $own;
		}

		return '' === $language ? 'en_US' : $language;
	}

	/**
	 * Refuse unless a setting now holds a value, and say what is stored when
	 * code on the site keeps WordPress from using it.
	 *
	 * @param string $option   Option name.
	 * @param string $expected Value written.
	 * @param string $still    What the site still does when code overrides the stored value.
	 * @return void
	 *
	 * @throws Command_Error When the value is not in use.
	 */
	private static function require_saved( string $option, string $expected, string $still ): void {
		if ( (string) get_option( $option ) === $expected ) {
			return;
		}
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- The stored value, past any filter that overrides it.
		$stored = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $option ) );
		if ( null !== $stored && (string) $stored === $expected ) {
			throw new Command_Error( sprintf( 'The setting was saved, but code on this site overrides it, so %s. Check the site\'s plugins, or the theme\'s functions, for what sets %s.', $still, $option ) );
		}

		throw new Command_Error( 'WordPress did not save the change; a plugin may have stopped it. Nothing was changed.' );
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
