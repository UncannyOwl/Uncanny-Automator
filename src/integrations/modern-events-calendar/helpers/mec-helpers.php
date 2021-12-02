<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
namespace Uncanny_Automator;

/**
 * Class MEC_HELPERS
 *
 * @package Uncanny_Automator
 */
class MEC_HELPERS {


	public $options;


	public $pro;

	/**
	 * @var bool
	 */
	public $load_options;

	/**
	 * The event id.
	 *
	 * @var $event_id int The event id.
	 */
	protected $event_id = 0;

	/**
	 * The event post meta.
	 *
	 * @var array $event_meta The collection of specific event post meta.
	 */
	protected $event_meta = array();

	/**
	 * The id of the for MEC's `location` taxonomy.
	 */
	const TAXONOMY_LOCATION = 'mec_location';

	/**
	 * The id of the for MEC's `organizer` taxonomy.
	 */
	const TAXONOMY_ORGANIZER = 'mec_organizer';

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
			'label'                    => esc_html__( 'List of available events', 'uncanny-automator' ),
			'description'              => esc_html__( 'Select from the list of available events. The selected event must have a ticket.', 'uncanny-automator' ),
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
			'label'                    => esc_html__( 'Select a ticket', 'uncanny-automator' ),
			'description'              => esc_html__( 'Use the dropdown to select a ticket associated from the previously selected event', 'uncanny-automator' ),
			'supports_token'           => false,
			'supports_multiple_values' => false,
			'supports_custom_value'    => false,
		);

		$config = wp_parse_args( $args, $defaults );

		return $config;

	}

	/**
	 * This function must be called before calling any methods.
	 *
	 * @param $id integer The event id.
	 *
	 * @return void.
	 */
	public function setup( $id ) {
		$this->event_id   = $id;
		$this->event_meta = get_post_meta( $this->event_id );
	}


	/**
	 * Returns the date of the event.
	 *
	 * @return string The event date in `F j, o` format.
	 */
	public function get_event_date() {
		return sprintf( '%s', $this->get_date( 'start', 'F j, o' ) );
	}

	/**
	 * Returns the time of the event.
	 *
	 * @return string The event time in `g:i A` format.
	 */
	public function get_event_time() {
		return sprintf( '%s', $this->get_date( 'start', 'g:i A' ) );
	}

	/**
	 * Returns the location of the event.
	 *
	 * @return mixed Returns `null` when location term does not exists. Otherwise, returns the location in string.
	 */
	public function get_event_location() {

		$location      = '';
		$location_id   = end( $this->event_meta['mec_location_id'] );
		$location_term = get_term( $location_id, self::TAXONOMY_LOCATION );

		if ( is_wp_error( $location_term ) ) {
			return null;
		}

		$location = $location_term->name;

		return $location;

	}

	/**
	 * Returns the cost of the Event.
	 *
	 * @return string The cost of the event.
	 */
	public function get_event_cost() {

		$cost = '';
		$cost = end( $this->event_meta['mec_cost'] );

		return $cost;

	}

	/**
	 * Returns the tickets prices cost.
	 *
	 * @return string The comma separated ticket costs.
	 */
	public function get_event_tickets_cost() {

		if ( ! class_exists( '\MEC_feature_books' ) && ! method_exists( '\MEC_feature_books', 'getBook' ) ) {
			return '';
		}

		if ( ! class_exists( '\MEC_main' ) && ! method_exists( '\MEC_main', 'render_price' ) ) {
			return '';
		}

		$mec_book = new \MEC_feature_books();
		$mec_main = new \MEC_main();

		$book = $mec_book->getBook();

		$event_tickets   = get_post_meta( $this->event_id, 'mec_tickets', true );
		$costs           = array();
		$attendees_count = 1; // Mocks the number of users. We need 1.

		if ( ! empty( $event_tickets ) ) {

			foreach ( $event_tickets as $id => $ticket ) {

				$raw_tickets   = array( $id => 1 );
				$price_details = $book->get_price_details( $raw_tickets, $event_id, $event_tickets, array() );
				$costs[]       = $mec_main->render_price( $price_details['total'] );

			}
		}

		return implode( ', ', $costs );

	}

	/**
	 * Returns the event title.
	 *
	 * @return string The event title.
	 */
	public function get_event_title() {

		return get_the_title( $this->event_id );

	}

	/**
	 * Returns the Event Organizer.
	 *
	 * @return mixed Returns null when org term is empty. Otherwise, returns the organizer in string format.
	 */
	public function get_event_organizer() {

		$organizer      = '';
		$organizer_id   = end( $this->event_meta['mec_organizer_id'] );
		$organizer_term = get_term( $organizer_id, self::TAXONOMY_ORGANIZER );

		if ( is_wp_error( $organizer_term ) ) {
			return null;
		}

		$organizer = $organizer_term->name;

		return $organizer;

	}

	/**
	 * Returns the date of the event.
	 *
	 * @param  mixed $type Either 'start' or 'end'
	 * @param  mixed $format The format of date.
	 *
	 * @return string The formatted event date.
	 */
	private function get_date( $type = 'start', $format = 'F j, o g:i A' ) {

		// Get the date settings.
		$date_settings = unserialize( end( $this->event_meta['mec_date'] ) ); // phpcs:ignore

		// Get the start date.
		$date_start_date = $date_settings[ $type ]['date'];

		// Format the start time.
		$date_start = sprintf(
			'%s %02d:%02d %s',
			$date_start_date,
			$date_settings[ $type ]['hour'],
			$date_settings[ $type ]['minutes'],
			$date_settings[ $type ]['ampm']
		);

		// Create new DateTime object from formatted start time.
		$date_start_obj = new \DateTime( $date_start );

		// Format the date start time using DateTime format method.
		return $date_start_obj->format( $format );

	}

	/**
	 * Returns the featured image id of the Event.
	 *
	 * @return string The id of the featured image of the event.
	 */
	public function get_event_featured_image_id() {

		$thumbnail_id = get_post_thumbnail_id( $this->event_id );

		return $thumbnail_id;

	}

	/**
	 * Returns the featured image url of the Event.
	 *
	 * @return string The url of the featured image of the event.
	 */
	public function get_event_featured_image_url() {

		$thumbnail_url = get_the_post_thumbnail_url( $this->event_id );

		return $thumbnail_url;

	}

}
