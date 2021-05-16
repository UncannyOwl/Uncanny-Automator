<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
namespace Uncanny_Automator;

/**
 * This class defines all the helper methods were using for token generation.
 *
 * @package Uncanny_Automator
 */
class MEC_EVENT_HELPERS {


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

}
