<?php

namespace Uncanny_Automator;

use Uncanny_Automator_Pro\Events_Manager_Pro_Helpers;

/**
 * Class Events_Manager_Helpers
 *
 * @package Uncanny_Automator
 */
class Events_Manager_Helpers {
	/**
	 * @var Events_Manager_Helpers
	 */
	public $options;

	/**
	 * @var Events_Manager_Pro_Helpers
	 */
	public $pro;

	/**
	 * @var bool
	 */
	public $load_options;

	/**
	 * Event_Tickets_Helpers constructor.
	 */
	public function __construct() {
	}

	/**
	 * @param Events_Manager_Helpers $options
	 */
	public function setOptions( Events_Manager_Helpers $options ) {
		$this->options = $options;
	}

	/**
	 * @param Events_Manager_Pro_Helpers $pro
	 */
	public function setPro( Events_Manager_Pro_Helpers $pro ) {
		$this->pro = $pro;
	}

	public function all_em_events( $label = null, $option_code = 'EMALLEVENTS', $args = array() ) {
		if ( ! $label ) {
			$label = __( 'Event', 'uncanny-automator' );
		}

		$token        = key_exists( 'token', $args ) ? $args['token'] : false;
		$is_ajax      = key_exists( 'is_ajax', $args ) ? $args['is_ajax'] : false;
		$target_field = key_exists( 'target_field', $args ) ? $args['target_field'] : '';
		$end_point    = key_exists( 'endpoint', $args ) ? $args['endpoint'] : '';
		$any_option   = key_exists( 'any_option', $args ) ? $args['any_option'] : false;
		$options      = array();

		if ( isset( $any_option ) && $any_option == true ) {
			$options['-1'] = __( 'Any event', 'uncanny-automator' );
		}

		global $wpdb;

		$all_events = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT event_id,event_name FROM {$wpdb->prefix}em_events WHERE event_status = %d ORDER BY event_name",
				1
			)
		);

		foreach ( $all_events as $event ) {
			$title = $event->event_name;
			if ( empty( $title ) ) {
				$title = sprintf( __( 'ID: %s (no title)', 'uncanny-automator' ), $event->event_id );
			}
			$options[ $event->event_id ] = $title;
		}

		$option = array(
			'option_code'     => $option_code,
			'label'           => $label,
			'input_type'      => 'select',
			'required'        => true,
			'supports_tokens' => $token,
			'is_ajax'         => $is_ajax,
			'fill_values_in'  => $target_field,
			'endpoint'        => $end_point,
			'options'         => $options,
			'relevant_tokens' => array(
				$option_code                => __( 'Event title', 'uncanny-automator' ),
				$option_code . '_ID'        => __( 'Event ID', 'uncanny-automator' ),
				$option_code . '_URL'       => __( 'Event URL', 'uncanny-automator' ),
				$option_code . '_THUMB_ID'  => __( 'Event featured image ID', 'uncanny-automator' ),
				$option_code . '_THUMB_URL' => __( 'Event featured image URL', 'uncanny-automator' ),
			),
		);

		return apply_filters( 'uap_option_all_em_events', $option );
	}
}
