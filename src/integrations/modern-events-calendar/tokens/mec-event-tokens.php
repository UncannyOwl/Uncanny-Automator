<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
namespace Uncanny_Automator;

/**
 * Mec_Event_Tokens
 *
 * @package Uncanny_Automator
 */
class Mec_Event_Tokens {

	/**
	 * Our integration.
	 *
	 * @var $integration string The integration.
	 */
	public static $integration = 'MEC';

	/**
	 * The token that we will use.
	 *
	 * @var string The token Identifier.
	 */
	private $token = 'MECTOKENS_';

	/**
	 * Our class constructor. Hooks `parse_tokens` method to `automator_maybe_parse_token` filter.
	 *
	 * @return void
	 */
	public function __construct() {

		$this->load_options = Automator()->helpers->recipe->maybe_load_trigger_options( __CLASS__ );

		add_filter( 'automator_maybe_parse_token', array( $this, 'parse_tokens' ), 36, 6 );

	}

	/**
	 * Process the tokens.
	 *
	 * @param  mixed $value The value accepted from `automator_maybe_parse_token`.
	 * @param  mixed $pieces The pieces accepted from `automator_maybe_parse_token`.
	 * @param  mixed $recipe_id The recipe id accepted from `automator_maybe_parse_token`.
	 * @param  mixed $trigger_data The trigger data accepted from `automator_maybe_parse_token`.
	 * @param  mixed $user_id The user id accepted from `automator_maybe_parse_token`.
	 * @param  mixed $replace_args The arguments accepted from `automator_maybe_parse_token`.
	 *
	 * @return mixed The token value to display.
	 */
	public function parse_tokens( $value, $pieces, $recipe_id, $trigger_data, $user_id, $replace_args ) {

		$to_match = array(
			$this->token . 'EVENT_TITLE',
			$this->token . 'EVENT_DATE',
			$this->token . 'EVENT_TIME',
			$this->token . 'EVENT_LOCATION',
			$this->token . 'EVENT_ORGANIZER',
			$this->token . 'EVENT_COST',
			$this->token . 'EVENT_THUMB_ID',
			$this->token . 'EVENT_THUMB_URL',
		);

		if ( $pieces ) {

			if ( array_intersect( $to_match, $pieces ) ) {

				$value = $this->replace_values( $value, $pieces, $recipe_id, $trigger_data, $user_id, $replace_args );

			}
		}

		return $value;

	}

	/**
	 * Replaces the token values.
	 *
	 * @return mixed The value.
	 */
	public function replace_values( $value, $pieces, $recipe_id, $trigger_data, $user_id, $replace_args ) {

		$trigger_meta = $pieces[1];
		$parse        = $pieces[2];

		$recipe_log_id = isset( $replace_args['recipe_log_id'] ) ? (int) $replace_args['recipe_log_id'] : Automator()->maybe_create_recipe_log_entry( $recipe_id, $user_id )['recipe_log_id'];

		if ( ! $trigger_data || ! $recipe_log_id ) {
			return $value;
		}

		foreach ( $trigger_data as $trigger ) {

			if ( ! isset( $trigger['meta'] ) ) {
				continue;
			}

			if ( ! key_exists( $trigger_meta, $trigger['meta'] ) && ( ! isset( $trigger['meta']['code'] ) && $trigger_meta !== $trigger['meta']['code'] ) ) {
				continue;
			}

			$trigger_id     = $trigger['ID'];
			$trigger_log_id = $replace_args['trigger_log_id'];

			$event_id = $this->get_event_id_from_trigger_log_meta(
				$user_id,
				'MEC_EVENT_ID',
				$replace_args['trigger_id'],
				$replace_args['trigger_log_id']
			);

			if ( empty( $event_id ) ) {
				return;
			}

			$helper = Automator()->helpers->recipe->modern_events_calendar->options;

			$the_event = $helper->setup( $event_id );

			$value = '';

			switch ( $parse ) {

				case $this->token . 'EVENT_TITLE':
					$value = $helper->get_event_title();
					break;
				case $this->token . 'EVENT_DATE':
					$value = $helper->get_event_date();
					break;
				case $this->token . 'EVENT_TIME':
					$value = $helper->get_event_time();
					break;
				case $this->token . 'EVENT_LOCATION':
					$value = $helper->get_event_location();
					break;
				case $this->token . 'EVENT_ORGANIZER':
					$value = $helper->get_event_organizer();
					break;
				case $this->token . 'EVENT_COST':
					$value = $helper->get_event_cost();
					break;
				case $this->token . 'EVENT_THUMB_ID':
					$value = $helper->get_event_featured_image_id();
					break;
				case $this->token . 'EVENT_THUMB_URL':
					$value = $helper->get_event_featured_image_url();
					break;
			}
		}

		return $value;

	}

	/**
	 * Get the event id from the trigger log table.
	 *
	 * @param  mixed $user_id The user id.
	 * @param  mixed $meta_key The meta key.
	 * @param  mixed $trigger_id The trigger id.
	 * @param  mixed $trigger_log_id The trigger log id.
	 *
	 * @return mixed The event ID | Empty String when not found.
	 */
	public function get_event_id_from_trigger_log_meta( $user_id, $meta_key, $trigger_id, $trigger_log_id ) {

		global $wpdb;

		if ( empty( $meta_key ) || empty( $trigger_id ) || empty( $trigger_log_id ) ) {
			return '';
		}

		$meta_value = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT meta_value 
				FROM {$wpdb->prefix}uap_trigger_log_meta 
				WHERE user_id = %d 
				AND meta_key = %s 
				AND automator_trigger_id = %d 
				AND automator_trigger_log_id = %d 
				ORDER BY ID DESC LIMIT 0,1",
				$user_id,
				$meta_key,
				$trigger_id,
				$trigger_log_id
			)
		);

		if ( ! empty( $meta_value ) ) {
			return maybe_unserialize( $meta_value );
		}

		return '';
	}

}
