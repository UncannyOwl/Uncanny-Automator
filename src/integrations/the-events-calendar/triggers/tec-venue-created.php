<?php

namespace Uncanny_Automator\Integrations\The_Events_Calendar;

use Uncanny_Automator\Recipe\Trigger_Definition;

/**
 * Class EC_VENUE_CREATED
 *
 * Trigger: A venue is created.
 *
 * **No filters by design.** This trigger has no venue selector because
 * the underlying `tribe_events_venue_created` hook fires unconditionally
 * for every venue create — including venues created programmatically by
 * importers, other plugins, or as a side effect of inline-venue creation
 * during an event save. Recipes using this trigger will fire on every
 * create system-wide; downstream actions should be idempotent or use a
 * Filter block to narrow scope.
 *
 * @property The_Events_Calendar_Helpers $item_helpers
 *
 * @package Uncanny_Automator
 */
class EC_VENUE_CREATED extends \Uncanny_Automator\Recipe\Trigger {

	/**
	 * Opt into the lazy trigger loading path.
	 *
	 * @return Trigger_Definition
	 */
	public static function definition() {
		// `tribe_events_venue_created` only fires from TEC's
		// Tribe__Events__Venue::create() API path (verified at
		// the-events-calendar/src/Tribe/Venue.php:611). The standard
		// wp-admin "Add Venue" screen uses wp_insert_post() directly and
		// never reaches that method, so the trigger never fires. Switch
		// to save_post_tribe_venue + a new-post guard in validate().
		return self::new_definition( 'EC_VENUE_CREATED', 'EC' )
			->trigger_meta( 'ECVEN' )
			->trigger_type( 'anonymous' )
			->hook( 'save_post_tribe_venue', 10, 3 );
	}

	/**
	 * @return void
	 */
	protected function setup_trigger() {
		// integration / code / trigger_meta / trigger_type / hook are auto-applied from definition().

		$this->set_is_login_required( false );
		$this->set_support_link( \Automator()->get_author_support_link( $this->get_trigger_code(), 'integration/the-events-calendar/' ) );

		$this->set_sentence( esc_html_x( 'A venue is created', 'The Events Calendar', 'uncanny-automator' ) );
		$this->set_readable_sentence( esc_html_x( 'A venue is created', 'The Events Calendar', 'uncanny-automator' ) );
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public function options() {
		return array();
	}

	/**
	 * @param array $trigger
	 * @param array $hook_args
	 *
	 * @return bool
	 */
	public function validate( $trigger, $hook_args ) {
		unset( $trigger );

		$post_id = isset( $hook_args[0] ) ? absint( $hook_args[0] ) : 0;
		$post    = $hook_args[1] ?? null;

		if ( 0 === $post_id || ! $post instanceof \WP_Post ) {
			return false;
		}

		// Skip autosaves / revisions.
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return false;
		}

		// Only a published venue counts as "created".
		if ( 'publish' !== $post->post_status ) {
			return false;
		}

		// Fire once on creation, not on later edits: on the first publish the
		// modified timestamp equals the post date; subsequent edits advance
		// it. The save_post $update flag is unreliable here — the wp-admin
		// "Add Venue" flow publishes a pre-existing auto-draft, so $update is
		// true even on the initial creation.
		if ( $post->post_modified_gmt !== $post->post_date_gmt ) {
			return false;
		}

		return true;
	}

	/**
	 * @param array $trigger
	 * @param array $tokens
	 *
	 * @return array
	 */
	public function define_tokens( $trigger, $tokens ) {
		return array_merge(
			$tokens,
			$this->item_helpers->tokens()->venue_tokens( 'ECVEN' )
		);
	}

	/**
	 * @param array $trigger
	 * @param array $hook_args
	 *
	 * @return array<string,string>
	 */
	public function hydrate_tokens( $trigger, $hook_args ) {
		unset( $trigger );
		$venue_id = isset( $hook_args[0] ) ? absint( $hook_args[0] ) : 0;
		return $this->item_helpers->tokens()->hydrate_venue_tokens( $venue_id, 'ECVEN' );
	}
}
