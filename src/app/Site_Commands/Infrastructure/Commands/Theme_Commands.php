<?php
/**
 * The "theme" command group.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Command errors are plain terminal text for AI clients, never rendered as HTML.

namespace Uncanny_Automator\App\Site_Commands\Infrastructure\Commands;

use Uncanny_Automator\App\Site_Commands\Domain\Command_Argument;
use Uncanny_Automator\App\Site_Commands\Domain\Command_Definition;
use Uncanny_Automator\App\Site_Commands\Domain\Command_Error;
use Uncanny_Automator\App\Site_Commands\Domain\Command_Option;
use Uncanny_Automator\App\Site_Commands\Domain\Output\Command_Output;
use Uncanny_Automator\App\Site_Commands\Domain\Output\Rows;
use Uncanny_Automator\App\Site_Commands\Ports\Site_Command_Group;

/**
 * Installed themes, as WP-CLI lists them. Server paths are never shown.
 */
final class Theme_Commands implements Site_Command_Group {

	private const STATUSES       = array( 'active', 'parent', 'inactive' );
	private const DEFAULT_FIELDS = array( 'name', 'status', 'update', 'version', 'update_version', 'auto_update' );
	private const FIELDS         = array( 'name', 'title', 'status', 'update', 'version', 'update_version', 'auto_update', 'author', 'description', 'parent_theme', 'template', 'stylesheet', 'tags', 'requires', 'requires_php' );
	private const GET_FIELDS     = array( 'name', 'title', 'version', 'status', 'parent_theme', 'template', 'stylesheet', 'description', 'author', 'tags' );
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
		'active'   => 'A',
		'parent'   => 'P',
		'inactive' => 'I',
	);

	/**
	 * The commands this group offers.
	 *
	 * @return Command_Definition[]
	 */
	public function commands(): array {
		$theme = Command_Argument::required( 'theme', 'Theme name (its folder), such as twentytwentyfive.' );

		return array(
			Command_Definition::reads( 'theme list', 'List installed themes. The update fields reflect WordPress\'s last update check.' )
				->with_option( Command_Option::choice( 'status', 'Only themes with this status.', self::STATUSES ) )
				->with_fields( self::DEFAULT_FIELDS, self::FIELDS, 'Themes' )
				->handled_by( array( $this, 'list_themes' ) ),
			Command_Definition::reads( 'theme get', 'Show details about one installed theme.' )
				->with_argument( $theme )
				->with_record_fields( self::GET_FIELDS, self::FIELDS )
				->handled_by( array( $this, 'get' ) ),
			Command_Definition::reads( 'theme status', 'Summarise installed themes, or show the status of one theme.' )
				->with_argument( Command_Argument::optional( 'theme', 'Theme name (its folder), such as twentytwentyfive.' ) )
				->handled_by( array( $this, 'status' ) ),
			Command_Definition::reads( 'theme is-active', 'Check whether a theme is the active theme. Prints yes or no.' )
				->with_argument( $theme )
				->handled_by( array( $this, 'is_active' ) ),
			Command_Definition::reads( 'theme is-installed', 'Check whether a theme is installed. Prints yes or no.' )
				->with_argument( $theme )
				->handled_by( array( $this, 'is_installed' ) ),
		);
	}

	/**
	 * The "theme list" command, sorted by title.
	 *
	 * @param array<string, string>      $arguments Unused.
	 * @param array<string, string|bool> $options   Validated options.
	 * @return Command_Output
	 */
	public function list_themes( array $arguments, array $options ): Command_Output {
		$status = isset( $options['status'] ) ? (string) $options['status'] : null;

		$rows = $this->rows();

		$output = Values::with_skipped( Command_Output::rows( Rows::where( $rows, 'status', $status ) )->with_stats( $this->summary( $rows ) ), $this->skipped );

		return $this->updates_known ? $output : $output->with_warning( Update_Status::NOT_CHECKED );
	}

	/**
	 * Every installed theme by status, whatever --status shows, and how many
	 * can be updated, from WordPress's last update check.
	 *
	 * @param array<int, array<string, scalar>> $rows Every installed theme.
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
	 * The "theme get" command.
	 *
	 * @param array<string, string>      $arguments Named arguments.
	 * @param array<string, string|bool> $options   Unused.
	 * @return Command_Output
	 */
	public function get( array $arguments, array $options ): Command_Output {
		$theme = $this->find( $arguments['theme'] );

		// As in WP-CLI, get names the theme by its title; stylesheet is the folder.
		$theme['name'] = $theme['title'];

		return Command_Output::record( $theme );
	}

	/**
	 * The "theme status" command, in WP-CLI's layout.
	 *
	 * @param array<string, string>      $arguments Named arguments.
	 * @param array<string, string|bool> $options   Unused.
	 * @return Command_Output
	 */
	public function status( array $arguments, array $options ): Command_Output {
		if ( isset( $arguments['theme'] ) ) {
			$theme = $this->find( $arguments['theme'] );

			return Command_Output::text(
				implode(
					"\n",
					array(
						'Theme ' . $theme['name'] . ' details:',
						'    Name: ' . $theme['title'],
						'    Status: ' . ucfirst( (string) $theme['status'] ),
						'    Version: ' . $theme['version'],
						'    Author: ' . $theme['author'],
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
		$lines   = array( count( $rows ) . ' installed themes:' );
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
			'P' => 'Parent',
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
	 * The "theme is-active" command.
	 *
	 * @param array<string, string>      $arguments Named arguments.
	 * @param array<string, string|bool> $options   Unused.
	 * @return Command_Output
	 */
	public function is_active( array $arguments, array $options ): Command_Output {
		return Command_Output::text( $this->active()[0] === $arguments['theme'] ? 'yes' : 'no' );
	}

	/**
	 * The "theme is-installed" command.
	 *
	 * @param array<string, string>      $arguments Named arguments.
	 * @param array<string, string|bool> $options   Unused.
	 * @return Command_Output
	 */
	public function is_installed( array $arguments, array $options ): Command_Output {
		return Command_Output::text( null !== $this->lookup( $arguments['theme'] ) ? 'yes' : 'no' );
	}

	/**
	 * A theme by name, or a WP-CLI style error.
	 *
	 * @param string $name Theme folder name.
	 * @return array<string, scalar>
	 *
	 * @throws Command_Error When it is not installed.
	 */
	private function find( string $name ): array {
		$theme = $this->lookup( $name );
		if ( null === $theme ) {
			throw new Command_Error( sprintf( "The '%s' theme could not be found.", $name ) );
		}

		return $theme;
	}

	/**
	 * A theme by name, or null.
	 *
	 * @param string $name Theme folder name.
	 * @return array<string, scalar>|null
	 */
	private function lookup( string $name ): ?array {
		foreach ( $this->rows() as $row ) {
			if ( $row['name'] === $name ) {
				return $row;
			}
		}

		return null;
	}

	/**
	 * The active theme and its parent folder names. A filter or option that
	 * returns something else is refused rather than guessed at.
	 *
	 * @return array{0: string, 1: string}
	 *
	 * @throws Command_Error When the active theme is unreadable.
	 */
	private function active(): array {
		$stylesheet = get_stylesheet();
		$template   = get_template();
		if ( ! is_string( $stylesheet ) || '' === $stylesheet || ! is_string( $template ) || '' === $template ) {
			throw new Command_Error( 'WordPress\'s active theme setting is unreadable, so theme statuses cannot be reported.' );
		}

		return array( $stylesheet, $template );
	}

	/**
	 * Every installed theme, sorted by title.
	 *
	 * @return array<int, array<string, scalar>>
	 */
	private function rows(): array {
		$transient           = get_site_transient( 'update_themes' );
		$this->updates_known = Update_Status::known( $transient );
		$offered             = Update_Status::entries( $transient, 'response' );
		$withheld            = Update_Status::entries( $transient, 'no_update' );
		$auto_updates        = get_site_option( 'auto_update_themes', array() );
		$auto_updates        = is_array( $auto_updates ) ? $auto_updates : array();

		list( $active, $parent ) = $this->active();

		$rows          = array();
		$this->skipped = 0;
		foreach ( wp_get_themes() as $stylesheet => $theme ) {
			if ( ! $theme instanceof \WP_Theme ) {
				++$this->skipped;
				continue;
			}
			// A numeric folder name such as "2024" arrives as an integer key.
			$stylesheet = (string) $stylesheet;
			$status     = 'inactive';
			if ( $active === $stylesheet ) {
				$status = 'active';
			} elseif ( $parent === $stylesheet ) {
				$status = 'parent';
			}
			$version = Values::text( $theme->get( 'Version' ) );
			$info    = $offered[ $stylesheet ] ?? $withheld[ $stylesheet ] ?? array();
			$tags    = $theme->get( 'Tags' );

			list( $update, $update_version ) = $this->updates_known ? Update_Status::status( $offered[ $stylesheet ] ?? null, $withheld[ $stylesheet ] ?? null, $version ) : array( 'unknown', '' );

			$rows[] = array(
				'name'           => $stylesheet,
				'title'          => Values::text( $theme->get( 'Name' ) ),
				'status'         => $status,
				'update'         => $update,
				'version'        => $version,
				'update_version' => $update_version,
				'auto_update'    => in_array( $stylesheet, $auto_updates, true ) ? 'on' : 'off',
				'author'         => wp_strip_all_tags( Values::text( $theme->get( 'Author' ) ) ),
				'description'    => wp_strip_all_tags( Values::text( $theme->get( 'Description' ) ) ),
				'parent_theme'   => $theme->parent() ? Values::text( $theme->parent()->get( 'Name' ) ) : '',
				'template'       => Values::text( $theme->get_template() ),
				'stylesheet'     => Values::text( $theme->get_stylesheet() ),
				'tags'           => is_array( $tags ) ? implode( ', ', array_filter( $tags, 'is_scalar' ) ) : '',
				'requires'       => '' !== ( $info['requires'] ?? '' ) ? $info['requires'] : Values::text( $theme->get( 'RequiresWP' ) ),
				'requires_php'   => '' !== ( $info['requires_php'] ?? '' ) ? $info['requires_php'] : Values::text( $theme->get( 'RequiresPHP' ) ),
			);
		}

		return Rows::sort_by( $rows, 'title' );
	}
}
