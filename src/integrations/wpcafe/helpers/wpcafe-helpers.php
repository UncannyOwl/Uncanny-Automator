<?php

namespace Uncanny_Automator\Integrations\Wpcafe;

use Exception;
use Uncanny_Automator\Recipe\Abstract_Helpers;
use WpCafe\Models\Reservation_Model;

/**
 * Class Wpcafe_Helpers
 *
 * Shared logic for the WPCafe integration.
 *
 * Two WPCafe implementation details shape everything in this class:
 *
 * 1. A reservation's status is stored ONLY as `post_status`. `Post_Model`
 *    excludes `status` from its meta-write loop but `load_attributes()` reads
 *    it back from meta, so `Reservation_Model->status` is always an empty
 *    string. WPCafe's own code works around this the same way we do — see
 *    `Reservation_Resource::to_array()` and Pro's `Reservation_Payload_Builder`,
 *    both of which call `get_post_status()`. Never trust the model property.
 *
 * 2. `wpc_reservation` is never passed to `register_post_type()`. Reservations
 *    are written with `wp_insert_post()` against an unregistered type, so any
 *    `register_post_type`-dependent helper is unavailable. Always query with an
 *    explicit `post_type`.
 *
 *    The reservation STATUSES, unlike the type, are registered — by
 *    `WpCafe\Admin\Post_Status` on `init` priority 999, with no `is_admin()`
 *    gate — so an explicit status list does reach WP_Query intact in
 *    production. `'post_status' => 'any'` is used here anyway because it needs
 *    no such assumption and covers a status a future WPCafe release adds.
 *
 *    Beware when reading this suite: WPCafe is not activated in wpunit and the
 *    model is stubbed, so nothing registers the statuses and WP_Query drops
 *    every one but `pending`. That is a test-environment artifact, not
 *    production behaviour. Pro's bootstrap registers them explicitly to avoid
 *    it.
 *
 * @package Uncanny_Automator
 */
class Wpcafe_Helpers extends Abstract_Helpers {

	/**
	 * The (unregistered) post type reservations are stored as.
	 *
	 * @var string
	 */
	const POST_TYPE = 'wpc_reservation';

	/**
	 * The reservation post type, as an instance method.
	 *
	 * Exists so Pro can reach the value through its `$base` delegation block
	 * instead of naming this concrete class at a query site.
	 *
	 * @return string
	 */
	public function get_post_type() {
		return self::POST_TYPE;
	}

	/**
	 * The taxonomy a reservation's `branch_id` points into.
	 *
	 * Registered against `product` by `Location_Taxonomy::register_taxonomy()`
	 * (wp-cafe/core/location/location-taxonomy.php:54) — but only when WPCafe's
	 * switchable `location` module is on, so it is NOT always present.
	 *
	 * @var string
	 */
	const LOCATION_TAXONOMY = 'wpcafe_location';

	/**
	 * Statuses a reservation is allowed to hold.
	 *
	 * Mirrors `Reservation_Model::ALLOWED_STATUSES`. Declared locally so the
	 * recipe UI can still render its dropdowns when WPCafe is deactivated
	 * mid-session; `get_allowed_statuses()` prefers the live constant.
	 *
	 * @return array<string,string> Status slug => label.
	 */
	public function get_status_labels() {
		return array(
			'pending'         => esc_html_x( 'Pending', 'WPCafe', 'uncanny-automator' ),
			'confirmed'       => esc_html_x( 'Confirmed', 'WPCafe', 'uncanny-automator' ),
			'cancelled'       => esc_html_x( 'Cancelled', 'WPCafe', 'uncanny-automator' ),
			'pending_payment' => esc_html_x( 'Pending payment', 'WPCafe', 'uncanny-automator' ),
			'refunded'        => esc_html_x( 'Refunded', 'WPCafe', 'uncanny-automator' ),
		);
	}

	/**
	 * The status slugs WPCafe actually recognizes.
	 *
	 * Prefers the live model constant so a WPCafe release that adds a status
	 * does not silently fall out of our trigger matching.
	 *
	 * @return string[]
	 */
	public function get_allowed_statuses() {

		if ( class_exists( '\WpCafe\Models\Reservation_Model' ) ) {
			return Reservation_Model::ALLOWED_STATUSES;
		}

		return array_keys( $this->get_status_labels() );
	}

	/**
	 * Is this a real reservation status?
	 *
	 * Used to reject core statuses (`trash`, `draft`, `auto-draft`, `inherit`,
	 * and the `new` pseudo-status) that reach us through
	 * `transition_post_status`.
	 *
	 * @param string $status Status slug.
	 *
	 * @return bool
	 */
	public function is_reservation_status( $status ) {
		return in_array( (string) $status, $this->get_allowed_statuses(), true );
	}

	/**
	 * Reservation status options, with an "Any status" sentinel (triggers).
	 *
	 * @return array
	 */
	public function get_status_options() {

		$options = array(
			array(
				'text'  => esc_html_x( 'Any status', 'WPCafe', 'uncanny-automator' ),
				'value' => '-1',
			),
		);

		return array_merge( $options, $this->get_status_options_strict() );
	}

	/**
	 * Reservation status options without the "Any" sentinel (actions).
	 *
	 * @return array
	 */
	public function get_status_options_strict() {

		$options = array();
		$labels  = $this->get_status_labels();

		foreach ( $this->get_allowed_statuses() as $status ) {
			$options[] = array(
				'text'  => $labels[ $status ] ?? ucfirst( str_replace( '_', ' ', $status ) ),
				'value' => $status,
			);
		}

		return $options;
	}

	/**
	 * Location picker options.
	 *
	 * A reservation's `branch_id` is a `wpcafe_location` TERM ID, not a post ID:
	 * WPCafe passes the submitted value straight to
	 * `Location_Model::find( $location_id )` (wp-cafe/utils/helpers.php:993),
	 * which is `get_term( $term_id, 'wpcafe_location' )`.
	 *
	 * Read through `get_terms()` rather than `Location_Model::all()` so the
	 * dropdown still renders when WPCafe is deactivated mid-session, matching how
	 * `get_status_labels()` guards the status dropdown. The label prefers the
	 * `restaurant_name` term meta and falls back to the term name, which is the
	 * same resolution order `Location_Model::find()` applies (:352-354).
	 *
	 * A single-location restaurant has no location terms at all, which is why the
	 * consuming field stays optional.
	 *
	 * @return array Option rows shaped { text, value }.
	 */
	public function get_location_options() {

		if ( ! taxonomy_exists( self::LOCATION_TAXONOMY ) ) {
			return array();
		}

		$terms = get_terms(
			array(
				'taxonomy'   => self::LOCATION_TAXONOMY,
				'hide_empty' => false,
			)
		);

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return array();
		}

		$options = array();

		foreach ( $terms as $term ) {
			$options[] = array(
				'text'  => $this->get_location_label( $term ),
				'value' => (string) $term->term_id,
			);
		}

		return $options;
	}

	/**
	 * A location's display label: `restaurant_name` term meta, else the term name.
	 *
	 * @param \WP_Term $term Location term.
	 *
	 * @return string
	 */
	private function get_location_label( \WP_Term $term ) {

		$name = (string) get_term_meta( $term->term_id, 'restaurant_name', true );

		return '' !== $name ? $name : $term->name;
	}

	/**
	 * Resolve a location term ID to its display label.
	 *
	 * WPCafe only stores `branch_name` when the booking form posts it, and the
	 * Create Reservation action never does, so the name is resolved from the ID.
	 *
	 * @param string|int $branch_id Location term ID.
	 *
	 * @return string Empty string when the ID is not a location.
	 */
	public function get_location_name( $branch_id ) {

		$branch_id = absint( $branch_id );

		if ( 0 === $branch_id ) {
			return '';
		}

		$term = get_term( $branch_id, self::LOCATION_TAXONOMY );

		return $term instanceof \WP_Term ? $this->get_location_label( $term ) : '';
	}

	/**
	 * Resolve booked seat IDs to their table labels.
	 *
	 * The visual seat picker stores seat IDs (`S11`) in `seats` and never writes
	 * `table_name` — that meta is only filled by the QR-table flow. WPCafe's own
	 * emails resolve the table the same way (reservation-email-handler.php:242):
	 * the location's `visual_table_layout` term meta, else the global setting.
	 * The setting is read from the option directly so this still works while
	 * WPCafe is deactivated.
	 *
	 * @param mixed      $seats     Stored `seats` meta.
	 * @param string|int $branch_id Location term ID.
	 *
	 * @return string Comma-separated table labels, or an empty string.
	 */
	public function get_table_names_from_seats( $seats, $branch_id ) {

		if ( empty( $seats ) || ! is_array( $seats ) ) {
			return '';
		}

		$labels = array();

		foreach ( $this->get_table_layout( $branch_id ) as $table ) {

			$table_label = (string) ( $table['name'] ?? $table['label'] ?? $table['id'] ?? '' );
			$ids         = array( (string) ( $table['id'] ?? '' ) );

			foreach ( (array) ( $table['seats'] ?? array() ) as $seat ) {
				$ids[] = (string) ( $seat['id'] ?? '' );
			}

			$ids = array_filter( $ids, 'strlen' );

			if ( '' !== $table_label && array_intersect( array_map( 'strval', $seats ), $ids ) ) {
				$labels[] = $table_label;
			}
		}

		return implode( ', ', array_unique( $labels ) );
	}

	/**
	 * The tables of the visual layout that applies to a location.
	 *
	 * @param string|int $branch_id Location term ID.
	 *
	 * @return array[]
	 */
	private function get_table_layout( $branch_id ) {

		$branch_id = absint( $branch_id );
		$layout    = 0 !== $branch_id ? get_term_meta( $branch_id, 'visual_table_layout', true ) : '';

		if ( empty( $layout ) ) {
			$settings = get_option( 'wpcafe_reservation_settings_options', array() );
			$layout   = is_array( $settings ) ? ( $settings['visual_table_layout'] ?? '' ) : '';
		}

		if ( is_string( $layout ) && '' !== $layout ) {
			$layout = json_decode( $layout, true );
		}

		$tables = is_array( $layout ) ? ( $layout['tables'] ?? array() ) : array();

		return is_array( $tables ) ? array_filter( $tables, 'is_array' ) : array();
	}

	/**
	 * Location picker segment (actions — no "Any" sentinel).
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_locations_strict( $request ): array {
		return $this->remote_data_success( $this->get_location_options() );
	}

	/**
	 * Reservation picker options, newest first.
	 *
	 * Labelled from meta rather than the post title: a reservation's title is
	 * whatever `$attributes['title']` held (wp-cafe/base/database/post-model.php:268)
	 * and WPCafe's reservation controller never sets one, so every reservation
	 * post is untitled. That is also why the search cannot be handed to
	 * WP_Query as `s` — the guest's name, email and date live in post meta,
	 * where a title search would never reach them.
	 *
	 * The search runs in SQL rather than over the fetched page. A restaurant
	 * passes the row cap in a season, and post-filtering a capped page means
	 * the bookings a search is most likely reaching for — the older ones — are
	 * the ones it can never return.
	 *
	 * @param string $search Optional search string, matched against guest name, email and date.
	 *
	 * @return array Option rows shaped { text, value }.
	 */
	public function get_reservation_options( $search = '' ) {

		$search = trim( (string) $search );
		$ids    = $this->query_reservation_ids( $search );

		// A numeric term is almost always the ID the label leads with, typed
		// with or without the `#` the label shows. Meta search cannot reach
		// the post ID, so resolve it directly and float the exact match up.
		$id_term = ltrim( $search, '#' );

		if ( '' !== $id_term && ctype_digit( $id_term ) ) {

			$exact = absint( $id_term );

			if ( null !== $this->get_reservation_post( $exact ) ) {
				array_unshift( $ids, $exact );
				$ids = array_values( array_unique( $ids ) );
			}
		}

		$options = array();

		foreach ( $ids as $reservation_id ) {
			$options[] = array(
				'text'  => $this->get_reservation_label( $reservation_id ),
				'value' => (string) $reservation_id,
			);
		}

		return $options;
	}

	/**
	 * Reservation post IDs for the picker, newest first.
	 *
	 * @param string $search Optional search string.
	 *
	 * @return int[]
	 */
	private function query_reservation_ids( $search ) {

		$args = array(
			'post_type'        => self::POST_TYPE,
			// `any` rather than a status list, so the picker needs no
			// assumption about which statuses are registered. See the class
			// docblock.
			'post_status'      => 'any',
			'posts_per_page'   => apply_filters( 'automator_select_all_posts_limit', 999, self::POST_TYPE ), // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page
			'orderby'          => 'ID',
			'order'            => 'DESC',
			'fields'           => 'ids',
			'no_found_rows'    => true,
			'suppress_filters' => false,
		);

		if ( '' !== $search ) {
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- guest identity lives only in post meta; there is no indexed column to search.
			$args['meta_query'] = array(
				'relation' => 'OR',
				array(
					'key'     => 'name',
					'value'   => $search,
					'compare' => 'LIKE',
				),
				array(
					'key'     => 'email',
					'value'   => $search,
					'compare' => 'LIKE',
				),
				array(
					'key'     => 'date',
					'value'   => $search,
					'compare' => 'LIKE',
				),
			);
		}

		return array_map( 'absint', get_posts( $args ) );
	}

	/**
	 * One reservation as a single line a restaurant would recognize.
	 *
	 * The ID leads because it is what the field stores and what the Reservation
	 * ID token carries, so a builder can match the two by eye.
	 *
	 * @param int $reservation_id Reservation post ID.
	 *
	 * @return string
	 */
	public function get_reservation_label( $reservation_id ) {

		$reservation_id = absint( $reservation_id );

		$parts = array( '#' . $reservation_id );

		$name = (string) get_post_meta( $reservation_id, 'name', true );

		if ( '' !== $name ) {
			$parts[] = $name;
		}

		$date = (string) get_post_meta( $reservation_id, 'date', true );
		$time = $this->format_time( get_post_meta( $reservation_id, 'start_time', true ) );
		$when = trim( $date . ' ' . $time );

		if ( '' !== $when ) {
			$parts[] = $when;
		}

		return implode( ' - ', $parts );
	}

	/**
	 * Reservation picker segment (actions — no "Any" sentinel).
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_reservations_strict( $request ): array {
		return $this->remote_data_success( $this->get_reservation_options( $request->get_search_query() ) );
	}

	/**
	 * Whether a location ID can be rejected as wrong.
	 *
	 * The picker supports custom values so a token can supply the ID, which means
	 * the value still has to be checked. `Reservation_Model` does not check it —
	 * it validates attribute KEYS, not values — so a bogus ID is otherwise stored
	 * as meta and the booking silently belongs to no location.
	 *
	 * Returns false only when the ID is known to be wrong. A site with WPCafe's
	 * `location` module switched off has no such taxonomy to check against, and a
	 * recipe that was valid before the module was turned off keeps working rather
	 * than failing on a judgement this cannot make.
	 *
	 * @param string|int $branch_id Location term ID.
	 *
	 * @return bool
	 */
	public function is_location( $branch_id ) {

		if ( ! taxonomy_exists( self::LOCATION_TAXONOMY ) ) {
			return true;
		}

		$branch_id = absint( $branch_id );

		if ( 0 === $branch_id ) {
			return false;
		}

		return get_term( $branch_id, self::LOCATION_TAXONOMY ) instanceof \WP_Term;
	}

	/**
	 * Human-readable label for a status slug.
	 *
	 * @param string $status Status slug.
	 *
	 * @return string
	 */
	public function get_status_label( $status ) {
		$labels = $this->get_status_labels();

		return $labels[ $status ] ?? (string) $status;
	}

	/**
	 * The reservation's real status.
	 *
	 * Always read through `get_post_status()` — see the class docblock for why
	 * the model property cannot be used.
	 *
	 * @param int $reservation_id Reservation post ID.
	 *
	 * @return string Empty string when the post is missing.
	 */
	public function get_reservation_status( $reservation_id ) {
		return (string) get_post_status( absint( $reservation_id ) );
	}

	/**
	 * Fetch a reservation post, confirming it is really a reservation.
	 *
	 * @param int $reservation_id Reservation post ID.
	 *
	 * @return \WP_Post|null
	 */
	public function get_reservation_post( $reservation_id ) {

		$post = get_post( absint( $reservation_id ) );

		if ( null === $post || self::POST_TYPE !== $post->post_type ) {
			return null;
		}

		return $post;
	}

	/**
	 * Load a reservation as a WPCafe model.
	 *
	 * `Reservation_Model::find()` throws on a post-type mismatch, so the type
	 * is confirmed first and the constructor is still guarded.
	 *
	 * @param int $reservation_id Reservation post ID.
	 *
	 * @return Reservation_Model|null
	 */
	public function get_reservation_model( $reservation_id ) {

		if ( ! class_exists( '\WpCafe\Models\Reservation_Model' ) ) {
			return null;
		}

		if ( null === $this->get_reservation_post( $reservation_id ) ) {
			return null;
		}

		try {
			return Reservation_Model::find( absint( $reservation_id ) );
		} catch ( Exception $e ) {
			return null;
		}
	}

	/**
	 * Resolve the WordPress user who made a reservation.
	 *
	 * WPCafe stores no user ID on a reservation. Its author is whoever was logged
	 * in when the booking was made (0 for a guest), and WPCafe treats the author
	 * as the reservation's owner. The booking email is typed on the public form,
	 * so it never binds an account. Staff who book for a customer are not that
	 * customer, so a reservation they made binds nobody.
	 *
	 * @param int $reservation_id Reservation post ID.
	 *
	 * @return int 0 for a guest or staff booking.
	 */
	public function resolve_user_id( $reservation_id ) {

		$author_id = absint( get_post_field( 'post_author', absint( $reservation_id ) ) );

		if ( 0 === $author_id ) {
			return 0;
		}

		if ( user_can( $author_id, 'manage_options' ) || user_can( $author_id, 'wpcafe_manage_reservations' ) ) {
			return 0;
		}

		return $author_id;
	}

	/**
	 * Format a WPCafe time value for display.
	 *
	 * `start_time` and `end_time` are stored as Unix timestamps, not time
	 * strings. WPCafe renders them with `gmdate( 'h:i A', $ts )`; we match that
	 * so tokens read the same as the booking's own confirmation screen.
	 *
	 * @param mixed $timestamp Stored meta value.
	 *
	 * @return string
	 */
	public function format_time( $timestamp ) {

		if ( empty( $timestamp ) || ! is_numeric( $timestamp ) ) {
			return '';
		}

		return gmdate( 'h:i A', (int) $timestamp );
	}

	/**
	 * Convert a date + time pair into the Unix timestamp WPCafe stores.
	 *
	 * The create action's fields are `date` and `time` pickers, so the usual
	 * input is `2026-09-20` plus a 24-hour `19:00`. A 12-hour string such as
	 * `7:00 PM` also parses, and a value that is already a timestamp is passed
	 * through — either can arrive when the field carries a token from an
	 * earlier step rather than a picked value.
	 *
	 * @param string $date Date string, e.g. `2026-09-20`.
	 * @param string $time Time string, e.g. `19:00` or `7:00 PM`, or a Unix timestamp.
	 *
	 * @return string Empty string when the value cannot be parsed.
	 */
	public function to_timestamp( $date, $time ) {

		$time = trim( (string) $time );

		if ( '' === $time ) {
			return '';
		}

		if ( is_numeric( $time ) ) {
			return (string) (int) $time;
		}

		$timestamp = strtotime( trim( (string) $date ) . ' ' . $time );

		return false === $timestamp ? '' : (string) $timestamp;
	}

	/**
	 * The reservation token set shared by every WPCafe trigger.
	 *
	 * @return array
	 */
	public function get_reservation_tokens_config() {

		return array(
			array(
				'tokenId'   => 'RESERVATION_ID',
				'tokenName' => esc_html_x( 'Reservation ID', 'WPCafe', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'RESERVATION_STATUS',
				'tokenName' => esc_html_x( 'Reservation status', 'WPCafe', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'RESERVATION_NAME',
				'tokenName' => esc_html_x( 'Guest name', 'WPCafe', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'RESERVATION_EMAIL',
				'tokenName' => esc_html_x( 'Guest email', 'WPCafe', 'uncanny-automator' ),
				'tokenType' => 'email',
			),
			array(
				'tokenId'   => 'RESERVATION_PHONE',
				'tokenName' => esc_html_x( 'Guest phone', 'WPCafe', 'uncanny-automator' ),
				'tokenType' => 'tel',
			),
			array(
				'tokenId'   => 'RESERVATION_DATE',
				'tokenName' => esc_html_x( 'Reservation date', 'WPCafe', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'RESERVATION_START_TIME',
				'tokenName' => esc_html_x( 'Start time', 'WPCafe', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'RESERVATION_END_TIME',
				'tokenName' => esc_html_x( 'End time', 'WPCafe', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'RESERVATION_TOTAL_GUEST',
				'tokenName' => esc_html_x( 'Number of guests', 'WPCafe', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'RESERVATION_TABLE_NAME',
				'tokenName' => esc_html_x( 'Table name', 'WPCafe', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'RESERVATION_BRANCH_ID',
				'tokenName' => esc_html_x( 'Location ID', 'WPCafe', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'RESERVATION_BRANCH_NAME',
				'tokenName' => esc_html_x( 'Location name', 'WPCafe', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'RESERVATION_NOTES',
				'tokenName' => esc_html_x( 'Reservation notes', 'WPCafe', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'RESERVATION_INVOICE',
				'tokenName' => esc_html_x( 'Invoice number', 'WPCafe', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'RESERVATION_TOTAL_PRICE',
				'tokenName' => esc_html_x( 'Total price', 'WPCafe', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'RESERVATION_BOOKING_AMOUNT',
				'tokenName' => esc_html_x( 'Booking amount', 'WPCafe', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'RESERVATION_CURRENCY',
				'tokenName' => esc_html_x( 'Currency', 'WPCafe', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'RESERVATION_PAYMENT_METHOD',
				'tokenName' => esc_html_x( 'Payment method', 'WPCafe', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'RESERVATION_FOOD_ORDER',
				'tokenName' => esc_html_x( 'Includes a food order', 'WPCafe', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
		);
	}

	/**
	 * Hydrate the shared reservation token set.
	 *
	 * Returns the full keyset even when the reservation is gone, so a recipe
	 * never receives a partial token map.
	 *
	 * @param int $reservation_id Reservation post ID.
	 *
	 * @return array
	 */
	public function hydrate_reservation_tokens( $reservation_id ) {

		$reservation_id = absint( $reservation_id );

		$tokens = array(
			'RESERVATION_ID'             => $reservation_id,
			'RESERVATION_STATUS'         => '',
			'RESERVATION_NAME'           => '',
			'RESERVATION_EMAIL'          => '',
			'RESERVATION_PHONE'          => '',
			'RESERVATION_DATE'           => '',
			'RESERVATION_START_TIME'     => '',
			'RESERVATION_END_TIME'       => '',
			'RESERVATION_TOTAL_GUEST'    => '',
			'RESERVATION_TABLE_NAME'     => '',
			'RESERVATION_BRANCH_ID'      => '',
			'RESERVATION_BRANCH_NAME'    => '',
			'RESERVATION_NOTES'          => '',
			'RESERVATION_INVOICE'        => '',
			'RESERVATION_TOTAL_PRICE'    => '',
			'RESERVATION_BOOKING_AMOUNT' => '',
			'RESERVATION_CURRENCY'       => '',
			'RESERVATION_PAYMENT_METHOD' => '',
			'RESERVATION_FOOD_ORDER'     => '',
		);

		if ( null === $this->get_reservation_post( $reservation_id ) ) {
			return $tokens;
		}

		$meta = array(
			'RESERVATION_NAME'           => 'name',
			'RESERVATION_EMAIL'          => 'email',
			'RESERVATION_PHONE'          => 'phone',
			'RESERVATION_DATE'           => 'date',
			'RESERVATION_TOTAL_GUEST'    => 'total_guest',
			'RESERVATION_TABLE_NAME'     => 'table_name',
			'RESERVATION_BRANCH_ID'      => 'branch_id',
			'RESERVATION_BRANCH_NAME'    => 'branch_name',
			'RESERVATION_NOTES'          => 'notes',
			'RESERVATION_INVOICE'        => 'invoice',
			'RESERVATION_TOTAL_PRICE'    => 'total_price',
			'RESERVATION_BOOKING_AMOUNT' => 'booking_amount',
			'RESERVATION_CURRENCY'       => 'currency',
			'RESERVATION_PAYMENT_METHOD' => 'payment_method',
			'RESERVATION_FOOD_ORDER'     => 'food_order',
		);

		foreach ( $meta as $token_id => $meta_key ) {
			$tokens[ $token_id ] = (string) get_post_meta( $reservation_id, $meta_key, true );
		}

		if ( '' === $tokens['RESERVATION_TABLE_NAME'] ) {
			$tokens['RESERVATION_TABLE_NAME'] = $this->get_table_names_from_seats(
				get_post_meta( $reservation_id, 'seats', true ),
				$tokens['RESERVATION_BRANCH_ID']
			);
		}

		if ( '' === $tokens['RESERVATION_BRANCH_NAME'] ) {
			$tokens['RESERVATION_BRANCH_NAME'] = $this->get_location_name( $tokens['RESERVATION_BRANCH_ID'] );
		}

		$tokens['RESERVATION_STATUS']     = $this->get_status_label( $this->get_reservation_status( $reservation_id ) );
		$tokens['RESERVATION_START_TIME'] = $this->format_time( get_post_meta( $reservation_id, 'start_time', true ) );
		$tokens['RESERVATION_END_TIME']   = $this->format_time( get_post_meta( $reservation_id, 'end_time', true ) );

		return $tokens;
	}

	/**
	 * Re-broadcast the WPCafe lifecycle hooks after writing through the model.
	 *
	 * `Reservation_Model` fires nothing — the REST controller does. Anything
	 * that writes through the model directly therefore leaves WPCafe's own
	 * reservation emails and WPCafe Pro's outbound webhooks silent. This
	 * mirrors the controller's guard order, but passes the real previous
	 * status: the controller reads it from the model property, which is always
	 * an empty string, so its own `$old_status` argument is unusable.
	 *
	 * @param Reservation_Model $reservation The written reservation.
	 * @param string            $old_status  Status before the write.
	 * @param string            $new_status  Status after the write.
	 *
	 * @return void
	 */
	public function fire_status_change_hooks( $reservation, $old_status, $new_status ) {

		if ( $old_status === $new_status ) {
			return;
		}

		if ( 'cancelled' === $new_status ) {
			do_action( 'wpcafe_after_reservation_cancelled', $reservation );

			return;
		}

		do_action( 'wpcafe_after_reservation_status_changed', $reservation, $old_status );
	}
}
