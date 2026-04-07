<?php

namespace Uncanny_Automator\Integrations\Linkedin;

use Uncanny_Automator\Automator;
use Exception;

/**
 * Class Linkedin_Scheduled_Posts_Manager
 *
 * Manages the lifecycle of scheduled LinkedIn posts: publishing when the
 * cron event fires, and unscheduling when recipe logs are deleted.
 *
 * Post data is read from the existing action log meta tables — no extra
 * storage is needed. The cron event arg is just the recipe_log_id, which
 * makes wp_next_scheduled() a deterministic reverse lookup.
 *
 * @package Uncanny_Automator
 */
class Linkedin_Scheduled_Posts_Manager {

	/**
	 * The WordPress cron hook name for scheduled posts.
	 *
	 * @var string
	 */
	const SCHEDULE_HOOK = 'automator_linkedin_scheduled_post';

	/**
	 * The helpers instance.
	 *
	 * @var Linkedin_App_Helpers
	 */
	private $helpers;

	/**
	 * The API caller instance.
	 *
	 * @var Linkedin_Api_Caller
	 */
	private $api;

	/**
	 * Constructor.
	 *
	 * @param Linkedin_App_Helpers $helpers The helpers instance.
	 * @param Linkedin_Api_Caller  $api     The API caller instance.
	 */
	public function __construct( Linkedin_App_Helpers $helpers, Linkedin_Api_Caller $api ) {
		$this->helpers = $helpers;
		$this->api     = $api;
	}

	////////////////////////////////////////////////////////////
	// Schedule
	////////////////////////////////////////////////////////////

	/**
	 * Schedule a LinkedIn post for future publishing.
	 *
	 * @param int $timestamp      Unix timestamp for when to publish.
	 * @param int $recipe_log_id  The recipe log ID identifying the scheduled post.
	 *
	 * @return void
	 * @throws Exception If scheduling fails.
	 */
	public static function schedule( $timestamp, $recipe_log_id ) {

		$scheduled = wp_schedule_single_event(
			$timestamp,
			self::SCHEDULE_HOOK,
			array( absint( $recipe_log_id ) )
		);

		if ( false === $scheduled ) {
			throw new Exception(
				esc_html_x( 'Failed to schedule the LinkedIn post. A duplicate event may already be scheduled.', 'LinkedIn', 'uncanny-automator' )
			);
		}
	}

	/**
	 * Register all scheduled-post hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		// Cron callback.
		add_action( self::SCHEDULE_HOOK, array( $this, 'publish' ) );

		// Unschedule on log deletion.
		add_action( 'automator_before_recipe_log_deleted', array( $this, 'unschedule_for_log' ), 10, 2 );
		add_action( 'automator_before_recipe_logs_cleared', array( $this, 'unschedule_for_recipe' ), 10, 1 );

		// Set action and recipe status to "Completed, awaiting" while the post is scheduled.
		add_filter( 'automator_get_action_completed_status', array( $this, 'filter_action_status' ), 10, 7 );
		add_filter( 'automator_get_action_error_message', array( $this, 'filter_action_message' ), 10, 7 );
		add_filter( 'automator_recipe_process_complete_status', array( $this, 'filter_recipe_status' ), 10, 2 );
	}

	/**
	 * Set the action status to COMPLETED_AWAITING for scheduled posts.
	 *
	 * Only applies when the action explicitly set the 'scheduled' flag,
	 * ensuring errors still get recorded as COMPLETED_WITH_ERRORS.
	 *
	 * @param int    $completed      The current completed status.
	 * @param int    $user_id        The user ID.
	 * @param array  $action_data    The action data.
	 * @param int    $recipe_id      The recipe ID.
	 * @param string $error_message  The error message.
	 * @param int    $recipe_log_id  The recipe log ID.
	 * @param array  $args           The recipe process args.
	 *
	 * @return int
	 */
	public function filter_action_status( $completed, $user_id, $action_data, $recipe_id, $error_message, $recipe_log_id, $args ) {

		if ( ! empty( $args['scheduled'] ) ) {
			return \Uncanny_Automator\Automator_Status::COMPLETED_AWAITING;
		}

		return $completed;
	}

	/**
	 * Set a descriptive message for the action log while the post is scheduled.
	 *
	 * @param string $message        The current error/status message.
	 * @param int    $user_id        The user ID.
	 * @param array  $action_data    The action data.
	 * @param int    $recipe_id      The recipe ID.
	 * @param string $error_message  The error message.
	 * @param int    $recipe_log_id  The recipe log ID.
	 * @param array  $args           The recipe process args.
	 *
	 * @return string
	 */
	public function filter_action_message( $message, $user_id, $action_data, $recipe_id, $error_message, $recipe_log_id, $args ) {

		if ( ! empty( $args['scheduled'] ) ) {
			$date = wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $args['scheduled'] );

			return sprintf(
				// translators: %s is the scheduled date and time.
				esc_html_x( 'Post scheduled for %s.', 'LinkedIn', 'uncanny-automator' ),
				$date
			);
		}

		return $message;
	}

	/**
	 * Set the recipe status to COMPLETED_AWAITING when a scheduled post action ran.
	 *
	 * @param int   $completed The current recipe completed status.
	 * @param array $args      The recipe completion args.
	 *
	 * @return int
	 */
	public function filter_recipe_status( $completed, $args ) {

		if ( ! empty( $args['scheduled'] ) ) {
			return \Uncanny_Automator\Automator_Status::COMPLETED_AWAITING;
		}

		return $completed;
	}

	////////////////////////////////////////////////////////////
	// Publish
	////////////////////////////////////////////////////////////

	/**
	 * Publish a scheduled LinkedIn post.
	 *
	 * Cron callback. Retrieves post data from the action log meta
	 * using the recipe_log_id, then publishes via the standard API.
	 *
	 * @param int $recipe_log_id The recipe log ID identifying the scheduled post.
	 *
	 * @return void
	 */
	public function publish( $recipe_log_id ) {

		$recipe_log_id = absint( $recipe_log_id );
		$data          = $this->get_post_data( $recipe_log_id );

		$action_id = is_array( $data ) ? ( $data['action_id'] ?? 0 ) : 0;

		if ( empty( $data['content'] ) || empty( $data['urn'] ) ) {
			$this->fail_run(
				$recipe_log_id,
				$action_id,
				esc_html_x( 'The scheduled post data could not be found. The log may have been deleted.', 'LinkedIn', 'uncanny-automator' )
			);
			return;
		}

		$completed     = \Uncanny_Automator\Automator_Status::COMPLETED;
		$error_message = '';
		$content       = $this->helpers->format_post_content( $data['content'] );

		// Reconstruct minimal action_data so the API call is logged and can be resent from the logs UI.
		$action_data = array(
			'recipe_log_id' => $recipe_log_id,
			'action_log_id' => $data['action_log_id'],
		);

		try {
			$this->api->publish_post( $content, $data['urn'], $action_data );
		} catch ( Exception $e ) {
			$completed     = \Uncanny_Automator\Automator_Status::COMPLETED_WITH_ERRORS;
			$error_message = $e->getMessage();
			automator_log( $error_message, 'LINKEDIN_POST_SCHEDULE', true, 'linkedin' );
		}

		// Mark the action and recipe logs as complete.
		Automator()->db->action->mark_complete( $action_id, $recipe_log_id, $completed, $error_message );
		Automator()->db->recipe->mark_complete( $recipe_log_id, $completed );
	}

	/**
	 * Mark a scheduled post run as failed.
	 *
	 * @param int    $recipe_log_id The recipe log ID.
	 * @param int    $action_id     The action post ID (0 if unknown).
	 * @param string $error_message The error message.
	 *
	 * @return void
	 */
	private function fail_run( $recipe_log_id, $action_id, $error_message ) {

		$status = \Uncanny_Automator\Automator_Status::COMPLETED_WITH_ERRORS;

		if ( ! empty( $action_id ) ) {
			Automator()->db->action->mark_complete( $action_id, $recipe_log_id, $status, $error_message );
		}

		Automator()->db->recipe->mark_complete( $recipe_log_id, $status );

		automator_log( $error_message, 'LINKEDIN_POST_SCHEDULE', true, 'linkedin' );
	}

	/**
	 * Retrieve post data for a scheduled LinkedIn post from the action log.
	 *
	 * Looks up the action log entry for the LINKEDIN_POST_SCHEDULE action
	 * within the given recipe run, then reads the parsed field values.
	 *
	 * @param int $recipe_log_id The recipe log ID.
	 *
	 * @return array{content: string, urn: string, action_id: int, action_log_id: int}|null Post data or null if not found.
	 */
	private function get_post_data( $recipe_log_id ) {

		global $wpdb;

		// Find the action log entry for the schedule action in this recipe run.
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT al.ID, al.automator_action_id
				FROM {$wpdb->prefix}uap_action_log al
				INNER JOIN {$wpdb->postmeta} pm
					ON pm.post_id = al.automator_action_id
					AND pm.meta_key = 'code'
					AND pm.meta_value = 'LINKEDIN_POST_SCHEDULE'
				WHERE al.automator_recipe_log_id = %d
				LIMIT 1",
				$recipe_log_id
			)
		);

		if ( empty( $row ) ) {
			return null;
		}

		$fields_json = Automator()->db->action->get_meta( $row->ID, 'action_fields' );
		$fields      = json_decode( $fields_json, true );

		if ( empty( $fields ) ) {
			return null;
		}

		// Index the fields array by field_code for easy lookup.
		$keyed = array_column( $fields, null, 'field_code' );

		return array(
			'content'       => $keyed['BODY']['value']['parsed'] ?? '',
			'urn'           => $keyed['LINKEDIN_POST_SCHEDULE_META']['value']['parsed'] ?? '',
			'action_id'     => absint( $row->automator_action_id ),
			'action_log_id' => absint( $row->ID ),
		);
	}

	////////////////////////////////////////////////////////////
	// Unschedule
	////////////////////////////////////////////////////////////

	/**
	 * Unschedule a pending LinkedIn post when a single recipe run log is deleted.
	 *
	 * @param int $recipe_id     The recipe ID.
	 * @param int $recipe_log_id The recipe log ID being deleted.
	 *
	 * @return void
	 */
	public function unschedule_for_log( $recipe_id, $recipe_log_id ) {

		$hook      = self::SCHEDULE_HOOK;
		$args      = array( absint( $recipe_log_id ) );
		$timestamp = wp_next_scheduled( $hook, $args );

		if ( false !== $timestamp ) {
			wp_unschedule_event( $timestamp, $hook, $args );
		}
	}

	/**
	 * Unschedule all pending LinkedIn posts when all logs for a recipe are cleared.
	 *
	 * Queries the action log for all recipe runs that include a LINKEDIN_POST_SCHEDULE
	 * action, then unschedules each pending cron event.
	 *
	 * @param int $recipe_id The recipe ID whose logs are being cleared.
	 *
	 * @return void
	 */
	public function unschedule_for_recipe( $recipe_id ) {

		global $wpdb;

		$log_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT al.automator_recipe_log_id
				FROM {$wpdb->prefix}uap_action_log al
				INNER JOIN {$wpdb->postmeta} pm
					ON pm.post_id = al.automator_action_id
					AND pm.meta_key = 'code'
					AND pm.meta_value = 'LINKEDIN_POST_SCHEDULE'
				WHERE al.automator_recipe_id = %d",
				$recipe_id
			)
		);

		$hook = self::SCHEDULE_HOOK;

		foreach ( $log_ids as $log_id ) {
			$args      = array( absint( $log_id ) );
			$timestamp = wp_next_scheduled( $hook, $args );

			if ( false !== $timestamp ) {
				wp_unschedule_event( $timestamp, $hook, $args );
			}
		}
	}
}
