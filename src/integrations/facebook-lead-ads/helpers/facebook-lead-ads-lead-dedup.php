<?php
/**
 * Facebook Lead Ads Lead Dedup
 *
 * @package Uncanny_Automator
 * @author  Uncanny Owl
 */

namespace Uncanny_Automator\Integrations\Facebook_Lead_Ads;

/**
 * One trigger run per lead, whichever path the lead arrives by (signed
 * delivery design §7.4).
 *
 * A lead can reach the site twice: signed, through the platform's inbox, and
 * as the legacy unsigned forward, while a page moves to signed delivery or
 * when the platform cannot tell the forward's site from the signed one's.
 * Both paths claim the lead's id before firing the trigger, and only the
 * first claim succeeds.
 *
 * A claim is an option added with `automator_add_option()`, which inserts a
 * row under a unique option name: the database decides between two requests
 * claiming at once, which a transient's read-then-write cannot. The value is
 * when the claim expires (48 hours, the window in which the platform also
 * de-duplicates the lead); a daily event removes expired claims. Claims are
 * never autoloaded.
 *
 * @since 8.0.0
 *
 * @package Uncanny_Automator\Integrations\Facebook_Lead_Ads
 */
class Facebook_Lead_Ads_Lead_Dedup {

	/**
	 * Prefix of every claim's option name.
	 *
	 * @var string
	 */
	const PREFIX = 'automator_fbla_lead_claim_';

	/**
	 * How long a claim holds its lead.
	 *
	 * @var int
	 */
	const TTL_SECONDS = 2 * DAY_IN_SECONDS;

	/**
	 * Daily WP-Cron hook that removes expired claims.
	 *
	 * @var string
	 */
	const PRUNE_HOOK = 'automator_facebook_lead_ads_prune_lead_claims';

	/**
	 * Claims removed per query while pruning.
	 *
	 * @var int
	 */
	const PRUNE_BATCH = 500;

	/**
	 * Queries per prune; what is left waits for the next day's.
	 *
	 * @var int
	 */
	const PRUNE_MAX_BATCHES = 20;

	/**
	 * Attach the daily prune.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'init', array( $this, 'schedule_prune' ) );
		add_action( 'automator_facebook_lead_ads_prune_lead_claims', array( $this, 'prune' ) );
	}

	/**
	 * Schedule the daily prune once.
	 *
	 * @return void
	 */
	public function schedule_prune() {

		if ( false === wp_next_scheduled( self::PRUNE_HOOK ) ) {
			wp_schedule_event( time() + DAY_IN_SECONDS, 'daily', self::PRUNE_HOOK );
		}
	}

	/**
	 * Claim the lead for this run.
	 *
	 * @param string $leadgen_id The lead's id.
	 *
	 * @return bool True when this is the first claim: run the lead. False when
	 *              another delivery claimed it.
	 */
	public function claim( $leadgen_id ) {

		global $wpdb;

		$name = self::option_name( $leadgen_id );

		// A concurrent claim makes this insert fail on the unique option name:
		// that is the expected answer, not an error to print.
		$suppressed = $wpdb->suppress_errors( true );
		$added      = automator_add_option( $name, time() + self::TTL_SECONDS, false, false );
		$wpdb->suppress_errors( $suppressed );

		if ( $added ) {
			return true;
		}

		if ( false !== automator_get_option( $name, false, true ) ) {
			return false;
		}

		// The insert failed and no claim exists: the database refused the
		// write. Dropping the lead would lose it; running it at worst runs it
		// twice.
		automator_log( 'The Facebook lead ' . $leadgen_id . ' could not be claimed: ' . $wpdb->last_error . '. It runs unclaimed.', 'Facebook_Lead_Ads_Lead_Dedup', true );

		return true;
	}

	/**
	 * Remove expired claims. Cron callback.
	 *
	 * @return int How many were removed.
	 */
	public function prune() {

		global $wpdb;

		$removed = 0;

		for ( $batch = 0; $batch < self::PRUNE_MAX_BATCHES; $batch++ ) {

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Automator's own table; no WordPress API for it. Live run state read; must not be cached.
			$names = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT option_name FROM {$wpdb->prefix}uap_options WHERE option_name LIKE %s AND CAST(option_value AS UNSIGNED) < %d LIMIT %d",
					$wpdb->esc_like( self::PREFIX ) . '%',
					time(),
					self::PRUNE_BATCH
				)
			);

			foreach ( $names as $name ) {
				$removed += automator_delete_option( $name ) ? 1 : 0;
			}

			if ( count( $names ) < self::PRUNE_BATCH ) {
				break;
			}
		}

		return $removed;
	}

	/**
	 * The option a lead's claim is kept in. The id arrives in a public
	 * request, so it is hashed into the name rather than written into it.
	 *
	 * @param string $leadgen_id The lead's id.
	 *
	 * @return string
	 */
	public static function option_name( $leadgen_id ) {
		return self::PREFIX . md5( (string) $leadgen_id );
	}
}
