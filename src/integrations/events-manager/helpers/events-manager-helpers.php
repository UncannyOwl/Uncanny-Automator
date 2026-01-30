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
	public $load_options = true;

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

	/**
	 * @param $label
	 * @param $option_code
	 * @param $args
	 *
	 * @return mixed|void
	 */
	public function all_em_events( $label = null, $option_code = 'EMALLEVENTS', $args = array() ) {
		if ( ! $label ) {
			$label = esc_attr_x( 'Event', 'Events Manager', 'uncanny-automator' );
		}

		$token           = key_exists( 'token', $args ) ? $args['token'] : false;
		$is_ajax         = key_exists( 'is_ajax', $args ) ? $args['is_ajax'] : false;
		$target_field    = key_exists( 'target_field', $args ) ? $args['target_field'] : '';
		$end_point       = key_exists( 'endpoint', $args ) ? $args['endpoint'] : '';
		$any_option      = key_exists( 'any_option', $args ) ? $args['any_option'] : false;
		$relevant_tokens = key_exists( 'relevant_tokens', $args ) ? $args['relevant_tokens'] : '';
		$options         = array();

		if ( isset( $any_option ) && true == $any_option ) {
			$options['-1'] = esc_attr_x( 'Any event', 'Events Manager', 'uncanny-automator' );
		}

		$default_tokens = array();

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
				// translators: 1: Event ID
				$title = sprintf( esc_attr_x( 'ID: %s (no title)', 'Events Manager', 'uncanny-automator' ), $event->event_id );
			}
			$options[ $event->event_id ] = $title;
		}

		if ( ! empty( $relevant_tokens ) ) {
			$default_tokens = array_merge( $default_tokens, $relevant_tokens );
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
			'relevant_tokens' => $default_tokens,
		);

		return apply_filters( 'uap_option_all_em_events', $option );
	}
}
