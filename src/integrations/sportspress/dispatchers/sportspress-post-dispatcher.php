<?php

namespace Uncanny_Automator\Integrations\Sportspress;

/**
 * Class Sportspress_Post_Dispatcher
 *
 * SportsPress fires no lifecycle hooks of its own, so this derives them from
 * WordPress's post hooks (R17):
 * - Created: the first time an event, player or team reaches Published or
 *   Scheduled. A created flag holding the post's own ID keeps it to once per
 *   post; a flag copied from another post doesn't count.
 * - Updated: an event saved while Published or Scheduled before and after,
 *   unless it was created earlier in the same request.
 * - Status changed: an event's post status really changes. An old auto-draft
 *   reads as draft, so a first publish from the editor always fires as
 *   Draft to Published.
 *
 * While SportsPress Pro's Duplicator runs, only Created fires: the copy's
 * terms, results and status describe the original.
 *
 * Free hosts it because Free's own triggers need it; Pro's triggers listen.
 *
 * @package Uncanny_Automator\Integrations\Sportspress
 */
class Sportspress_Post_Dispatcher {

	/**
	 * @var bool
	 */
	private static $booted = false;

	/**
	 * Post IDs that fired Created in this request.
	 *
	 * @var array<int, true>
	 */
	private static $created = array();

	/**
	 * Whether SportsPress Pro's Duplicator is running in this request.
	 *
	 * @var bool
	 */
	private static $duplicating = false;

	/**
	 * Idempotent: load_shared_hooks() may run more than once per request.
	 *
	 * @return void
	 */
	public static function boot() {

		if ( self::$booted ) {
			return;
		}

		self::$booted = true;

		add_action( 'transition_post_status', array( __CLASS__, 'on_transition' ), 10, 3 );
		add_action( 'post_updated', array( __CLASS__, 'on_post_updated' ), 10, 3 );
		// Before the Duplicator's own handler at 10, which ends the request with exit.
		add_action( 'admin_action_sportspress_duplicate', array( __CLASS__, 'on_duplicate' ), 1, 0 );
	}

	/**
	 * Test seam.
	 *
	 * @return void
	 */
	public static function reset_boot() {
		self::$booted = false;
	}

	/**
	 * Test seam.
	 *
	 * @return void
	 */
	public static function reset_created() {
		self::$created = array();
	}

	/**
	 * Test seam.
	 *
	 * @return void
	 */
	public static function reset_duplicating() {
		self::$duplicating = false;
	}

	/**
	 * @return void
	 */
	public static function on_duplicate() {
		self::$duplicating = true;
	}

	/**
	 * @return bool
	 */
	public static function is_duplicating() {
		return self::$duplicating;
	}

	/**
	 * @param string   $new_status
	 * @param string   $old_status
	 * @param \WP_Post $post
	 *
	 * @return void
	 */
	public static function on_transition( $new_status, $old_status, $post ) {

		if ( ! $post instanceof \WP_Post ) {
			return;
		}

		self::maybe_created( (string) $new_status, (string) $old_status, $post );

		if ( Sportspress_Helpers::EVENT === $post->post_type && ! self::$duplicating ) {
			self::maybe_status_changed( (string) $new_status, (string) $old_status, (int) $post->ID );
		}
	}

	/**
	 * @param int      $post_id
	 * @param \WP_Post $post_after
	 * @param \WP_Post $post_before
	 *
	 * @return void
	 */
	public static function on_post_updated( $post_id, $post_after, $post_before ) {

		if ( ! $post_after instanceof \WP_Post || ! $post_before instanceof \WP_Post ) {
			return;
		}

		$post_id = (int) $post_id;

		if ( Sportspress_Helpers::EVENT !== $post_after->post_type || self::$duplicating || isset( self::$created[ $post_id ] ) ) {
			return;
		}

		if ( ! self::is_live( $post_before->post_status ) || ! self::is_live( $post_after->post_status ) ) {
			return;
		}

		do_action( 'automator_sportspress_event_updated', $post_id );
	}

	/**
	 * @param string   $new_status
	 * @param string   $old_status
	 * @param \WP_Post $post
	 *
	 * @return void
	 */
	private static function maybe_created( $new_status, $old_status, $post ) {

		if ( ! in_array( $post->post_type, array( Sportspress_Helpers::EVENT, Sportspress_Helpers::PLAYER, Sportspress_Helpers::TEAM ), true ) ) {
			return;
		}

		$post_id = (int) $post->ID;

		// Leaving Published or Scheduled flags the post, so republishing never fires.
		if ( self::is_live( $old_status ) && ! self::is_live( $new_status ) ) {
			self::flag( $post_id );
			return;
		}

		if ( ! self::is_live( $new_status ) || self::is_live( $old_status ) || self::is_flagged( $post_id ) ) {
			return;
		}

		self::flag( $post_id );
		self::$created[ $post_id ] = true;

		switch ( $post->post_type ) {
			case Sportspress_Helpers::EVENT:
				do_action( 'automator_sportspress_event_created', $post_id );
				break;
			case Sportspress_Helpers::PLAYER:
				do_action( 'automator_sportspress_player_created', $post_id );
				break;
			case Sportspress_Helpers::TEAM:
				do_action( 'automator_sportspress_team_created', $post_id );
				break;
		}
	}

	/**
	 * @param string $new_status
	 * @param string $old_status
	 * @param int    $post_id
	 *
	 * @return void
	 */
	private static function maybe_status_changed( $new_status, $old_status, $post_id ) {

		// As Automator's is_wp_post_being_published(): an auto-draft is a post
		// not yet published, so it reads as the Draft the Publish box shows.
		if ( 'auto-draft' === $old_status ) {
			$old_status = 'draft';
		}

		if ( $old_status === $new_status || 'new' === $old_status || in_array( $new_status, array( 'auto-draft', 'inherit' ), true ) ) {
			return;
		}

		do_action( 'automator_sportspress_event_status_changed', $post_id, $new_status, $old_status );
	}

	/**
	 * @param string $status
	 *
	 * @return bool
	 */
	private static function is_live( $status ) {
		return in_array( $status, array( 'publish', 'future' ), true );
	}

	/**
	 * @param int $post_id
	 *
	 * @return bool
	 */
	private static function is_flagged( $post_id ) {
		return (int) get_post_meta( $post_id, '_automator_sportspress_created', true ) === $post_id;
	}

	/**
	 * @param int $post_id
	 *
	 * @return void
	 */
	private static function flag( $post_id ) {

		if ( ! self::is_flagged( $post_id ) ) {
			update_post_meta( $post_id, '_automator_sportspress_created', $post_id );
		}
	}
}
