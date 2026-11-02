<?php
/**
 * Owner's ability switches, enforced wherever an MCP tool call runs an ability.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Model_Context_Protocol\Tools\Abilities;

use Uncanny_Automator\App\Transports\Abilities\Automator_Abilities;
use WP_Error;

/**
 * Stops an ability the owner switched off for AI apps while an Automator MCP
 * tool call is running, wherever WordPress runs it from.
 *
 * run_ability and rest_request check the ability they are asked for. Another
 * plugin's ability can run a further ability by name (the WordPress MCP
 * Adapter's execute-ability ships with Elementor and WooCommerce), so the
 * switch is also checked as WordPress starts each ability. Outside an MCP tool
 * call the switches do not apply: wp-admin, cron and other plugins run as before.
 *
 * @since 8.0.0
 */
final class Ability_Switch_Guard {

	public const ERROR_CODE = 'automator_ability_switched_off';

	/**
	 * Names stopped during each running tool call, innermost last.
	 *
	 * @var array<int,string[]>
	 */
	private static array $calls = array();

	/**
	 * @var Ability_Availability_Settings|null
	 */
	private static ?Ability_Availability_Settings $settings = null;

	/**
	 * Hook the check into WordPress's ability runs.
	 *
	 * WordPress 7.1 lets a plugin answer for an ability before it runs. Earlier
	 * versions only announce the run, so there the stop is an exception that
	 * the tool call catches.
	 *
	 * @return void
	 */
	public static function register(): void {
		if ( class_exists( 'WP_Filter_Sentinel' ) ) {
			add_filter( 'wp_pre_execute_ability', array( self::class, 'refuse_switched_off' ), PHP_INT_MAX, 2 );
			return;
		}

		add_action( 'wp_before_execute_ability', array( self::class, 'stop_switched_off' ), PHP_INT_MIN, 1 );
	}

	/**
	 * Start watching for one MCP tool call.
	 *
	 * @return void
	 */
	public static function enter(): void {
		self::$calls[] = array();
	}

	/**
	 * Stop watching for the innermost tool call.
	 *
	 * @return string[] Abilities stopped during it.
	 */
	public static function leave(): array {
		return array_values( array_unique( array_pop( self::$calls ) ?? array() ) );
	}

	/**
	 * `wp_pre_execute_ability`: answer a switched-off ability with the refusal.
	 *
	 * @param mixed  $pre          The pre-computed result, or WordPress's sentinel to run normally.
	 * @param string $ability_name Ability about to run.
	 *
	 * @return mixed
	 */
	public static function refuse_switched_off( $pre, $ability_name ) {
		if ( ! self::refuses( (string) $ability_name ) ) {
			return $pre;
		}

		return new WP_Error( self::ERROR_CODE, self::message( array( (string) $ability_name ) ), array( 'status' => 403 ) );
	}

	/**
	 * `wp_before_execute_ability` (WordPress before 7.1): stop a switched-off ability.
	 *
	 * @param string $ability_name Ability about to run.
	 *
	 * @return void
	 *
	 * @throws Ability_Switched_Off_Exception When the owner switched it off for AI apps.
	 */
	public static function stop_switched_off( $ability_name ): void {
		if ( self::refuses( (string) $ability_name ) ) {
			throw new Ability_Switched_Off_Exception( esc_html( self::message( array( (string) $ability_name ) ) ) );
		}
	}

	/**
	 * What the AI app is told when switched-off abilities were stopped.
	 *
	 * @param string[] $names Stopped abilities.
	 *
	 * @return string
	 */
	public static function message( array $names ): string {
		$quoted = implode( ', ', array_map( static fn( string $name ): string => '"' . $name . '"', $names ) );
		$url    = admin_url( 'edit.php?post_type=uo-recipe&page=uncanny-automator-config&tab=model-context-protocol&model-context-protocol=wordpress-abilities' );

		return 1 === count( $names )
			? sprintf( 'The ability %1$s is not switched on for AI apps on this site, so this request was stopped. A site administrator can switch it on at %2$s.', $quoted, $url )
			: sprintf( 'The abilities %1$s are not switched on for AI apps on this site, so this request was stopped. A site administrator can switch them on at %2$s.', $quoted, $url );
	}

	/**
	 * Whether the running tool call must not run this ability, recording it if so.
	 *
	 * Automator's own operations are not on the owner's WordPress abilities
	 * list; their nested runs are refused where they are authorized.
	 *
	 * @param string $name Ability name.
	 *
	 * @return bool
	 */
	private static function refuses( string $name ): bool {
		if ( array() === self::$calls || Automator_Abilities::is_operation_name( $name ) ) {
			return false;
		}

		self::$settings = self::$settings ?? new Ability_Availability_Settings();
		if ( self::$settings->is_enabled( $name ) ) {
			return false;
		}

		self::$calls[ array_key_last( self::$calls ) ][] = $name;

		return true;
	}
}
