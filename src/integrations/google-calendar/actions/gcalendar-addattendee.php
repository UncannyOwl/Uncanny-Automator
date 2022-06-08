<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
namespace Uncanny_Automator;

use Uncanny_Automator\Recipe\Actions;

/**
 * Class GCALENDAR_ADDATTENDEE
 *
 * @package Uncanny_Automator
 */
class GCALENDAR_ADDATTENDEE {

	use Actions;

	/**
	 * The prefix for the action fields.
	 *
	 * @var string
	 */
	const PREFIX = 'GCALENDAR_ADDATTENDEE';

	public function __construct() {

		$this->setup_action();

	}

	/**
	 * Setup Action.
	 *
	 * @return void.
	 */
	protected function setup_action() {

		$this->set_integration( 'GOOGLE_CALENDAR' );

		$this->set_action_code( self::PREFIX . '_CODE' );

		$this->set_action_meta( self::PREFIX . '_META' );

		$this->set_support_link( Automator()->get_author_support_link( $this->get_action_code(), 'knowledge-base/google-calendar/' ) );

		$this->set_is_pro( false );

		$this->set_requires_user( false );

		$this->set_sentence(
			sprintf(
			/* translators: Action sentence */
				esc_attr__( 'Add {{an attendee:%1$s}} to {{an event:%2$s}} in {{a Google Calendar:%3$s}}', 'uncanny-automator' ),
				$this->get_action_meta(),
				$this->get_formatted_code( 'event_id' ) . ':' . $this->get_action_meta(),
				$this->get_formatted_code( 'calendar_id' ) . ':' . $this->get_action_meta()
			)
		);

		/* translators: Action - WordPress */
		$this->set_readable_sentence( esc_attr__( 'Add {{an attendee}} to {{an event}} in {{a Google Calendar}}', 'uncanny-automator' ) );

		$this->set_options_callback( array( $this, 'load_options' ) );

		$this->register_action();

	}

	public function load_options() {

		$helper = Automator()->helpers->recipe->google_calendar->options;

		$options = array(
			'options_group' => array(
				$this->get_action_meta() => array(
					array(
						'option_code'           => $this->get_formatted_code( 'calendar_id' ),
						'label'                 => esc_attr__( 'Calendar', 'uncanny-automator' ),
						'input_type'            => 'select',
						'required'              => true,
						'supports_token'        => true,
						'supports_custom_value' => true,
						'options'               => $helper->get_calendar_options(),
						'is_ajax'               => true,
						'endpoint'              => 'automator_google_calendar_list_events',
						'fill_values_in'        => $this->get_formatted_code( 'event_id' ),
						'options_show_id'       => false,
					),
					array(
						'option_code'           => $this->get_formatted_code( 'event_id' ),
						'label'                 => esc_attr__( 'Event', 'uncanny-automator' ),
						'input_type'            => 'select',
						'required'              => true,
						'supports_token'        => true,
						'supports_custom_value' => true,
						'options_show_id'       => false,
					),
					array(
						'option_code'           => $this->get_action_meta(),
						'label'                 => esc_attr__( 'Attendee email', 'uncanny-automator' ),
						'input_type'            => 'email',
						'required'              => true,
						'supports_custom_value' => true,
						'supports_token'        => true,
					),
				),
			),
		);

		$options = Automator()->utilities->keep_order_of_options( $options );

		return $options;
	}

	/**
	 * Get formatted code.
	 *
	 * @param  string $option_code The option code.
	 *
	 * @return string The prefix underscore option code string.
	 */
	protected function get_formatted_code( $option_code = '' ) {

		return sprintf( '%1$s_%2$s', self::PREFIX, $option_code );

	}


	/**
	 * Process the action.
	 *
	 * @param int $user_id
	 * @param array $action_data
	 * @param int $recipe_id
	 * @param array $args
	 * @param $parsed
	 *
	 * @return void.
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		$calendar_id    = isset( $parsed[ $this->get_formatted_code( 'calendar_id' ) ] ) ? sanitize_text_field( $parsed[ $this->get_formatted_code( 'calendar_id' ) ] ) : null;
		$event_id       = isset( $parsed[ $this->get_formatted_code( 'event_id' ) ] ) ? sanitize_text_field( $parsed[ $this->get_formatted_code( 'event_id' ) ] ) : null;
		$attendee_email = isset( $parsed[ $this->get_action_meta() ] ) ? sanitize_text_field( $parsed[ $this->get_action_meta() ] ) : null;

		$helper = Automator()->helpers->recipe->google_calendar->options;

		try {

			$body = array(
				'access_token'   => $helper->get_client(),
				'action'         => 'add_attendee',
				'calendar_id'    => $calendar_id,
				'event_id'       => $event_id,
				'attendee_email' => $attendee_email,
			);

			$helper->api_call( $body, $action_data );

			Automator()->complete->action( $user_id, $action_data, $recipe_id );

		} catch ( \Exception $e ) {

			$action_data['complete_with_errors'] = true;

			Automator()->complete->action( $user_id, $action_data, $recipe_id, $e->getMessage() );

		}

		return true;

	}

}
