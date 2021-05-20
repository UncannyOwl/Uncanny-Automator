<?php
namespace Uncanny_Automator;

/**
 * Class MEC_HELPERS
 *
 * @package Uncanny_Automator_Pro
 */
class MEC_HELPERS {

	/**
	 * Sets the option.
	 *
	 * @param MEC_HELPERS $options The instance of this class passed as an argument.
	 *
	 * @return void.
	 */
	public function setOptions( MEC_HELPERS $options ) {
		$this->options = $options;
	}

	/**
	 * Sets if Pro.
	 *
	 * @param MEC_HELPERS $pro The instance of this class passed as an argument.
	 *
	 * @return void.
	 */
	public function setPro( MEC_HELPERS $pro ) {
		$this->pro = $pro;
	}

	/**
	 * Our class constructor. Adds Couple of endpoints to the server.
	 *
	 * @return void.
	 */
	public function __construct() {
		add_action( 'wp_ajax_ua_mec_select_events', array( $this, 'select_events_endpoint' ) );
		add_action( 'wp_ajax_ua_mec_select_event_ticket', array( $this, 'select_event_tickets' ) );
	}

	/**
	 * This is a callback method to `wp_ajax_ua_mec_select_events` hook.
	 * The method renders a json array which is  immediately followed by die function from wp_send_json.
	 *
	 * @return void.
	 */
	public function select_event_tickets() {

		$tickets = array();

		$event_id = filter_input( INPUT_POST, 'value', FILTER_SANITIZE_NUMBER_INT );

		$event_tickets = get_post_meta( $event_id, 'mec_tickets', true );

		if ( ! empty( $event_tickets ) ) {
			foreach ( $event_tickets as $ticket_id => $event_ticket ) {
				$tickets[] = array(
					'value' => $ticket_id,
					'text'  => $event_ticket['name'],
				);
			}
		}

		wp_send_json( $tickets );

	}

	/**
	 * This is a callback method to `wp_ajax_ua_mec_select_events` hook.
	 * The method renders a json array which is  immediately followed by die function from wp_send_json.
	 *
	 * @return void.
	 */
	public function select_events_endpoint() {

		$events = new MEC_HELPERS();

		wp_send_json( $events->get_events( true ) );

	}

	/**
	 * Returns the list of events registered in MEC.
	 *
	 * @return array The list of events.
	 */
	public function get_events( $is_from_endpoint = false ) {



		$args = array(
			'post_type'      => 'mec-events',
			'posts_per_page' => 99,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'post_status'    => array( 'publish', 'private' ),
		);

		$events = array();

		$options = Automator()->helpers->recipe->options->wp_query( $args, false, null );

		$events = array();

		foreach ( $options as $value => $text ) {
			$events[] = array(
				'value' => $value,
				'text'  => $text,
			);
		}

		if ( $is_from_endpoint ) {
			return $events;
		}

		return $options;
	}

	/**
	 * Returns the configurations for our 'Events' dropdown.
	 *
	 * @return array The parameters of the 'Events' dropdown.
	 */
	public function get_events_select_field( $args = array() ) {

		$defaults = array(
			'input_type'               => 'select',
			'option_code'              => 'MEC_SELECTED_EVENT_ID',
			'options'                  => $this->get_events(),
			'required'                 => true,
			'label'                    => esc_html__( 'List of available Events', 'uncanny-automator' ),
			'description'              => esc_html__( 'Select from the list of available Events. The selected Event must have a Ticket.', 'uncanny-automator' ),
			'is_ajax'                  => true,
			'endpoint'                 => 'ua_mec_select_event_ticket',
			'fill_values_in'           => 'MEC_SELECTED_TICKET_ID',
			'supports_token'           => false,
			'supports_multiple_values' => false,
			'supports_custom_value'    => false,
		);

		$config = wp_parse_args( $args, $defaults );

		return $config;

	}

	/**
	 * Returns the configurations for our 'Tickets' dropdown.
	 *
	 * @return array The parameters of the 'Tickets' dropdown.
	 */
	public function get_tickets_select_field( $args = array() ) {

		$defaults = array(
			'input_type'               => 'select',
			'option_code'              => 'MEC_SELECTED_TICKET_ID',
			'options'                  => array(),
			'required'                 => true,
			'label'                    => esc_html__( 'Select a Ticket', 'uncanny-automator' ),
			'description'              => esc_html__( 'Use the dropdown to select a Ticket associated from the previously selected Event', 'uncanny-automator' ),
			'supports_token'           => false,
			'supports_multiple_values' => false,
			'supports_custom_value'    => false,
		);

		$config = wp_parse_args( $args, $defaults );

		return $config;

	}

}
