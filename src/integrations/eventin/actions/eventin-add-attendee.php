<?php

namespace Uncanny_Automator\Integrations\Eventin;

use Eventin\Attendee\Attendee\TicketIdGenerator;
use Eventin\Customer\CustomerModel;
use Eventin\Order\OrderModel;
use Etn\Core\Attendee\Attendee_Model;
use Etn\Utils\Helper;
use Uncanny_Automator\Recipe\Action;

/**
 * Class Eventin_Add_Attendee
 *
 * Comps a ticket: creates the customer, the order and the attendee, in the same
 * sequence and with the same generated identifiers Eventin's own REST route
 * uses (AttendeeController::create_item(), core/Attendee/Api/AttendeeController.php:423-486).
 *
 * Three details in that sequence are load-bearing and easy to miss:
 *
 * 1. `Post_Model::create()` defaults `post_status` to `draft`
 *    (base/post-model.php:111). Eventin's REST path forces `publish` in
 *    prepare_item_for_database() (…/AttendeeController.php:837); this action
 *    passes it explicitly for the same reason — a draft attendee is invisible.
 * 2. `etn_unique_ticket_id` must be generated. The QR scanner matches against
 *    it (eventin-pro/core/attendee/hooks.php:957), so an attendee created
 *    without one can never be checked in.
 * 3. `etn_attendeee_ticket_status` must be written as 'unused'.
 *    `Post_Model::update_meta()` only writes keys present in the passed args
 *    (base/post-model.php:211), so the model's own default is never persisted
 *    on its own — and without that meta row the check-in trigger's
 *    `updated_post_meta` path would not fire for this attendee.
 *
 * `Attendee_Model::create()` is called on an instance, never statically:
 * `Post_Model::__callStatic()` (base/post-model.php:93) does `new self` on an
 * abstract class and then `call_user_func( $method, … )`, which looks for a
 * global function — it is dead code, and Eventin never uses it either.
 *
 * @package Uncanny_Automator\Integrations\Eventin
 *
 * @property Eventin_Helpers $item_helpers
 */
class Eventin_Add_Attendee extends Action {

	/**
	 * Option code of the ticket field.
	 */
	const TICKET = 'EVENTIN_TICKET';

	/**
	 * Option code of the attendee name field.
	 */
	const NAME = 'EVENTIN_ATTENDEE_NAME';

	/**
	 * Option code of the attendee email field.
	 */
	const EMAIL = 'EVENTIN_ATTENDEE_EMAIL';

	/**
	 * Option code of the attendee phone field.
	 */
	const PHONE = 'EVENTIN_ATTENDEE_PHONE';

	/**
	 * Option code of the order status field.
	 */
	const STATUS = 'EVENTIN_ORDER_STATUS';

	/**
	 * Setup action.
	 *
	 * @return void
	 */
	protected function setup_action() {

		$this->set_integration( 'EVENTIN' );
		$this->set_action_code( 'EVENTIN_ADD_ATTENDEE' );
		$this->set_action_meta( 'EVENTIN_EVENT' );
		$this->set_requires_user( true );

		$this->set_sentence(
			sprintf(
				/* translators: 1: Eventin event */
				esc_html_x( 'Add the user as an attendee to {{an event:%1$s}}', 'Eventin', 'uncanny-automator' ),
				$this->get_action_meta()
			)
		);

		$this->set_readable_sentence( esc_html_x( 'Add the user as an attendee to {{an event}}', 'Eventin', 'uncanny-automator' ) );
	}

	/**
	 * Options.
	 *
	 * @return array
	 */
	public function options() {
		return array(
			array(
				'option_code' => $this->get_action_meta(),
				'label'       => esc_html_x( 'Event', 'Eventin', 'uncanny-automator' ),
				'input_type'  => 'select',
				'required'    => true,
				'options'     => array(),
				'remote_data' => $this->item_helpers->remote_data_load_config( 'events_strict' ),
			),
			array(
				'option_code' => self::TICKET,
				'label'       => esc_html_x( 'Ticket', 'Eventin', 'uncanny-automator' ),
				'input_type'  => 'select',
				'required'    => true,
				'options'     => array(),
				'remote_data' => $this->item_helpers->remote_data_parent_config(
					'tickets_strict',
					array( $this->get_action_meta() )
				),
			),
			array(
				'option_code'     => self::NAME,
				'label'           => esc_html_x( 'Attendee name', 'Eventin', 'uncanny-automator' ),
				'input_type'      => 'text',
				'required'        => false,
				'supports_tokens' => true,
				'description'     => esc_html_x( "Leave empty to use the user's display name.", 'Eventin', 'uncanny-automator' ),
			),
			array(
				'option_code'     => self::EMAIL,
				'label'           => esc_html_x( 'Attendee email', 'Eventin', 'uncanny-automator' ),
				'input_type'      => 'email',
				'required'        => false,
				'supports_tokens' => true,
				'description'     => esc_html_x( "Leave empty to use the user's email address.", 'Eventin', 'uncanny-automator' ),
			),
			array(
				'option_code'     => self::PHONE,
				'label'           => esc_html_x( 'Attendee phone', 'Eventin', 'uncanny-automator' ),
				'input_type'      => 'text',
				'required'        => false,
				'supports_tokens' => true,
			),
			array(
				'option_code'           => self::STATUS,
				'label'                 => esc_html_x( 'Order status', 'Eventin', 'uncanny-automator' ),
				'input_type'            => 'select',
				'required'              => true,
				'options_show_id'       => false,
				'supports_custom_value' => false,
				'default_value'         => 'completed',
				'options'               => $this->item_helpers->get_order_status_options(),
			),
		);
	}

	/**
	 * Define output tokens.
	 *
	 * @return array
	 */
	public function define_tokens() {
		return array(
			'EVENTIN_CREATED_ATTENDEE_ID' => array(
				'name' => esc_html_x( 'Attendee ID', 'Eventin', 'uncanny-automator' ),
				'type' => 'int',
			),
			'EVENTIN_CREATED_ORDER_ID'    => array(
				'name' => esc_html_x( 'Order ID', 'Eventin', 'uncanny-automator' ),
				'type' => 'int',
			),
			'EVENTIN_UNIQUE_TICKET_ID'    => array(
				'name' => esc_html_x( 'Unique ticket ID', 'Eventin', 'uncanny-automator' ),
				'type' => 'text',
			),
			'EVENTIN_TICKET_NAME'         => array(
				'name' => esc_html_x( 'Ticket name', 'Eventin', 'uncanny-automator' ),
				'type' => 'text',
			),
			'EVENTIN_TICKET_PRICE'        => array(
				'name' => esc_html_x( 'Ticket price', 'Eventin', 'uncanny-automator' ),
				'type' => 'float',
			),
			'EVENTIN_EVENT_ID'            => array(
				'name' => esc_html_x( 'Event ID', 'Eventin', 'uncanny-automator' ),
				'type' => 'int',
			),
			'EVENTIN_EVENT_TITLE'         => array(
				'name' => esc_html_x( 'Event title', 'Eventin', 'uncanny-automator' ),
				'type' => 'text',
			),
			'EVENTIN_EVENT_URL'           => array(
				'name' => esc_html_x( 'Event URL', 'Eventin', 'uncanny-automator' ),
				'type' => 'url',
			),
		);
	}

	/**
	 * Process action.
	 *
	 * @param int   $user_id     The user ID.
	 * @param array $action_data The action data.
	 * @param int   $recipe_id   The recipe ID.
	 * @param array $args        The args.
	 * @param array $parsed      The parsed options.
	 *
	 * @return bool
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		if ( ! class_exists( '\Etn\Core\Attendee\Attendee_Model' ) || ! class_exists( '\Eventin\Order\OrderModel' ) ) {
			$this->add_log_error( 'Eventin is not active.' );
			return false;
		}

		$event_id = absint( $parsed[ $this->get_action_meta() ] ?? 0 );

		if ( 0 === $event_id || Eventin_Helpers::EVENT_POST_TYPE !== get_post_type( $event_id ) ) {
			$this->add_log_error( sprintf( 'Event not found: [%d].', $event_id ) );
			return false;
		}

		$ticket_slug = trim( (string) ( $parsed[ self::TICKET ] ?? '' ) );
		$variation   = $this->item_helpers->get_ticket_variation( $event_id, $ticket_slug );

		if ( empty( $variation ) ) {
			$this->add_log_error( sprintf( 'Ticket [%1$s] does not belong to event [%2$d].', $ticket_slug, $event_id ) );
			return false;
		}

		$attendee = $this->resolve_attendee_details( $user_id, $parsed );

		if ( '' === $attendee['email'] ) {
			$this->add_log_error( 'An attendee email is required.' );
			return false;
		}

		// Respect the event's stock exactly as Eventin's own create route does
		// (core/Attendee/Api/AttendeeController.php:443-454).
		$stock = $this->validate_stock( $event_id, $ticket_slug );

		if ( is_wp_error( $stock ) ) {
			$this->add_log_error( $stock->get_error_message() );
			return false;
		}

		$status   = $this->resolve_status( $parsed );
		$price    = (string) ( $variation['etn_ticket_price'] ?? '0' );
		$customer = $this->resolve_customer_id( $attendee );

		$order_id = $this->create_order( $event_id, $ticket_slug, $price, $status, $attendee, $customer );

		if ( 0 === $order_id ) {
			$this->add_log_error( 'The Eventin order could not be created.' );
			return false;
		}

		$unique_ticket_id = $this->generate_unique_ticket_id();

		$attendee_id = $this->create_attendee( $event_id, $order_id, $ticket_slug, $variation, $price, $status, $attendee, $unique_ticket_id );

		if ( 0 === $attendee_id ) {
			// The order is already in the database at this point and nothing is
			// tied to it any more, so it is removed before the failure is
			// reported — an abandoned order would otherwise show up in Eventin's
			// order list, and in its revenue totals, as a sale that never was.
			$this->delete_order( $order_id );
			$this->add_log_error( 'The Eventin attendee could not be created.' );
			return false;
		}

		$this->hydrate_tokens(
			array(
				'EVENTIN_CREATED_ATTENDEE_ID' => $attendee_id,
				'EVENTIN_CREATED_ORDER_ID'    => $order_id,
				'EVENTIN_UNIQUE_TICKET_ID'    => $unique_ticket_id,
				'EVENTIN_TICKET_NAME'         => (string) ( $variation['etn_ticket_name'] ?? $ticket_slug ),
				'EVENTIN_TICKET_PRICE'        => $price,
				'EVENTIN_EVENT_ID'            => $event_id,
				'EVENTIN_EVENT_TITLE'         => (string) get_the_title( $event_id ),
				'EVENTIN_EVENT_URL'           => (string) get_permalink( $event_id ),
			)
		);

		return true;
	}

	/**
	 * Name / email / phone for the attendee, falling back to the recipe user.
	 *
	 * @param int   $user_id The recipe user ID.
	 * @param array $parsed  The parsed options.
	 *
	 * @return array{name:string,email:string,phone:string,first:string,last:string}
	 */
	private function resolve_attendee_details( $user_id, $parsed ) {

		$name  = trim( (string) ( $parsed[ self::NAME ] ?? '' ) );
		$email = trim( (string) ( $parsed[ self::EMAIL ] ?? '' ) );
		$phone = trim( (string) ( $parsed[ self::PHONE ] ?? '' ) );

		$user = get_user_by( 'ID', absint( $user_id ) );

		if ( $user instanceof \WP_User ) {
			$name  = '' === $name ? (string) $user->display_name : $name;
			$email = '' === $email ? (string) $user->user_email : $email;
		}

		$email = is_email( $email ) ? $email : '';
		$parts = preg_split( '/\s+/', $name, 2 );

		return array(
			'name'  => $name,
			'email' => $email,
			'phone' => $phone,
			'first' => (string) ( $parts[0] ?? '' ),
			'last'  => (string) ( $parts[1] ?? '' ),
		);
	}

	/**
	 * The order status to write, constrained to the settable vocabulary.
	 *
	 * @param array $parsed The parsed options.
	 *
	 * @return string
	 */
	private function resolve_status( $parsed ) {

		$status = (string) ( $parsed[ self::STATUS ] ?? 'completed' );

		return in_array( $status, Eventin_Helpers::SETTABLE_ORDER_STATUSES, true ) ? $status : 'completed';
	}

	/**
	 * Run Eventin's own stock validation when it is available.
	 *
	 * @param int    $event_id    The event post ID.
	 * @param string $ticket_slug The ticket slug.
	 *
	 * @return true|\WP_Error
	 */
	private function validate_stock( $event_id, $ticket_slug ) {

		if ( ! function_exists( 'etn_validate_event_tickets' ) ) {
			return true;
		}

		$result = etn_validate_event_tickets(
			$event_id,
			array(
				array(
					'ticket_slug'     => $ticket_slug,
					'ticket_quantity' => 1,
				),
			)
		);

		return is_wp_error( $result ) ? $result : true;
	}

	/**
	 * Resolve the Eventin customer (a WP user with the `etn-customer` role).
	 *
	 * An existing account is reused — CustomerModel::create() goes straight to
	 * wp_insert_user() (core/Customer/CustomerModel.php:94), which fails on a
	 * duplicate email, so creating unconditionally would break for every user
	 * who already exists. A failure here is non-fatal: Eventin itself leaves
	 * `customer_id` empty for guest orders (core/Order/OrderController.php:2146).
	 *
	 * @param array $attendee The resolved attendee details.
	 *
	 * @return int
	 */
	private function resolve_customer_id( $attendee ) {

		$existing = $this->item_helpers->get_user_id_by_email( $attendee['email'] );

		if ( 0 !== $existing ) {
			return $existing;
		}

		if ( ! class_exists( '\Eventin\Customer\CustomerModel' ) ) {
			return 0;
		}

		$customer = CustomerModel::create(
			array(
				'first_name' => $attendee['first'],
				'last_name'  => $attendee['last'],
				'email'      => $attendee['email'],
			)
		);

		if ( is_wp_error( $customer ) || empty( $customer->id ) ) {
			return 0;
		}

		return absint( $customer->id );
	}

	/**
	 * Create the Eventin order.
	 *
	 * @param int    $event_id    The event post ID.
	 * @param string $ticket_slug The ticket slug.
	 * @param string $price       The ticket price.
	 * @param string $status      The order status.
	 * @param array  $attendee    The resolved attendee details.
	 * @param int    $customer_id The customer's WP user ID, or 0.
	 *
	 * @return int
	 */
	private function create_order( $event_id, $ticket_slug, $price, $status, $attendee, $customer_id ) {

		$order = new OrderModel();

		$created = $order->create(
			array(
				'post_title'     => sprintf( 'Order for %s', $attendee['email'] ),
				'post_status'    => 'publish',
				'event_id'       => $event_id,
				'status'         => $status,
				'payment_method' => 'automator',
				'date_time'      => current_time( 'mysql' ),
				'customer_fname' => $attendee['first'],
				'customer_lname' => $attendee['last'],
				'customer_email' => $attendee['email'],
				'customer_phone' => $attendee['phone'],
				'customer_id'    => $customer_id,
				'user_id'        => $customer_id,
				'total_price'    => $price,
				'tickets'        => array(
					array(
						'ticket_slug'     => $ticket_slug,
						'ticket_quantity' => 1,
					),
				),
			)
		);

		return false === $created ? 0 : absint( $order->id );
	}

	/**
	 * Create the Eventin attendee.
	 *
	 * @param int    $event_id         The event post ID.
	 * @param int    $order_id         The order post ID.
	 * @param string $ticket_slug      The ticket slug.
	 * @param array  $variation        The ticket variation row.
	 * @param string $price            The ticket price.
	 * @param string $status           The order status.
	 * @param array  $attendee         The resolved attendee details.
	 * @param string $unique_ticket_id The generated ticket ID.
	 *
	 * @return int
	 */
	private function create_attendee( $event_id, $order_id, $ticket_slug, $variation, $price, $status, $attendee, $unique_ticket_id ) {

		$model = new Attendee_Model();

		// Every key below except post_title / post_status is already declared in
		// Attendee_Model::$data, so update_meta() persists them all without a
		// set_fields() call — and skipping set_fields() is what stops the two
		// post_* keys being written as junk meta rows as well.
		$created = $model->create(
			array(
				'post_title'           => '' === $attendee['name'] ? $attendee['email'] : $attendee['name'],
				// Post_Model::create() defaults to 'draft'; Eventin's own REST
				// path forces 'publish' (…/AttendeeController.php:837).
				'post_status'          => 'publish',
				'etn_name'             => $attendee['name'],
				'etn_email'            => $attendee['email'],
				'etn_phone'            => $attendee['phone'],
				'etn_event_id'         => $event_id,
				'eventin_order_id'     => $order_id,
				'etn_unique_ticket_id' => $unique_ticket_id,
				'ticket_name'          => (string) ( $variation['etn_ticket_name'] ?? $ticket_slug ),
				'ticket_slug'          => $ticket_slug,
				'etn_ticket_price'     => $price,
				// Written explicitly: update_meta() skips keys absent from the
				// args, so the model default never persists on its own — and
				// without this row the check-in trigger cannot fire.
				Eventin_Helpers::ATTENDEE_TICKET_STATUS_META => 'unused',
				Eventin_Helpers::ATTENDEE_PAYMENT_STATUS_META => 'completed' === $status ? 'success' : 'failed',
				'etn_info_edit_token'  => $this->generate_edit_token(),
			)
		);

		return false === $created ? 0 : absint( $model->id );
	}

	/**
	 * Delete an order created moments earlier by create_order().
	 *
	 * Force-deletes rather than trashing: a trashed order is still an order
	 * everywhere Eventin counts one. The post type is re-read first so that an
	 * ID which is somehow not an order can never delete anything else.
	 *
	 * @param int $order_id The order post ID.
	 *
	 * @return void
	 */
	private function delete_order( $order_id ) {

		$order_id = absint( $order_id );

		if ( 0 === $order_id || Eventin_Helpers::ORDER_POST_TYPE !== get_post_type( $order_id ) ) {
			return;
		}

		wp_delete_post( $order_id, true );
	}

	/**
	 * Generate the unique ticket ID the QR scanner matches against.
	 *
	 * @return string
	 */
	private function generate_unique_ticket_id() {

		if ( class_exists( '\Eventin\Attendee\Attendee\TicketIdGenerator' ) ) {
			return (string) TicketIdGenerator::generate_ticket_id();
		}

		return (string) wp_generate_uuid4();
	}

	/**
	 * Generate the attendee's self-service edit token.
	 *
	 * @return string
	 */
	private function generate_edit_token() {

		if ( class_exists( '\Etn\Utils\Helper' ) && method_exists( '\Etn\Utils\Helper', 'generate_secure_token' ) ) {
			return (string) Helper::generate_secure_token();
		}

		return (string) wp_generate_password( 32, false );
	}
}
