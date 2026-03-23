<?php


namespace Uncanny_Automator;

use Tribe__Tickets__Tickets_Handler;
use Uncanny_Automator_Pro\Event_Tickets_Pro_Helpers;

/**
 * Class Event_Tickets_Helpers
 *
 * @package Uncanny_Automator
 */
class Event_Tickets_Helpers {

	/**
	 * Internal action fired by all normalized ticket provider hooks.
	 *
	 * @var string
	 */
	const USER_REGISTERED_ACTION = 'automator_event_tickets_user_registered';

	/**
	 * @var Event_Tickets_Helpers
	 */
	public $options;

	/**
	 * @var Event_Tickets_Pro_Helpers
	 */
	public $pro;

	/**
	 * @var bool
	 */
	public $load_options = true;

	/**
	 * Whether the normalized hooks have been registered.
	 *
	 * @var bool
	 */
	private static $hooks_registered = false;

	/**
	 * Event_Tickets_Helpers constructor.
	 */
	public function __construct() {
		$this->register_normalized_hooks();
	}

	/**
	 * Register listeners for all TEC ticket provider hooks and normalize
	 * them into a single internal action with a consistent signature.
	 *
	 * Providers: RSVP, WooCommerce, PayPal (TPP), and Tickets Commerce (TC).
	 *
	 * @return void
	 */
	public function register_normalized_hooks() {

		if ( true === self::$hooks_registered ) {
			return;
		}

		self::$hooks_registered = true;

		// Legacy RSVP.
		add_action( 'event_tickets_rsvp_tickets_generated_for_product', array( $this, 'normalize_rsvp_registration' ), 10, 3 );

		// Legacy WooCommerce (via Event Tickets Plus).
		add_action( 'event_tickets_woocommerce_tickets_generated_for_product', array( $this, 'normalize_woo_registration' ), 10, 4 );

		// Legacy PayPal / Tribe Commerce.
		add_action( 'event_tickets_tpp_tickets_generated_for_product', array( $this, 'normalize_tpp_registration' ), 10, 3 );

		// Modern Tickets Commerce.
		add_action( 'tec_tickets_commerce_attendee_after_create', array( $this, 'normalize_tc_registration' ), 10, 4 );
	}

	/**
	 * Normalize RSVP ticket registration.
	 *
	 * @param int    $product_id RSVP ticket post ID.
	 * @param string $order_id   ID (hash) of the RSVP order.
	 * @param int    $qty        Quantity ordered.
	 *
	 * @return void
	 */
	public function normalize_rsvp_registration( $product_id, $order_id, $qty ) {

		$event = tribe_events_get_ticket_event( $product_id );

		if ( ! $event instanceof \WP_Post ) {
			return;
		}

		do_action( self::USER_REGISTERED_ACTION, $event->ID, $product_id, $order_id, get_current_user_id() );
	}

	/**
	 * Normalize WooCommerce ticket registration.
	 *
	 * @param int $product_id WooCommerce ticket post ID.
	 * @param int $order_id   ID of the WooCommerce order.
	 * @param int $quantity   Quantity ordered.
	 * @param int $post_id    ID of the event.
	 *
	 * @return void
	 */
	public function normalize_woo_registration( $product_id, $order_id, $quantity, $post_id ) {

		$user_id = get_current_user_id();

		// Fallback: retrieve customer from WooCommerce order (e.g. background/cron processing).
		if ( 0 === $user_id && function_exists( 'wc_get_order' ) ) {
			$order = wc_get_order( $order_id );
			if ( $order ) {
				$user_id = absint( $order->get_customer_id() );
			}
		}

		do_action( self::USER_REGISTERED_ACTION, absint( $post_id ), $product_id, $order_id, $user_id );
	}

	/**
	 * Normalize PayPal / Tribe Commerce ticket registration.
	 *
	 * @param int    $product_id PayPal ticket post ID.
	 * @param string $order_id   ID of the PayPal order.
	 * @param int    $qty        Quantity ordered.
	 *
	 * @return void
	 */
	public function normalize_tpp_registration( $product_id, $order_id, $qty ) {

		$event = tribe_events_get_ticket_event( $product_id );

		if ( ! $event instanceof \WP_Post ) {
			return;
		}

		do_action( self::USER_REGISTERED_ACTION, $event->ID, $product_id, $order_id, get_current_user_id() );
	}

	/**
	 * Normalize Tickets Commerce registration.
	 *
	 * @param \WP_Post $attendee Attendee post object.
	 * @param \WP_Post $order    Order post object.
	 * @param object   $ticket   Ticket object (Tribe__Tickets__Ticket_Object).
	 * @param array    $args     Extra arguments used to populate attendee data.
	 *
	 * @return void
	 */
	public function normalize_tc_registration( $attendee, $order, $ticket, $args ) {

		if ( ! $attendee instanceof \WP_Post ) {
			return;
		}

		// Resolve event ID from ticket first (most reliable at creation time), then attendee.
		$event_id = is_callable( array( $ticket, 'get_event_id' ) ) ? absint( $ticket->get_event_id() ) : 0;

		if ( 0 === $event_id && ! empty( $attendee->event_id ) ) {
			$event_id = absint( $attendee->event_id );
		}

		$product_id = is_object( $ticket ) && ! empty( $ticket->ID ) ? absint( $ticket->ID ) : 0;
		$order_id   = $order instanceof \WP_Post ? $order->ID : 0;

		// Resolve user from order purchaser data, then fallback to current user.
		$user_id = ! empty( $order->purchaser['user_id'] ) ? absint( $order->purchaser['user_id'] ) : 0;

		if ( 0 === $user_id ) {
			$user_id = get_current_user_id();
		}

		do_action( self::USER_REGISTERED_ACTION, $event_id, $product_id, $order_id, $user_id );
	}

	/**
	 * @param Event_Tickets_Helpers $options
	 */
	public function setOptions( Event_Tickets_Helpers $options ) {
		$this->options = $options;
	}

	/**
	 * @param Event_Tickets_Pro_Helpers $pro
	 */
	public function setPro( Event_Tickets_Pro_Helpers $pro ) {
		$this->pro = $pro;
	}

	/**
	 * @param string $label
	 * @param string $option_code
	 *
	 * @return mixed
	 */
	public function all_ec_events( $label = null, $option_code = 'ECEVENTS', $extra_args = array() ) {
		if ( ! $this->load_options ) {

			return Automator()->helpers->recipe->build_default_options_array( $label, $option_code );
		}

		if ( ! $label ) {
			$label = esc_attr__( 'Event', 'uncanny-automator' );
		}

		$is_ajax      = key_exists( 'is_ajax', $extra_args ) ? $extra_args['is_ajax'] : false;
		$target_field = key_exists( 'target_field', $extra_args ) ? $extra_args['target_field'] : '';
		$end_point    = key_exists( 'endpoint', $extra_args ) ? $extra_args['endpoint'] : '';

		$args = array(
			'posts_per_page' => 9999,
			'orderby'        => 'title',
			'order'          => 'DESC',
			'post_type'      => 'tribe_events',
			'post_status'    => 'publish',
		);

		$all_events = Automator()->helpers->recipe->options->wp_query( $args, true, esc_html__( 'Any event', 'uncanny-automator' ) );

		$option = array(
			'option_code'     => $option_code,
			'label'           => $label,
			'input_type'      => 'select',
			'required'        => true,
			'is_ajax'         => $is_ajax,
			'fill_values_in'  => $target_field,
			'endpoint'        => $end_point,
			//'default_value'      => 'Any post',
			'options'         => $all_events,
			'relevant_tokens' => array(
				$option_code                => esc_attr__( 'Event title', 'uncanny-automator' ),
				$option_code . '_ID'        => esc_attr__( 'Event ID', 'uncanny-automator' ),
				$option_code . '_URL'       => esc_attr__( 'Event URL', 'uncanny-automator' ),
				$option_code . '_THUMB_ID'  => esc_attr__( 'Event featured image ID', 'uncanny-automator' ),
				$option_code . '_THUMB_URL' => esc_attr__( 'Event featured image URL', 'uncanny-automator' ),
			),
		);

		return apply_filters( 'uap_option_all_ec_events', $option );
	}

	/**
	 * @param string $label
	 * @param string $option_code
	 *
	 * @return mixed
	 */
	public function all_ec_rsvp_events( $label = null, $option_code = 'ECEVENTS' ) {
		if ( ! $this->load_options ) {

			return Automator()->helpers->recipe->build_default_options_array( $label, $option_code );
		}

		if ( ! $label ) {
			$label = esc_attr__( 'Event', 'uncanny-automator' );
		}

		$args    = array(
			'posts_per_page' => 9999,
			'orderby'        => 'title',
			'order'          => 'DESC',
			'post_type'      => 'tribe_events',
			'post_status'    => 'publish',
		);
		$options = array();

		if ( Automator()->helpers->recipe->load_helpers ) {
			//$posts          = get_posts( $args );
			$posts          = Automator()->helpers->recipe->options->wp_query( $args );
			$ticket_handler = new Tribe__Tickets__Tickets_Handler();
			foreach ( $posts as $post_id => $title ) {
				//$title = $post->post_title;

				if ( empty( $title ) ) {
					// translators: 1: Event ID
					$title = sprintf( esc_attr__( 'ID: %1$s (no title)', 'uncanny-automator' ), $post_id );
				}

				$rsvp_ticket = $ticket_handler->get_event_rsvp_tickets( get_post( $post_id ) );

				if ( ! empty( $rsvp_ticket ) ) {
					$options[ $post_id ] = $title;
				}
			}
		}
		$option = array(
			'option_code'     => $option_code,
			'label'           => $label,
			'input_type'      => 'select',
			'required'        => true,
			//'default_value'      => 'Any post',
			'options'         => $options,
			'relevant_tokens' => array(
				$option_code          => esc_attr__( 'Event title', 'uncanny-automator' ),
				$option_code . '_ID'  => esc_attr__( 'Event ID', 'uncanny-automator' ),
				$option_code . '_URL' => esc_attr__( 'Event URL', 'uncanny-automator' ),
			),
		);

		return apply_filters( 'uap_option_all_ec_events', $option );
	}
}
