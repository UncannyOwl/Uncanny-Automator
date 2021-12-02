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
	public $load_options;

	/**
	 * Event_Tickets_Helpers constructor.
	 */
	public function __construct() {

		$this->load_options = Automator()->helpers->recipe->maybe_load_trigger_options( __CLASS__ );
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
	public function all_ec_events( $label = null, $option_code = 'ECEVENTS' ) {
		if ( ! $this->load_options ) {

			return Automator()->helpers->recipe->build_default_options_array( $label, $option_code );
		}

		if ( ! $label ) {
			$label = esc_attr__( 'Event', 'uncanny-automator' );
		}

		$args = array(
			'posts_per_page' => 999,
			'orderby'        => 'title',
			'order'          => 'DESC',
			'post_type'      => 'tribe_events',
			'post_status'    => 'publish',
		);

		$all_events = Automator()->helpers->recipe->options->wp_query( $args, true, __( 'Any event', 'uncanny-automator' ) );

		$option = array(
			'option_code'     => $option_code,
			'label'           => $label,
			'input_type'      => 'select',
			'required'        => true,
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
			'posts_per_page' => 999,
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
