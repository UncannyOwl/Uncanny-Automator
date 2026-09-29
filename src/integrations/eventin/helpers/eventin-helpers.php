<?php

namespace Uncanny_Automator\Integrations\Eventin;

use Etn\Core\Event\Event_Model;
use Uncanny_Automator\Recipe\Abstract_Helpers;

/**
 * Class Eventin_Helpers
 *
 * Shared logic for the Eventin integration: the event / ticket / attendee /
 * order pickers and their remote_data segments, the order-status vocabulary,
 * and the check-in guards shared by the trigger and the action.
 *
 * Segment naming follows the framework convention — the bare segment carries
 * the "Any" sentinel and is consumed by triggers; the `_strict` variant omits
 * it and is consumed by actions.
 *
 * @package Uncanny_Automator\Integrations\Eventin
 */
class Eventin_Helpers extends Abstract_Helpers {

	/**
	 * "Any" sentinel shared by the trigger dropdowns.
	 *
	 * @var string
	 */
	const ANY = '-1';

	/**
	 * Event post type. Registered at core/event/cpt.php:17.
	 *
	 * @var string
	 */
	const EVENT_POST_TYPE = 'etn';

	/**
	 * Attendee post type. Registered at core/Attendee/cpt.php:36.
	 *
	 * @var string
	 */
	const ATTENDEE_POST_TYPE = 'etn-attendee';

	/**
	 * Order post type. Registered at core/Order/OrderModel.php:26.
	 *
	 * @var string
	 */
	const ORDER_POST_TYPE = 'etn-order';

	/**
	 * Attendee meta holding the parent event ID.
	 *
	 * @var string
	 */
	const ATTENDEE_EVENT_META = 'etn_event_id';

	/**
	 * Attendee meta holding the check-in state. Values are `unused` / `used`
	 * (core/Attendee/attendee-model.php:36, eventin-pro/core/attendee/hooks.php:982).
	 *
	 * Note the three e's in "attendeee" — the typo is Eventin's, and it is the
	 * real key name in the database.
	 *
	 * @var string
	 */
	const ATTENDEE_TICKET_STATUS_META = 'etn_attendeee_ticket_status';

	/**
	 * Attendee meta holding the check-in timestamp.
	 *
	 * @var string
	 */
	const ATTENDEE_CHECKIN_TIME_META = 'scanner_update_time';

	/**
	 * Attendee meta holding the payment state. Values are
	 * `success` / `failed` / `pending` / `waiting`.
	 *
	 * @var string
	 */
	const ATTENDEE_PAYMENT_STATUS_META = 'etn_status';

	/**
	 * Order statuses Eventin's own REST route accepts on an update
	 * (core/Order/OrderController.php:811).
	 *
	 * The full stored vocabulary is wider — `pending` and `waiting` are set by
	 * the checkout flow and `cancelled` only ever arrives from WooCommerce — but
	 * those are states Eventin refuses to let anyone set directly, so the action
	 * does not offer them either.
	 *
	 * @var string[]
	 */
	const SETTABLE_ORDER_STATUSES = array( 'completed', 'failed', 'refunded', 'partially_refunded' );

	/**
	 * Whether Eventin is active.
	 *
	 * @return bool
	 */
	public function eventin_active() {
		return class_exists( '\Wpeventin' );
	}

	/**
	 * All Eventin events as dropdown options.
	 *
	 * @param bool $include_any Whether to prepend the "Any event" sentinel.
	 *
	 * @return array
	 */
	public function get_event_options( $include_any = true ) {

		$options = array();

		if ( true === $include_any ) {
			$options[] = array(
				'value' => self::ANY,
				'text'  => esc_html_x( 'Any event', 'Eventin', 'uncanny-automator' ),
			);
		}

		$events = get_posts(
			array(
				'post_type'        => self::EVENT_POST_TYPE,
				'post_status'      => array( 'publish', 'draft', 'pending', 'private' ),
				// phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page
				'posts_per_page'   => apply_filters( 'automator_select_all_posts_limit', 999, self::EVENT_POST_TYPE ),
				'orderby'          => 'title',
				'order'            => 'ASC',
				'suppress_filters' => false,
			)
		);

		foreach ( $events as $event ) {
			$options[] = array(
				'value' => (string) $event->ID,
				'text'  => $event->post_title,
			);
		}

		return $options;
	}

	/**
	 * Ticket variations on an event, as dropdown options.
	 *
	 * Variations live in the `etn_ticket_variations` post meta; each row carries
	 * `etn_ticket_name` / `etn_ticket_slug` / `etn_ticket_price` /
	 * `etn_avaiilable_tickets` (three i's — Eventin's typo) / `etn_sold_tickets`
	 * (core/event/Api/EventController.php:2358-2360). The dropdown value is the
	 * SLUG, because that is what an order's `tickets[]` rows carry
	 * (core/Order/OrderModel.php:80-84).
	 *
	 * @param int  $event_id    The event post ID.
	 * @param bool $include_any Whether to prepend the "Any ticket" sentinel.
	 *
	 * @return array
	 */
	public function get_ticket_options( $event_id, $include_any = true ) {

		$options = array();

		if ( true === $include_any ) {
			$options[] = array(
				'value' => self::ANY,
				'text'  => esc_html_x( 'Any ticket', 'Eventin', 'uncanny-automator' ),
			);
		}

		foreach ( $this->get_ticket_variations( $event_id ) as $variation ) {

			$slug = (string) ( $variation['etn_ticket_slug'] ?? '' );

			if ( '' === $slug ) {
				continue;
			}

			$options[] = array(
				'value' => $slug,
				'text'  => (string) ( $variation['etn_ticket_name'] ?? $slug ),
			);
		}

		return $options;
	}

	/**
	 * Raw ticket-variation rows for an event.
	 *
	 * @param int $event_id The event post ID.
	 *
	 * @return array
	 */
	public function get_ticket_variations( $event_id ) {

		$event_id = absint( $event_id );

		if ( 0 === $event_id || self::EVENT_POST_TYPE !== get_post_type( $event_id ) ) {
			return array();
		}

		$variations = get_post_meta( $event_id, 'etn_ticket_variations', true );

		return is_array( $variations ) ? $variations : array();
	}

	/**
	 * A single ticket variation by slug, or an empty array.
	 *
	 * @param int    $event_id The event post ID.
	 * @param string $slug     The ticket slug.
	 *
	 * @return array
	 */
	public function get_ticket_variation( $event_id, $slug ) {

		$slug = (string) $slug;

		foreach ( $this->get_ticket_variations( $event_id ) as $variation ) {

			$variation_slug = (string) ( $variation['etn_ticket_slug'] ?? '' );

			if ( $slug === $variation_slug ) {
				return $variation;
			}
		}

		return array();
	}

	/**
	 * Attendees of an event as dropdown options.
	 *
	 * @param int $event_id The event post ID.
	 *
	 * @return array
	 */
	public function get_attendee_options( $event_id ) {

		$event_id = absint( $event_id );
		$options  = array();

		if ( 0 === $event_id ) {
			return $options;
		}

		$attendees = get_posts(
			array(
				'post_type'        => self::ATTENDEE_POST_TYPE,
				'post_status'      => array( 'publish', 'draft', 'pending', 'private' ),
				// phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page
				'posts_per_page'   => apply_filters( 'automator_select_all_posts_limit', 999, self::ATTENDEE_POST_TYPE ),
				'orderby'          => 'title',
				'order'            => 'ASC',
				'suppress_filters' => false,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_key'         => self::ATTENDEE_EVENT_META,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'meta_value'       => (string) $event_id,
			)
		);

		foreach ( $attendees as $attendee ) {

			$email = (string) get_post_meta( $attendee->ID, 'etn_email', true );
			$name  = (string) get_post_meta( $attendee->ID, 'etn_name', true );
			$name  = '' === $name ? $attendee->post_title : $name;

			$options[] = array(
				'value' => (string) $attendee->ID,
				'text'  => '' === $email ? $name : $name . ' (' . $email . ')',
			);
		}

		return $options;
	}

	/**
	 * Orders as dropdown options, newest first.
	 *
	 * @return array
	 */
	public function get_order_options() {

		$options = array();

		$orders = get_posts(
			array(
				'post_type'        => self::ORDER_POST_TYPE,
				'post_status'      => array( 'publish', 'draft', 'pending', 'private' ),
				// phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page
				'posts_per_page'   => apply_filters( 'automator_select_all_posts_limit', 999, self::ORDER_POST_TYPE ),
				'orderby'          => 'date',
				'order'            => 'DESC',
				'suppress_filters' => false,
			)
		);

		foreach ( $orders as $order ) {

			$email = (string) get_post_meta( $order->ID, 'customer_email', true );

			$options[] = array(
				'value' => (string) $order->ID,
				'text'  => '' === $email
					? sprintf( '#%d', $order->ID )
					: sprintf( '#%1$d — %2$s', $order->ID, $email ),
			);
		}

		return $options;
	}

	/**
	 * Order statuses the update action may set.
	 *
	 * @return array
	 */
	public function get_order_status_options() {
		return array(
			array(
				'value' => 'completed',
				'text'  => esc_html_x( 'Completed', 'Eventin', 'uncanny-automator' ),
			),
			array(
				'value' => 'failed',
				'text'  => esc_html_x( 'Failed', 'Eventin', 'uncanny-automator' ),
			),
			array(
				'value' => 'refunded',
				'text'  => esc_html_x( 'Refunded', 'Eventin', 'uncanny-automator' ),
			),
			array(
				'value' => 'partially_refunded',
				'text'  => esc_html_x( 'Partially refunded', 'Eventin', 'uncanny-automator' ),
			),
		);
	}

	/**
	 * Whether an order status transition is one Eventin permits.
	 *
	 * Mirrors the three refusals Eventin's own REST route enforces
	 * (core/Order/OrderController.php:837-859). OrderModel::update() enforces
	 * none of them, so the action has to.
	 *
	 * @param string $from The current status.
	 * @param string $to   The requested status.
	 *
	 * @return string Empty string when the transition is allowed, else the reason.
	 */
	public function get_status_transition_error( $from, $to ) {

		$from = (string) $from;
		$to   = (string) $to;

		if ( 'failed' === $from && 'refunded' === $to ) {
			return 'A failed order cannot be refunded.';
		}

		if ( 'refunded' === $from && 'failed' === $to ) {
			return 'A refunded order cannot be marked failed.';
		}

		if ( 'partially_refunded' === $from && ! in_array( $to, array( 'partially_refunded', 'refunded', 'completed' ), true ) ) {
			return 'A partially refunded order can only be fully refunded or restored to completed.';
		}

		return '';
	}

	/**
	 * The Eventin lifecycle hook that matches an order status, or an empty string.
	 *
	 * OrderModel::update() fires none of these itself, which leaves attendee
	 * payment statuses and coupon redemption counts stale
	 * (core/Order/OrderAttendee.php:18-20, core/Coupon/CouponRedemptionHandler.php:37-38).
	 *
	 * @param string $status The new status.
	 *
	 * @return string
	 */
	public function get_status_hook( $status ) {

		$map = array(
			'completed' => 'eventin_order_status_completed',
			'failed'    => 'eventin_order_status_failed',
			'refunded'  => 'eventin_order_refund',
		);

		return (string) ( $map[ (string) $status ] ?? '' );
	}

	/**
	 * Read the post ID off an Eventin model.
	 *
	 * `Etn\Base\Post_Model::$id` is a real public property (base/post-model.php:41),
	 * so it is safe to read directly. EVERY other property on those models is
	 * magic — served by `__get()` from the `$data` map — and the class defines no
	 * `__isset()`, which means `$model->event_id ?? 0` silently evaluates to 0
	 * rather than reading the value, and a bare `$model->unknown_key` throws an
	 * Exception (base/post-model.php:65-79). So: take the ID from the model, then
	 * read every other field from post meta.
	 *
	 * That is not a workaround — `Post_Model::get_data()` (base/post-model.php:258)
	 * resolves each property with `get_post_meta( $this->id, $key, true )` and
	 * every Eventin model inherits an empty `$meta_prefix`, so the meta key and
	 * the property name are the same string.
	 *
	 * @param mixed $model An Eventin model, or a post ID.
	 *
	 * @return int
	 */
	public function get_model_id( $model ) {

		if ( is_object( $model ) ) {
			return absint( $model->id ?? 0 );
		}

		return absint( $model );
	}

	/**
	 * Whether a post is an Eventin attendee.
	 *
	 * @param int $attendee_id The attendee post ID.
	 *
	 * @return bool
	 */
	public function is_attendee( $attendee_id ) {
		return self::ATTENDEE_POST_TYPE === get_post_type( absint( $attendee_id ) );
	}

	/**
	 * The event an attendee belongs to, or 0.
	 *
	 * @param int $attendee_id The attendee post ID.
	 *
	 * @return int
	 */
	public function get_attendee_event_id( $attendee_id ) {
		return absint( get_post_meta( absint( $attendee_id ), self::ATTENDEE_EVENT_META, true ) );
	}

	/**
	 * Resolve a WordPress user from an email address.
	 *
	 * Eventin attendees carry only an email — guest checkout is fully supported
	 * and produces no WP user at all (core/Order/OrderController.php:2134), so
	 * an unresolved address legitimately returns 0.
	 *
	 * @param string $email The email address.
	 *
	 * @return int
	 */
	public function get_user_id_by_email( $email ) {

		$email = (string) $email;

		if ( '' === $email || ! is_email( $email ) ) {
			return 0;
		}

		$user = get_user_by( 'email', $email );

		return $user instanceof \WP_User ? absint( $user->ID ) : 0;
	}

	/**
	 * The WordPress user who bought an order, or 0 for a guest checkout.
	 *
	 * Eventin stamps the order's author with the user whose session placed it.
	 * `customer_id` is not trusted on its own: on a guest checkout Eventin links
	 * it to whichever account owns the email the buyer typed. It is used only
	 * when staff placed the order for someone else. A guest order paid through
	 * WooCommerce binds the customer WooCommerce recorded.
	 *
	 * @param int $order_id The order post ID.
	 *
	 * @return int
	 */
	public function get_order_buyer_id( $order_id ) {

		$order_id = absint( $order_id );

		if ( 0 === $order_id ) {
			return 0;
		}

		$author_id = absint( get_post_field( 'post_author', $order_id ) );

		if ( 0 === $author_id ) {
			return $this->get_wc_customer_id( $order_id );
		}

		$customer_id = get_post_meta( $order_id, 'customer_id', true );
		$customer_id = is_numeric( $customer_id ) ? absint( $customer_id ) : 0;
		$is_staff    = user_can( $author_id, 'manage_options' ) || user_can( $author_id, 'etn_manage_order' );

		if ( 0 !== $customer_id && $customer_id !== $author_id && $is_staff ) {
			return $customer_id;
		}

		return $author_id;
	}

	/**
	 * The customer WooCommerce recorded on the order that paid for an Eventin
	 * order, or 0.
	 *
	 * WooCommerce records the logged-in buyer or the account created at its own
	 * checkout; it never matches an account by email. Eventin keeps the link in
	 * the WooCommerce order's post meta.
	 *
	 * @param int $order_id The Eventin order post ID.
	 *
	 * @return int
	 */
	private function get_wc_customer_id( $order_id ) {

		if ( ! function_exists( 'wc_get_order' ) ) {
			return 0;
		}

		$wc_order_ids = get_posts(
			array(
				'post_type'      => array( 'shop_order', 'shop_order_placehold' ),
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_key'       => 'eventin_order_id',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'meta_value'     => (string) $order_id,
			)
		);

		$wc_order = empty( $wc_order_ids ) ? false : wc_get_order( $wc_order_ids[0] );

		return $wc_order instanceof \WC_Order ? absint( $wc_order->get_customer_id() ) : 0;
	}

	/**
	 * An event's lifecycle status key — `upcoming` / `ongoing` / `expired`.
	 *
	 * Event_Model::get_status() returns a ['key','value'] array for published
	 * events with parseable dates and a bare post-status STRING otherwise
	 * (core/event/event-model.php:194, 201, 233, 236). Both shapes are
	 * normalized to a string here.
	 *
	 * @param int $event_id The event post ID.
	 *
	 * @return string
	 */
	public function get_event_status( $event_id ) {

		$event_id = absint( $event_id );

		if ( 0 === $event_id || ! class_exists( '\Etn\Core\Event\Event_Model' ) ) {
			return '';
		}

		$status = ( new Event_Model( $event_id ) )->get_status();

		if ( is_array( $status ) ) {
			return (string) ( $status['key'] ?? '' );
		}

		return (string) $status;
	}

	/**
	 * Remote_Data segment: events with the "Any" sentinel (triggers).
	 *
	 * @param mixed $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_events( $request ): array {
		unset( $request );
		return $this->remote_data_success( $this->get_event_options( true ) );
	}

	/**
	 * Remote_Data segment: events without the "Any" sentinel (actions).
	 *
	 * @param mixed $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_events_strict( $request ): array {
		unset( $request );
		return $this->remote_data_success( $this->get_event_options( false ) );
	}

	/**
	 * Remote_Data segment: tickets on the selected event, with the "Any"
	 * sentinel (triggers).
	 *
	 * @param mixed $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_tickets( $request ): array {
		return $this->remote_data_success(
			$this->get_ticket_options( $this->request_value( $request, 'EVENTIN_EVENT' ), true )
		);
	}

	/**
	 * Remote_Data segment: tickets on the selected event, without the "Any"
	 * sentinel (actions).
	 *
	 * @param mixed $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_tickets_strict( $request ): array {
		return $this->remote_data_success(
			$this->get_ticket_options( $this->request_value( $request, 'EVENTIN_EVENT' ), false )
		);
	}

	/**
	 * Remote_Data segment: attendees of the selected event (actions).
	 *
	 * @param mixed $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_attendees_strict( $request ): array {
		return $this->remote_data_success(
			$this->get_attendee_options( $this->request_value( $request, 'EVENTIN_EVENT' ) )
		);
	}

	/**
	 * Remote_Data segment: orders (actions).
	 *
	 * @param mixed $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_orders_strict( $request ): array {
		unset( $request );
		return $this->remote_data_success( $this->get_order_options() );
	}

	/**
	 * Read a parent field value off a remote-data request.
	 *
	 * @param mixed  $request The remote-data request.
	 * @param string $key     The parent option code.
	 *
	 * @return int
	 */
	private function request_value( $request, $key ) {

		if ( ! is_object( $request ) || ! method_exists( $request, 'get_field_value' ) ) {
			return 0;
		}

		return absint( $request->get_field_value( $key ) );
	}
}
