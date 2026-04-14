<?php

namespace Uncanny_Automator\Integrations\Linkedin;

use Exception;

/**
 * Class LINKEDIN_POST_SCHEDULE
 *
 * Schedules a LinkedIn post using WordPress cron (wp_schedule_single_event).
 * The schedule field accepts a number of days (e.g. "3"), a Unix timestamp,
 * or a date string (e.g. "2026-03-15 09:00"). When the scheduled event fires,
 * the post is published via the standard publish_post API call.
 *
 * The cron event arg is just the recipe_log_id, which enables clean
 * unscheduling via wp_next_scheduled() + wp_unschedule_event(). When the
 * event fires, post data is read from the existing action log meta tables.
 *
 * Hooks are registered in Linkedin_Integration::register_hooks().
 *
 * @package Uncanny_Automator
 *
 * @property Linkedin_App_Helpers $helpers
 * @property Linkedin_Api_Caller $api
 */
class LINKEDIN_POST_SCHEDULE extends \Uncanny_Automator\Recipe\App_Action {

	/**
	 * Setup the action.
	 *
	 * @return void
	 */
	public function setup_action() {

		$this->set_integration( 'LINKEDIN' );
		$this->set_action_code( 'LINKEDIN_POST_SCHEDULE' );
		$this->set_action_meta( 'LINKEDIN_POST_SCHEDULE_META' );
		$this->set_is_pro( false );
		$this->set_support_link( Automator()->get_author_support_link( $this->get_action_code(), 'knowledge-base/linkedin/' ) );
		$this->set_requires_user( false );
		$this->set_wpautop( false );
		$this->set_readable_sentence( esc_attr_x( 'Schedule a post on {{a LinkedIn page}}', 'LinkedIn', 'uncanny-automator' ) );
		$this->set_sentence(
			sprintf(
				// translators: %1$s is the LinkedIn page name
				esc_attr_x( 'Schedule a post on {{a LinkedIn page:%1$s}}', 'LinkedIn', 'uncanny-automator' ),
				$this->get_action_meta()
			)
		);
	}

	/**
	 * Define options.
	 *
	 * @return array
	 */
	public function options() {
		return array(
			$this->helpers->get_page_option_config( $this->get_action_meta() ),
			$this->helpers->get_message_option_config( 'BODY' ),
			array(
				'option_code'     => 'SCHEDULE',
				'label'           => esc_attr_x( 'When to publish', 'LinkedIn', 'uncanny-automator' ),
				'description'     => esc_attr_x( "Enter a number of days from now (e.g. 3), a specific date (e.g. 2026-03-15 09:00), or a Unix timestamp. Dates are interpreted in your site's timezone.", 'LinkedIn', 'uncanny-automator' ),
				'input_type'      => 'text',
				'supports_tokens' => true,
				'required'        => true,
			),
		);
	}

	/**
	 * Process the action.
	 *
	 * Resolves the schedule value and creates a WordPress cron event with
	 * recipe_log_id as the sole arg.
	 *
	 * @param int   $user_id     The user ID.
	 * @param array $action_data The action data.
	 * @param int   $recipe_id   The recipe ID.
	 * @param array $args        Additional args.
	 * @param array $parsed      Parsed token values.
	 *
	 * @return bool
	 * @throws \Exception If schedule value is invalid or scheduling fails.
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		$scheduled_at  = $this->resolve_scheduled_timestamp( $parsed['SCHEDULE'] ?? '' );
		$recipe_log_id = absint( $action_data['recipe_log_id'] ?? 0 );

		Linkedin_Scheduled_Posts_Manager::schedule( $scheduled_at, $recipe_log_id );

		// Signal the status filters to mark this run as "Completed, awaiting".
		$this->action_data['args']['scheduled'] = $scheduled_at;

		return true;
	}

	/**
	 * Resolve the user-provided schedule value into a Unix timestamp.
	 *
	 * Accepts three formats:
	 * - Number of days from now (e.g. "3", "14") — small integers (< 10 digits).
	 * - Unix timestamp (e.g. "1740000000") — 10+ digit integers.
	 * - Date string (e.g. "2026-03-15 09:00", "next Friday") — parsed with the site's timezone.
	 *
	 * @param string $value The raw schedule value.
	 *
	 * @return int Unix timestamp.
	 * @throws \Exception If the value cannot be resolved to a valid future timestamp.
	 */
	private function resolve_scheduled_timestamp( $value ) {

		$value = trim( $value );

		if ( '' === $value ) {
			throw new Exception(
				esc_html_x( 'The schedule field is required.', 'LinkedIn', 'uncanny-automator' )
			);
		}

		// Pure digits: either days or a Unix timestamp.
		if ( ctype_digit( $value ) ) {

			$number = (int) $value;

			// 10+ digits is a Unix timestamp (covers all dates from Sep 2001 onward).
			if ( strlen( (string) absint( $number ) ) >= 10 ) {
				return $this->validate_future_timestamp( $number );
			}

			// Small number = days from now.
			if ( $number < 1 ) {
				throw new Exception(
					esc_html_x( 'The number of days must be at least 1.', 'LinkedIn', 'uncanny-automator' )
				);
			}

			return time() + ( $number * DAY_IN_SECONDS );
		}

		// Date string — parse using the site's timezone.
		$date = date_create( $value, wp_timezone() );

		if ( false === $date ) {
			throw new Exception(
				esc_html_x( 'The schedule value could not be recognized as a valid date, number of days, or timestamp.', 'LinkedIn', 'uncanny-automator' )
			);
		}

		return $this->validate_future_timestamp( $date->getTimestamp() );
	}

	/**
	 * Validate that a timestamp is in the future.
	 *
	 * @param int $timestamp Unix timestamp.
	 *
	 * @return int The validated timestamp.
	 * @throws \Exception If the timestamp is in the past.
	 */
	private function validate_future_timestamp( $timestamp ) {

		if ( $timestamp <= time() ) {
			throw new Exception(
				esc_html_x( 'The scheduled date must be in the future.', 'LinkedIn', 'uncanny-automator' )
			);
		}

		return $timestamp;
	}
}
