<?php

namespace Uncanny_Automator;

/**
 * Ameliabooking Tokens.
 */
class AMELIABOOKING_TOKENS {


	/**
	 * __construct
	 *
	 * @return void
	 */
	public function __construct() {

		add_filter( 'automator_before_trigger_completed', array( $this, 'save_token_data' ), 20, 2 );

		add_filter( 'automator_maybe_trigger_ameliabooking_tokens', array( $this, 'register_tokens' ), 20, 2 );

		add_filter( 'automator_maybe_parse_token', array( $this, 'parse_tokens' ), 20, 6 );

	}

	/**
	 * Register the tokens.
	 *
	 * @param  mixed $tokens
	 * @param  mixed $args
	 * @return void
	 */
	public function register_tokens( $tokens = array(), $args = array() ) {
		if ( ! automator_do_identify_tokens() ) {
			return $tokens;
		}

		$trigger_integration = $args['integration'];

		$trigger_meta = $args['meta'];

		$tokens_collection = array_merge(
			$this->get_appointment_tokens(),
			$this->get_booking_tokens(),
			$this->get_customer_tokens(),
			$this->get_additional_tokens()
		);

		$arr_column_tokens_collection = array_column( $tokens_collection, 'name' );

		array_multisort( $arr_column_tokens_collection, SORT_ASC, $tokens_collection );

		$tokens = array();

		foreach ( $tokens_collection as $token ) {
			$tokens[] = array(
				'tokenId'         => str_replace( ' ', '_', $token['id'] ),
				'tokenName'       => $token['name'],
				'tokenType'       => 'text',
				'tokenIdentifier' => strtoupper( 'AMELIA_' . $token['id'] ),
			);
		}

		return $tokens;

	}

	/**
	 * Save the token data.
	 *
	 * @param  mixed $args
	 * @param  mixed $trigger
	 * @return void
	 */
	public function save_token_data( $args, $trigger ) {

		if ( ! isset( $args['trigger_args'] ) || ! isset( $args['entry_args']['code'] ) ) {
			return;
		}

		$triggers = array(
			'AMELIA_APPOINTMENT_BOOKED',
			'AMELIA_APPOINTMENT_BOOKED_SERVICE',
			'AMELIA_USER_APPOINTMENT_BOOKED',
			'AMELIA_USER_APPOINTMENT_BOOKED_SERVICE',
		);

		// Check if trigger code is for Amelia.
		if ( in_array( $args['entry_args']['code'], $triggers, true ) ) {

			$booking_data_arr = array_shift( $args['trigger_args'] );

			// Add the category name.
			$booking_data_arr['category']['name'] = $this->fetch_category_name( absint( $booking_data_arr['appointment']['serviceId'] ) );

			// Add the service name.
			$booking_data_arr['service']['name'] = $this->fetch_service_name( absint( $booking_data_arr['appointment']['serviceId'] ) );

			// Add the customer WordPress user id.
			$booking_data_arr['customer']['wpUserId'] = 0;

			if ( isset( $booking_data_arr['customer']['email'] ) && ! empty( $booking_data_arr['customer']['email'] ) ) {

				$wp_user = get_user_by( 'email', $booking_data_arr['customer']['email'] );

				if ( false !== $wp_user ) {
					$booking_data_arr['customer']['wpUserId'] = $wp_user->ID;
				}
			}

			$booking_data = wp_json_encode( $booking_data_arr );

			Automator()->db->token->save( 'AMELIA_BOOKING_DATA', $booking_data, $args['trigger_entry'] );

		}

	}

	/**
	 * Parsing the tokens.
	 *
	 * @param  mixed $value
	 * @param  mixed $pieces
	 * @param  mixed $recipe_id
	 * @param  mixed $trigger_data
	 * @param  mixed $user_id
	 * @param  mixed $replace_args
	 * @return void
	 */
	public function parse_tokens( $value, $pieces, $recipe_id, $trigger_data, $user_id, $replace_args ) {

		$trigger_code = '';

		if ( isset( $trigger_data[0]['meta']['code'] ) ) {
			$trigger_code = $trigger_data[0]['meta']['code'];
		}

		$triggers = array(
			'AMELIA_APPOINTMENT_BOOKED',
			'AMELIA_APPOINTMENT_BOOKED_SERVICE',
			'AMELIA_USER_APPOINTMENT_BOOKED',
			'AMELIA_USER_APPOINTMENT_BOOKED_SERVICE',
		);

		if ( empty( $trigger_code ) || ! in_array( $trigger_code, $triggers, true ) ) {
			return $value;
		}

		if ( ! is_array( $pieces ) || ! isset( $pieces[1] ) || ! isset( $pieces[2] ) ) {
			return $value;
		}

		// The $pieces[2] is the token id.
		$token_id_parts = explode( '_', $pieces[2] );

		// Get the meta from database record.
		$booking_data = json_decode( Automator()->db->token->get( 'AMELIA_BOOKING_DATA', $replace_args ), true );

		// Add a check to prevent notice.
		if ( isset( $token_id_parts[0] ) && isset( $token_id_parts[1] ) ) {
			// Example: $booking_data['appointment']['id].
			if ( isset( $booking_data[ $token_id_parts[0] ][ $token_id_parts[1] ] ) ) {
				$value = $booking_data[ $token_id_parts[0] ][ $token_id_parts[1] ];
			}
		}

		return $value;

	}

	/**
	 * Get appointment tokens.
	 *
	 * @return array[]
	 */
	public function get_appointment_tokens() {
		return array(
			array(
				'name' => esc_html__( 'Appointment ID', 'uncanny-automator' ),
				'id'   => 'appointment_id',
			),
			array(
				'name' => esc_html__( 'Appointment booking start', 'uncanny-automator' ),
				'id'   => 'appointment_bookingStart',
			),
			array(
				'name' => esc_html__( 'Appointment booking end', 'uncanny-automator' ),
				'id'   => 'appointment_bookingEnd',
			),
			array(
				'name' => esc_html__( 'Appointment provider ID', 'uncanny-automator' ),
				'id'   => 'appointment_providerId',
			),
			array(
				'name' => esc_html__( 'Appointment status', 'uncanny-automator' ),
				'id'   => 'appointment_status',
			),
		);
	}

	/**
	 * Get booking tokens.
	 *
	 * @return array[]
	 */
	public function get_booking_tokens() {
		return array(
			array(
				'name' => esc_html__( 'Booking ID', 'uncanny-automator' ),
				'id'   => 'booking_id',
			),

			array(
				'name' => esc_html__( 'Booking status', 'uncanny-automator' ),
				'id'   => 'booking_status',
			),
			array(
				'name' => esc_html__( 'Booking appointment ID', 'uncanny-automator' ),
				'id'   => 'booking_appointmentId',
			),
			array(
				'name' => esc_html__( 'Booking number of persons', 'uncanny-automator' ),
				'id'   => 'booking_persons',
			),
			array(
				'name' => esc_html__( 'Booking price', 'uncanny-automator' ),
				'id'   => 'booking_price',
			),
		);
	}

	/**
	 * Get customer related tokens.
	 *
	 * @return array[]
	 */
	public function get_customer_tokens() {

		// The id is mapped into booking data keys.
		return array(

			array(
				'name' => esc_html__( 'Customer first name', 'uncanny-automator' ),
				'id'   => 'customer_firstName',
			),
			array(
				'name' => esc_html__( 'Customer last name', 'uncanny-automator' ),
				'id'   => 'customer_lastName',
			),
			array(
				'name' => esc_html__( 'Customer ID', 'uncanny-automator' ),
				'id'   => 'customer_wpUserId',
			),
			array(
				'name' => esc_html__( 'Customer email', 'uncanny-automator' ),
				'id'   => 'customer_email',
			),
			array(
				'name' => esc_html__( 'Customer phone', 'uncanny-automator' ),
				'id'   => 'customer_phone',
			),
			array(
				'name' => esc_html__( 'Customer locale', 'uncanny-automator' ),
				'id'   => 'customer_locale',
			),
			array(
				'name' => esc_html__( 'Customer timezone', 'uncanny-automator' ),
				'id'   => 'customer_timeZone',
			),
		);
	}

	/**
	 * Additional tokens.
	 *
	 * @return array[]
	 */
	public function get_additional_tokens() {
		return array(
			array(
				'name' => esc_html__( 'Service name' ),
				'id'   => 'service_name',
			),
			array(
				'name' => esc_html__( 'Category name' ),
				'id'   => 'category_name',
			),
		);
	}

	/**
	 * Fetch the category name.
	 *
	 * @param $service_id
	 *
	 * @return string
	 */
	public function fetch_category_name( $service_id ) {

		global $wpdb;

		$category_name = '';

		$category = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT services.id as service_id, services.name as service_name,
				services.categoryId as category_id, categories.name as category_name
				FROM {$wpdb->prefix}amelia_services as services
				INNER JOIN {$wpdb->prefix}amelia_categories as categories
				ON services.categoryId = categories.id
				WHERE services.id = %d
				",
				$service_id
			)
		);

		if ( isset( $category->category_name ) ) {
			$category_name = $category->category_name;
		}

		return $category_name;

	}

	/**
	 * Fetch the service name.
	 *
	 * @param $service_id
	 *
	 * @return string
	 */
	public function fetch_service_name( $service_id ) {

		global $wpdb;

		$service_name = '';

		$service = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT name, id from {$wpdb->prefix}amelia_services WHERE id = %d",
				$service_id
			)
		);

		if ( isset( $service->name ) ) {
			$service_name = $service->name;
		}

		return $service_name;

	}

}
