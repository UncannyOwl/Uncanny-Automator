<?php
/**
 * Keeps AI apps from changing the site owner's MCP switches.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Model_Context_Protocol\Tools;

use Uncanny_Automator\App\Transports\Model_Context_Protocol\Tools\Abilities\Ability_Availability_Settings;

/**
 * The owner's consents and tool switches decide what AI apps may do, so an
 * AI app must never change them, even through an action or ability that
 * writes options in general:
 *
 * - while an MCP tool use or an Automator ability runs, updating, adding or
 *   deleting these options is refused: an update keeps the stored value, and
 *   an addition or deletion stops the work with an error before anything is
 *   written. Option names are matched as the database matches them, so a
 *   name in another case or with accents, such as
 *   "automator_mcp_databasé_access", which reaches the same row, is refused
 *   too;
 * - such other spellings are refused on every request, recipes included, and
 *   Automator Pro's actions that set or delete options refuse the names
 *   themselves (register()).
 *
 * The owner changes them on Automator's settings screens.
 */
final class Mcp_Settings_Guard {

	/**
	 * The protected options, in lower case.
	 *
	 * @var string[]
	 */
	private const OPTIONS = array(
		Site_Management_Settings::OPTION_NAME,
		Database_Access_Settings::OPTION_NAME,
		Database_Table_Access_Settings::OPTION_NAME,
		File_Reading_Settings::OPTION_NAME,
		File_Reading_Settings::EXCLUSIONS_OPTION_NAME,
		Tool_Availability_Settings::OPTION_NAME,
		Action_Availability_Settings::OPTION_NAME,
		Ability_Availability_Settings::OPTION_NAME,
		'automator_site_manage_maintenance',
		// Names the upload folder, which file commands may read.
		'upload_path',
	);

	/**
	 * How many tool uses are running, nested.
	 *
	 * @var int
	 */
	private static int $depth = 0;

	/**
	 * Each options table's character set, collation and longest name, once
	 * read; false when they cannot be read. On a network, each site has its
	 * own table.
	 *
	 * @var array<string, array{0: string, 1: string, 2: int}|false>|null
	 */
	private static $collation = null;

	/**
	 * Protected options already looked up in this request, by options table
	 * and stored name.
	 *
	 * @var array<string, string|null|false>
	 */
	private static array $answers = array();

	/**
	 * The protected option Automator's own code is writing, or null.
	 *
	 * @var string|null
	 */
	private static ?string $permitted = null;

	/** Longest option name every WordPress database keeps whole. */
	private const SHORTEST_NAME_COLUMN = 64;

	/**
	 * Protect the options on every request: from other spellings of their
	 * names, and from Automator Pro's option actions.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_filter( 'automator_wp_admin_protected_option_keys', array( self::class, 'protect_from_option_actions' ) );
		self::hook();
	}

	/**
	 * Add the protected options to Automator Pro's list of options its
	 * actions may not set or delete.
	 *
	 * @param mixed $keys Protected option names.
	 * @return array
	 */
	public static function protect_from_option_actions( $keys ): array {
		return array_merge( is_array( $keys ) ? $keys : array(), self::OPTIONS );
	}

	/**
	 * Run AI work with the switches protected.
	 *
	 * @template T
	 * @param callable(): T $work The tool use.
	 * @return T
	 */
	public static function during( callable $work ) {
		self::hook();
		++self::$depth;
		try {
			return $work();
		} finally {
			--self::$depth;
		}
	}

	/**
	 * Let Automator's own code write one protected option during AI work,
	 * such as site_manage recording when it turned on maintenance mode.
	 *
	 * @template T
	 * @param string        $option The option it writes.
	 * @param callable(): T $write  The write.
	 * @return T
	 */
	public static function permit( string $option, callable $write ) {
		$was             = self::$permitted;
		self::$permitted = $option;
		try {
			return $write();
		} finally {
			self::$permitted = $was;
		}
	}

	/**
	 * Whether AI work is running.
	 *
	 * @return bool
	 */
	public static function is_guarding(): bool {
		return self::$depth > 0;
	}

	/**
	 * Whether a write to an option is refused: any write to a protected
	 * option during AI work, and at any time a write that reaches one under
	 * another spelling of its name, which no Automator code uses.
	 *
	 * @param mixed $option Option name.
	 * @return bool
	 */
	public static function refuses( $option ): bool {
		if ( null !== self::$permitted && $option === self::$permitted ) {
			return false;
		}
		if ( self::$depth > 0 ) {
			return null !== self::protected_name( $option, true );
		}
		$name = self::protected_name( $option, false );

		return null !== $name && $name !== $option;
	}

	/**
	 * Whether an option name reaches a protected option.
	 *
	 * @param mixed $option Option name.
	 * @return bool
	 */
	public static function is_protected( $option ): bool {
		return null !== self::protected_name( $option, true );
	}

	/**
	 * The protected option an option name reaches, or null.
	 *
	 * The name is compared as the database keeps it: cut to the column's
	 * length, as MySQL cuts a longer name when WordPress adds it. Plain
	 * lower-case names are compared directly, and other plain ASCII names
	 * without regard to case or trailing spaces. Any other name is compared
	 * by the database with the options table's collation, which may also
	 * ignore accents and some characters. During AI work, a name that is not
	 * valid UTF-8, which WordPress may strip into a protected name, or one
	 * the database cannot compare, counts as protected, so the write is
	 * refused; at other times such a name is let through as WordPress
	 * handles it.
	 *
	 * @param mixed $option      Option name.
	 * @param bool  $fail_closed Whether a name that cannot be compared counts as protected.
	 * @return string|null
	 */
	private static function protected_name( $option, bool $fail_closed ): ?string {
		if ( ! is_string( $option ) ) {
			return null;
		}
		if ( 1 === preg_match( '/^[a-z0-9_-]*\z/', $option ) ) {
			// Without spaces or other characters a database ignores, no cut makes it another name.
			return in_array( $option, self::OPTIONS, true ) ? $option : null;
		}
		if ( 1 !== preg_match( '//u', $option ) ) {
			return $fail_closed ? self::OPTIONS[0] : null;
		}
		$stored = mb_strlen( $option, 'UTF-8' ) > self::SHORTEST_NAME_COLUMN ? mb_substr( $option, 0, self::name_length(), 'UTF-8' ) : $option;
		if ( 1 === preg_match( '/^[\x20-\x7e]*\z/', $stored ) ) {
			$plain = strtolower( rtrim( $stored, ' ' ) );
			return in_array( $plain, self::OPTIONS, true ) ? $plain : null;
		}
		$plain = strtolower( trim( remove_accents( $stored ) ) );
		if ( in_array( $plain, self::OPTIONS, true ) ) {
			return $plain;
		}
		global $wpdb;
		$key = $wpdb->options . '|' . $stored;
		if ( ! array_key_exists( $key, self::$answers ) ) {
			self::$answers[ $key ] = self::compare_in_database( $stored );
		}
		$found = self::$answers[ $key ];

		return false === $found ? ( $fail_closed ? self::OPTIONS[0] : null ) : $found;
	}

	/**
	 * The options table's character set, collation and longest name, or
	 * false when they cannot be read.
	 *
	 * @return array{0: string, 1: string, 2: int}|false
	 */
	private static function column() {
		global $wpdb;
		if ( ! is_array( self::$collation ) ) {
			self::$collation = array();
		}
		if ( ! array_key_exists( $wpdb->options, self::$collation ) ) {
			self::$collation[ $wpdb->options ] = false;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Reads the options table's collation once per request.
			$column = $wpdb->get_row( "SHOW FULL COLUMNS FROM `{$wpdb->options}` LIKE 'option_name'" );
			$column = is_object( $column ) ? get_object_vars( $column ) : array();
			$name   = isset( $column['Collation'] ) ? (string) $column['Collation'] : '';
			$type   = isset( $column['Type'] ) ? (string) $column['Type'] : '';
			if ( 1 === preg_match( '/^([a-z0-9]+)_[a-z0-9_]+$/', $name, $match ) && 1 === preg_match( '/^varchar\((\d+)\)$/i', $type, $length ) ) {
				self::$collation[ $wpdb->options ] = array( $match[1], $name, (int) $length[1] );
			}
		}

		return self::$collation[ $wpdb->options ];
	}

	/**
	 * How many characters of an option name the database keeps.
	 *
	 * @return int
	 */
	private static function name_length(): int {
		$column = self::column();

		// Unknown: compare the shortest length WordPress has used, which refuses more, never less.
		return false === $column ? self::SHORTEST_NAME_COLUMN : max( 1, $column[2] );
	}

	/**
	 * Ask the database which protected option a name reaches.
	 *
	 * @param string $option Option name that is not plain lower case.
	 * @return string|null|false The protected option, null for none, or false when the database cannot answer.
	 */
	private static function compare_in_database( string $option ) {
		global $wpdb;
		$column = self::column();
		if ( false === $column ) {
			return false;
		}
		list( $charset, $collation ) = $column;
		$names                       = implode( ', ', array_fill( 0, count( self::OPTIONS ), '%s' ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, PluginCheck.Security.DirectDB.UnescapedDBParameter -- The character set and collation cannot be placeholders; they were read from the table and checked. The names are placeholders.
		$found = $wpdb->get_var( $wpdb->prepare( "SELECT FIELD( CONVERT( %s USING {$charset} ) COLLATE {$collation}, {$names} )", array_merge( array( $option ), self::OPTIONS ) ) );
		if ( null === $found || ! is_numeric( $found ) ) {
			return false;
		}
		$index = (int) $found - 1;

		return self::OPTIONS[ $index ] ?? null;
	}

	/**
	 * Install the guarding hooks once.
	 *
	 * @return void
	 */
	private static function hook(): void {
		// Checked each time: tests and other code may reset WordPress's hooks.
		if ( false !== has_filter( 'pre_update_option', array( self::class, 'keep_value' ) ) ) {
			return;
		}
		add_filter( 'pre_update_option', array( self::class, 'keep_value' ), PHP_INT_MAX, 3 );
		// Both fire before WordPress writes anything.
		add_action( 'add_option', array( self::class, 'refuse_change' ), PHP_INT_MAX, 1 );
		add_action( 'delete_option', array( self::class, 'refuse_change' ), PHP_INT_MAX, 1 );
	}

	/**
	 * Keep a protected option's value.
	 *
	 * @param mixed $value     New value.
	 * @param mixed $option    Option name.
	 * @param mixed $old_value Current value.
	 * @return mixed
	 */
	public static function keep_value( $value, $option, $old_value ) {
		return self::refuses( $option ) ? $old_value : $value;
	}

	/**
	 * Stop the work before a protected option is added or deleted.
	 *
	 * @param mixed $option Option name.
	 * @return void
	 *
	 * @throws Protected_Setting_Refused When the change is refused.
	 */
	public static function refuse_change( $option ): void {
		if ( self::refuses( $option ) ) {
			throw new Protected_Setting_Refused( 'This setting belongs to the site owner and can be changed only on Automator\'s settings screens, so it was not changed.' );
		}
	}
}
