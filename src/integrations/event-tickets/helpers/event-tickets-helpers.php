<?php


namespace Uncanny_Automator;

/**
 * Class Event_Tickets_Helpers
 * @package Uncanny_Automator
 */
class Event_Tickets_Helpers {
	/**
	 * @var Event_Tickets_Helpers
	 */
	public $options;

	/**
	 * @var \Uncanny_Automator_Pro\Event_Tickets_Pro_Helpers
	 */
	public $pro;

	/**
	 * @param Event_Tickets_Helpers $options
	 */
	public function setOptions( Event_Tickets_Helpers $options ) {
		$this->options = $options;
	}

	/**
	 * @param \Uncanny_Automator_Pro\Event_Tickets_Pro_Helpers $pro
	 */
	public function setPro( \Uncanny_Automator_Pro\Event_Tickets_Pro_Helpers $pro ) {
		$this->pro = $pro;
	}

	/**
	 * @param string $label
	 * @param string $option_code
	 *
	 * @return mixed
	 */
	public function all_ec_events( $label = null, $option_code = 'ECEVENTS' ) {

		if ( ! $label ) {
			$label = __( 'Event', 'uncanny-automator' );
		}

		$args = [
			'posts_per_page' => 999,
			'orderby'        => 'title',
			'order'          => 'DESC',
			'post_type'      => 'tribe_events',
			'post_status'    => 'publish',
		];

		global $uncanny_automator;
		$all_events = $uncanny_automator->helpers->recipe->options->wp_query( $args );

		$option = [
			'option_code'     => $option_code,
			'label'           => $label,
			'input_type'      => 'select',
			'required'        => true,
			//'default_value'      => 'Any post',
			'options'         => $all_events,
			'relevant_tokens' => [
				$option_code          => __( 'Event title', 'uncanny-automator' ),
				$option_code . '_ID'  => __( 'Event ID', 'uncanny-automator' ),
				$option_code . '_URL' => __( 'Event URL', 'uncanny-automator' ),
			],
		];

		return apply_filters( 'uap_option_all_ec_events', $option );
	}

	/**
	 * @param string $label
	 * @param string $option_code
	 *
	 * @return mixed
	 */
	public function all_ec_rsvp_events( $label = null, $option_code = 'ECEVENTS' ) {

		if ( ! $label ) {
			$label = __( 'Event', 'uncanny-automator' );
		}

		$args    = [
			'posts_per_page' => 999,
			'orderby'        => 'title',
			'order'          => 'DESC',
			'post_type'      => 'tribe_events',
			'post_status'    => 'publish',
		];
		$options = [];
		global $uncanny_automator;
		if ( $uncanny_automator->helpers->recipe->load_helpers ) {
			$posts          = get_posts( $args );
			$ticket_handler = new \Tribe__Tickets__Tickets_Handler();
			foreach ( $posts as $post ) {
				$title = $post->post_title;

				if ( empty( $title ) ) {
					$title = sprintf( __( 'ID: %1$s (no title)', 'uncanny-automator' ), $post->ID );
				}

				$rsvp_ticket = $ticket_handler->get_event_rsvp_tickets( $post );

				if ( ! empty ( $rsvp_ticket ) ) {
					$options[ $post->ID ] = $title;
				}
			}
		}
		$option = [
			'option_code'     => $option_code,
			'label'           => $label,
			'input_type'      => 'select',
			'required'        => true,
			//'default_value'      => 'Any post',
			'options'         => $options,
			'relevant_tokens' => [
				$option_code          => __( 'Event title', 'uncanny-automator' ),
				$option_code . '_ID'  => __( 'Event ID', 'uncanny-automator' ),
				$option_code . '_URL' => __( 'Event URL', 'uncanny-automator' ),
			],
		];

		return apply_filters( 'uap_option_all_ec_events', $option );
	}
}