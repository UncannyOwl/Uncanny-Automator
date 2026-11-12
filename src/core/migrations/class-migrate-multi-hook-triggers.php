<?php

namespace Uncanny_Automator\Migrations;

use Uncanny_Automator\Actionify_Triggers\Trigger_Query;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Migrate_Multi_Hook_Triggers
 *
 * Gives back the hooks a trigger lost when 8.0 saved it with only the first one.
 *
 * A legacy trigger may listen on several hooks: WooCommerce's order triggers
 * listen on completed, thank-you and payment-complete. Until this release, a trigger
 * saved from the trigger registry (the AI agent and MCP tools, recipe
 * prepopulation, the trigger API) or copied from a recipe tree (duplicate,
 * import, templates) stored only the first hook in `add_action`, so the
 * "lands on a thank you page" and "pays for" options never fired.
 *
 * A trigger is repaired only when its stored value is exactly the first hook of
 * the list its code registers. Any other stored value was not written by that
 * truncation and is left alone.
 *
 * The repair runs again after a plugin is activated: a trigger whose integration
 * was inactive is skipped, because its code's hook list is unknown. An imported
 * trigger needs no repair; the trigger store writes its code's hooks.
 * 8.0.0 to 8.0.3 are the only releases that wrote truncated triggers, but the
 * exports and templates they made carry the single hook and outlive them. Keep
 * run_again() and its hook in `Automator_Load::load_migrations_hooks()` for as
 * long as such a file can be imported.
 *
 * @since 8.0.4
 *
 * @package Uncanny_Automator
 */
class Migrate_Multi_Hook_Triggers extends Migration {

	/**
	 * One-time key in the `automator_migrations` option.
	 *
	 * @since 8.0.4
	 *
	 * @var string
	 */
	const NAME = '804_multi_hook_triggers';

	/**
	 * Register the migration under its key.
	 *
	 * @since 8.0.4
	 *
	 * @return void
	 */
	public function __construct() {
		parent::__construct( self::NAME );
	}

	/**
	 * Forget that the repair ran, so the next full admin page load runs it again.
	 *
	 * Hooked to `activated_plugin` in `Automator_Load::load_migrations_hooks()`.
	 *
	 * @since 8.0.4
	 *
	 * @return void
	 */
	public static function run_again() {

		$migrations = automator_get_option( self::OPTION_NAME, array(), true );

		if ( empty( $migrations[ self::NAME ] ) ) {
			return;
		}

		unset( $migrations[ self::NAME ] );

		automator_update_option( self::OPTION_NAME, $migrations );
	}

	/**
	 * Whether the repair has not run on this site yet.
	 *
	 * Read before integrations load, to decide whether this request has to load
	 * all of them so every trigger code's hook list is known.
	 *
	 * @since 8.0.4
	 *
	 * @return bool
	 */
	public static function is_pending() {
		$migrations = automator_get_option( self::OPTION_NAME, array() );

		return empty( $migrations[ self::NAME ] );
	}

	/**
	 * Only run on a full admin page load that loaded every integration's trigger definitions.
	 *
	 * A request that was not pending when it started did not ask for the full load, and a
	 * plugin activated during the request is not loaded in it. Running there would skip that
	 * plugin's triggers and record the repair as done.
	 *
	 * @since 8.0.4
	 *
	 * @return bool
	 */
	public function conditions_met() {

		if ( wp_doing_ajax() || wp_doing_cron() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return false;
		}

		if ( did_action( 'activated_plugin' ) ) {
			return false;
		}

		return is_admin() && \Uncanny_Automator\Recipe_Manifest::get_instance()->should_load_all();
	}

	/**
	 * Store the full hook list on every trigger that kept only the first hook.
	 *
	 * @since 8.0.4
	 *
	 * @return void
	 */
	public function migrate() {

		$registered = Automator()->get_triggers();

		// No trigger definitions loaded on this request: try again on a later one.
		if ( empty( $registered ) || ! is_array( $registered ) ) {
			return;
		}

		$hook_lists = $this->get_multi_hook_lists( $registered );

		if ( empty( $hook_lists ) ) {
			$this->complete();
			return;
		}

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-off migration.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT hook_meta.post_id, hook_meta.meta_value AS add_action, code_meta.meta_value AS trigger_code, trigger_post.post_parent
				   FROM $wpdb->postmeta hook_meta
				   INNER JOIN $wpdb->postmeta code_meta
				      ON code_meta.post_id = hook_meta.post_id
				     AND code_meta.meta_key = 'code'
				   INNER JOIN $wpdb->posts trigger_post
				      ON trigger_post.ID = hook_meta.post_id
				     AND trigger_post.post_type = %s
				  WHERE hook_meta.meta_key = 'add_action'",
				AUTOMATOR_POST_TYPE_TRIGGER
			),
			ARRAY_A
		);

		$repaired_recipes = array();

		foreach ( (array) $rows as $row ) {

			$code = (string) $row['trigger_code'];

			if ( ! isset( $hook_lists[ $code ] ) ) {
				continue;
			}

			$stored = automator_safe_unserialize( $row['add_action'] );

			// Only the truncated value: exactly the first hook of the code's list.
			if ( ! is_string( $stored ) || $stored !== $hook_lists[ $code ][0] ) {
				continue;
			}

			update_post_meta( (int) $row['post_id'], 'add_action', $hook_lists[ $code ] );

			$repaired_recipes[ (int) $row['post_parent'] ] = true;
		}

		foreach ( array_keys( $repaired_recipes ) as $recipe_id ) {
			Automator()->cache->clear_automator_recipe_part_cache( $recipe_id );
		}

		// The engine reads its hook list from this transient for up to a minute.
		delete_transient( Trigger_Query::CACHE_KEY );

		automator_log( sprintf( 'Restored the full hook list on triggers in %d recipe(s).', count( $repaired_recipes ) ), $this->name, true );

		$this->complete();
	}

	/**
	 * Hook lists of every loaded trigger code that listens on more than one hook.
	 *
	 * @since 8.0.4
	 *
	 * @param array $registered Trigger definitions from `Automator()->get_triggers()`.
	 *
	 * @return array<string, string[]> Hook names by trigger code.
	 */
	private function get_multi_hook_lists( array $registered ) {

		$lists = array();

		foreach ( $registered as $trigger ) {

			if ( ! is_array( $trigger ) || empty( $trigger['code'] ) || ! is_array( $trigger['action'] ?? null ) ) {
				continue;
			}

			$names = array_values(
				array_filter(
					$trigger['action'],
					static function ( $name ) {
						return is_string( $name ) && '' !== $name;
					}
				)
			);

			if ( count( $names ) > 1 ) {
				$lists[ (string) $trigger['code'] ] = $names;
			}
		}

		return $lists;
	}
}

new Migrate_Multi_Hook_Triggers();
