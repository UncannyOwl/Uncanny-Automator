<?php

namespace Uncanny_Automator;

/**
 * Class EM_REGISTER
 *
 * @package Uncanny_Automator
 */
class EM_BOOKING_APPROVED {

	/**
	 * Integration code
	 *
	 * @var string
	 */
	public static $integration = 'EVENTSMANAGER';

	private $trigger_code;
	private $trigger_meta;

	/**
	 * Set up Automator trigger constructor.
	 */
	public function __construct() {
		$this->trigger_code = 'SELECTEDEVENT';
		$this->trigger_meta = 'EMEVENTS';
		$this->define_trigger();
	}

	/**
	 * Define and register the trigger by pushing it into the Automator object
	 */
	public function define_trigger() {

		$trigger = array(
			'author'              => Automator()->get_author_name( $this->trigger_code ),
			'support_link'        => Automator()->get_author_support_link( $this->trigger_code, 'integration/events-manager/' ),
			'integration'         => self::$integration,
			'code'                => $this->trigger_code,
			/* translators: Logged-in trigger - The Events Manager */
			'sentence'            => sprintf( esc_attr__( "A user's booking for {{an event:%1\$s}} is approved", 'uncanny-automator' ), $this->trigger_meta ),
			/* translators: Logged-in trigger - The Events Manager */
			'select_option_name'  => esc_attr__( "A user's booking for {{an event}} is approved", 'uncanny-automator' ),
			'action'              => array( 'em_booking_save', 'em_booking_set_status' ),
			'priority'            => 99,
			'accepted_args'       => 2,
			'validation_function' => array( $this, 'user_registered_for_event' ),
			'options'             => array(
				Automator()->helpers->recipe->events_manager->options->all_em_events(
					null,
					$this->trigger_meta,
					array(
						'any_option' => true,
					)
				),
			),
		);

		Automator()->register->trigger( $trigger );
	}

	/**
	 * @param $em_status
	 * @param \EM_Booking $em_booking_obj
	 *
	 * @return mixed
	 */
	public function user_registered_for_event( $em_status, $em_booking_obj ) {

		if ( 'Approved' !== $em_booking_obj->get_status() ) {
			return $em_status;
		}

		$em_event_id        = $em_booking_obj->event_id;
		$user_id            = $em_booking_obj->person_id;
		$recipes            = Automator()->get->recipes_from_trigger_code( $this->trigger_code );
		$required_event     = Automator()->get->meta_from_recipes( $recipes, $this->trigger_meta );
		$matched_recipe_ids = array();

		foreach ( $recipes as $recipe_id => $recipe ) {
			foreach ( $recipe['triggers'] as $trigger ) {
				$trigger_id = $trigger['ID'];//return early for all products
				if ( isset( $required_event[ $recipe_id ][ $trigger_id ] ) ) {
					if ( absint( $required_event[ $recipe_id ][ $trigger_id ] ) === absint( $em_event_id ) || intval( '-1' ) === intval( $required_event[ $recipe_id ][ $trigger_id ] ) ) {
						$matched_recipe_ids[] = array(
							'recipe_id'  => $recipe_id,
							'trigger_id' => $trigger_id,
						);
					}
				}
			}
		}

		if ( ! empty( $matched_recipe_ids ) ) {
			foreach ( $matched_recipe_ids as $matched_recipe_id ) {
				$pass_args = array(
					'code'             => $this->trigger_code,
					'meta'             => $this->trigger_meta,
					'user_id'          => $user_id,
					'recipe_to_match'  => $matched_recipe_id['recipe_id'],
					'trigger_to_match' => $matched_recipe_id['trigger_id'],
					'ignore_post_id'   => true,
				);

				$args = Automator()->maybe_add_trigger_entry( $pass_args, false );

				if ( $args ) {
					foreach ( $args as $result ) {
						if ( true === $result['result'] ) {
							Em_Tokens::em_save_tokens( $this->trigger_meta, $result['args'], $em_booking_obj );
							Automator()->maybe_trigger_complete( $result['args'] );
						}
					}
				}
			}
		}

		return $em_status;
	}
}
