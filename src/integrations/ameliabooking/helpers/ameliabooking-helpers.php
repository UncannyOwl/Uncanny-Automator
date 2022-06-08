<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName

namespace Uncanny_Automator;

/**
 * Class Ameliabooking_Helpers
 *
 * @package Uncanny_Automator
 */
class Ameliabooking_Helpers {


	/**
	 * The options.
	 *
	 * @var mixed The options.
	 */
	public $options;

	/**
	 * The settings tab.
	 *
	 * @var string The settings tab.
	 */
	public $setting_tab;

	/**
	 * The trigger options.
	 *
	 * @var mixed The trigger options.
	 */
	public $load_options;


	public function __construct() {

		global $wpdb;

		$this->load_options = Automator()->helpers->recipe->maybe_load_trigger_options( __CLASS__ );

		add_action( 'wp_ajax_ameliabooking_service_category_endpoint', array( $this, 'ameliabooking_service_category_endpoint' ) );

	}

	/**
	 * Set the options.
	 *
	 * @param Ameliabooking_Helpers $options
	 */
	public function setOptions( Ameliabooking_Helpers $options ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		$this->options = $options;
	}

	public function get_option_fields( $trigger_code = '', $trigger_meta = '' ) {
		return array(
			$trigger_meta => array(
				array(
					'input_type'      => 'select',
					'option_code'     => $trigger_code . '_SERVICES',
					'required'        => true,
					'label'           => esc_html__( 'Category', 'uncanny-automator' ),
					'options'         => $this->get_services_categories(),
					'is_ajax'         => true,
					'endpoint'        => 'ameliabooking_service_category_endpoint',
					'fill_values_in'  => $trigger_meta,
					'relevant_tokens' => array(),
				),
				array(
					'input_type'      => 'select',
					'option_code'     => $trigger_meta,
					'required'        => true,
					'label'           => esc_html__( 'Service', 'uncanny-automator' ),
					'relevant_tokens' => array(),
				),
			),
		);
	}

	/**
	 * Callback method to wp_ajax_ameliabooking_service_category_endpoint.
	 *
	 * @return void
	 */
	public function ameliabooking_service_category_endpoint() {

		Automator()->utilities->ajax_auth_check();

		$category_id = absint( automator_filter_input( 'value', INPUT_POST ) );

		$items = array(
			array(
				'text'  => esc_html__( 'Any service', 'uncanny-automator' ),
				'value' => '-1',
			),
		);

		$services = $this->get_services_by_category( $category_id );

		if ( ! empty( $services ) ) {
			foreach ( $services as $service ) {
				$items[] = array(
					'text'  => $service->name,
					'value' => $service->id,
				);
			}
		}

		wp_send_json( $items );

	}

	/**
	 * Get amelia services categories.
	 *
	 * @return array The amelia services categories.
	 */
	public function get_services_categories() {

		global $wpdb;

		$items = array();

		$categories = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, name FROM {$wpdb->prefix}amelia_categories WHERE status = %s ORDER BY name ASC LIMIT 100",
				'visible'
			),
			OBJECT
		);

		if ( ! empty( $categories ) ) {
			foreach ( $categories as $category ) {
				$items[ $category->id ] = $category->name;
			}
		}

		return $items;

	}

	/**
	 * Get all amelia services by category.
	 *
	 * @return array The wpdb query result.
	 */
	public function get_services_by_category( $category_id = 0 ) {

		global $wpdb;

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, name FROM {$wpdb->prefix}amelia_services 
				WHERE categoryId = %d AND status = %s 
				ORDER BY name ASC LIMIT 100",
				$category_id,
				'visible'
			),
			OBJECT
		);
	}

	/**
	 * Get all amelia services.
	 *
	 * @return array The amelia services returned by wpdb get_results.
	 */
	public function get_services_all() {

		global $wpdb;

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, name FROM {$wpdb->prefix}amelia_services WHERE status = %s ORDER BY name ASC LIMIT 999",
				'visible'
			),
			OBJECT
		);

	}

	public function get_events_dropdown() {

		$events = $this->get_events();

		$options = array(
			'-1' => esc_attr__( 'Any event', 'uncanny-automator' ),
		);

		if ( ! empty( $events ) ) {
			foreach ( $events as $event ) {
				if ( ! empty( $event->name ) && ! empty( $event->id ) ) {
					$options[ $event->id ] = $event->name;
				}
			}
		}

		return $options;

	}

	public function get_events() {

		global $wpdb;

		$options = array();

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, name from {$wpdb->prefix}amelia_events WHERE status = %s ORDER BY name ASC LIMIT 999",
				'approved'
			),
			OBJECT
		);

		return $results;

	}

	/**
	 * Validate trigger.
	 *
	 * @return boolean False if args is empty. Otherwise, True.
	 */
	public function validate_trigger( $args = array() ) {

		// Bailout if args is empty.
		if ( empty( $args ) ) {
			return false;
		}

		$booking = array_shift( $args[0] );

		if ( empty( $booking['type'] ) ) {
			return false;
		}

		// Only run for appointments. Dont run for events.
		if ( 'appointment' === $booking['type'] ) {
			return true;
		}

		return false;

	}

	/**
	 * Method get_event_date.
	 *
	 * @param array $reservation The reservation array from Amelia hook.
	 *
	 * @return string The event date.
	 */
	public function get_event_date( $reservation = array() ) {

		$reservation_periods = isset( $reservation['event']['periods'][0] ) ? $reservation['event']['periods'][0] : '';

		$period_end = isset( $reservation_periods['periodEnd'] ) ? $reservation_periods['periodEnd'] : '';

		$period_start = isset( $reservation_periods['periodStart'] ) ? $reservation_periods['periodStart'] : '';

		return $period_start . ' - ' . $period_end;

	}

	/**
	 * Method get_event_organizer.
	 *
	 * @param  int $organizer_id The organizer|staff|employee id.
	 *
	 * @return string The organizer.
	 */
	public function get_event_organizer( $organizer_id = 0 ) {

		$organizer = $this->get_organizer( $organizer_id );

		if ( ! empty( $organizer ) ) {

			return implode(
				' ',
				array(
					$organizer->firstName, // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
					$organizer->lastName, // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
				)
			);

		}

	}

	/**
	 * Method get_event_tags.
	 *
	 * @param  array $reservation The reservation array from Amelia Hook.
	 *
	 * @return string The tag [name] from tags array.
	 */
	public function get_event_tags( $reservation = array() ) {

		$tag_string = '';

		if ( isset( $reservation['event']['tags'] ) ) {

			$tags = array_shift( $reservation['event']['tags'] );

			$tag_string = isset( $tags['name'] ) ? $tags['name'] : '';

		}

		return $tag_string;

	}

	/**
	 * Method get_event_staff.
	 *
	 * @param  array $reservation The reservation array from Amelia Hook.
	 *
	 * @return string The provider name.
	 */
	public function get_event_staff( $reservation = array() ) {

		$providers = array();

		if ( ! empty( $reservation['event']['providers'] ) ) {

			foreach ( $reservation['event']['providers'] as $provider ) {

				$providers[] = implode(
					' ',
					array(
						$provider['firstName'],
						$provider['lastName'],
					)
				);

			}

			return implode( ', ', $providers );
		}

		return '';
	}

	/**
	 * Method get_organizer.
	 *
	 * @param  mixed $organizer_id
	 *
	 * @return object WPDB get row result.
	 */
	public function get_organizer( $organizer_id = 0 ) {

		global $wpdb;

		$results = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}amelia_users WHERE id = %d",
				$organizer_id
			)
		);

		return $results;
	}

}
