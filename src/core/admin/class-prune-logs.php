<?php

namespace Uncanny_Automator;

use DateTime;
use DateTimeZone;
use Exception;

/**
 * Class Prune_Logs
 *
 * @package Uncanny_Automator
 */
class Prune_Logs {

	/**
	 * Sane default for the minimum input.
	 *
	 * @var float
	 */
	protected $minimum_input = 0.001;

	/**
	 * The MySQL timestamp format.
	 *
	 * @var string
	 */
	protected $mysql_timestamp_format = 'Y-m-d H:i:s';

	/**
	 * Conditionally register hooks based on the param#1.
	 *
	 * @param bool $should_register_hooks Defaults to true.
	 *
	 * @return void
	 */
	public function __construct( $should_register_hooks = true ) {
		if ( $should_register_hooks ) {
			$this->register_hooks();
		}

		$this->minimum_input = apply_filters( 'automator_prune_logs_minimum_input', $this->minimum_input );
	}

	/**
	 * Register various hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {

		// Add setting
		add_action( 'automator_settings_general_logs_content', array( $this, 'add_purge_settings' ), 10 );

		// Add User Deleted setting
		add_action( 'automator_settings_general_logs_content', array( $this, 'add_user_deleted_settings' ), 10 );

		// Add recipe Delete setting
		add_action(
			'automator_settings_general_logs_content',
			array(
				$this,
				'add_recipe_on_completion_delete_settings',
			),
			10
		);

		// Add Delete on uninstall
		add_action(
			'automator_settings_general_logs_content',
			array(
				$this,
				'automator_delete_data_on_uninstall',
			),
			PHP_INT_MAX
		);

		// Add setting to automatically prone logs (just for promotion)
		add_action( 'automator_settings_general_logs_content', array( $this, 'add_pro_auto_prune_settings' ), 15 );

		// Create wp-ajax endpoint.
		add_action( 'wp_ajax_prune_logs', array( $this, 'purge_logs_handler' ) );

		add_action( 'admin_init', array( $this, 'maybe_update_user_deleted_setting' ) );

		add_action( 'admin_init', array( $this, 'maybe_update_delete_recipe_on_completion_setting' ) );

		add_action( 'admin_init', array( $this, 'maybe_update_automator_delete_data_on_uninstall' ) );

		// Register settings
		$this->register_settings();

	}

	/**
	 * Register the settings
	 *
	 * @return void
	 */
	private function register_settings() {
		add_action(
			'admin_init',
			function () {
				register_setting( 'uncanny_automator_manual_prune', 'automator_manual_purge_days' );
				register_setting( 'uncanny_automator_delete_user_records_on_user_delete', 'automator_delete_user_records_on_user_delete' );
				register_setting( 'uncanny_automator_delete_recipe_records_on_completion', 'automator_delete_recipe_records_on_completion' );
				register_setting( 'uncanny_automator_delete_data_on_uninstall', 'automator_delete_data_on_uninstall' );
			}
		);
	}

	/**
	 * Adds purge settings.
	 *
	 * @return void
	 */
	public function add_purge_settings() {

		// Get the date of the last time this action was performed
		$last_manual_prune_date = get_option( 'automator_last_manual_prune_date', '' );

		// Check if it was ever executed
		$user_pruned_before = ! empty( $last_manual_prune_date );

		// Number of days (value of the field)
		$number_of_days = get_option( 'automator_manual_purge_days', '' );

		// Check if the logs were JUST pruned
		$user_just_pruned_logs = automator_filter_has_var( 'pruned' );

		// Load the view
		include Utilities::automator_get_view( 'admin-settings/tab/general/logs/prune-logs.php' );
	}

	/**
	 * Add Delete User Records on User Delete to the settings page
	 */
	public function add_user_deleted_settings() {
		// Check if the setting is enabled
		$is_enabled = get_option( 'automator_delete_user_records_on_user_delete', false );

		// Load the view
		include Utilities::automator_get_view( 'admin-settings/tab/general/logs/delete-user-records.php' );
	}

	/**
	 * Add Delete recipe record on completion to the settings page
	 */
	public function add_recipe_on_completion_delete_settings() {
		// Check if the setting is enabled
		$is_enabled = get_option( 'automator_delete_recipe_records_on_completion', false );

		// Load the view
		include Utilities::automator_get_view( 'admin-settings/tab/general/logs/remove-recipe-log-on-completion.php' );
	}

	/**
	 * @return void
	 */
	public function automator_delete_data_on_uninstall() {
		// Check if the setting is enabled
		$is_enabled = get_option( 'automator_delete_data_on_uninstall', false );

		// Load the view
		include Utilities::automator_get_view( 'admin-settings/tab/general/logs/remove-delete-data-on-uninstall.php' );
	}

	/**
	 * Add a tease of the auto prune tool available in Pro
	 */
	public function add_pro_auto_prune_settings() {
		// Check if the user has Automator Free
		// Don't add it if Pro is active
		if ( ! is_automator_pro_active() ) {
			// Get the link to upgrade to Pro
			$upgrade_to_pro_url = add_query_arg(
			// UTM
				array(
					'utm_source'  => 'uncanny_automator',
					'utm_medium'  => 'settings',
					'utm_content' => 'auto_prune_tease',
				),
				'https://automatorplugin.com/pricing/'
			);

			// Load the view
			include Utilities::automator_get_view( 'admin-settings/tab/general/logs/auto-prune-logs-tease.php' );
		}
	}

	/**
	 * Prune logs wp_ajax handler.
	 *
	 * Does 302 redirect if there is an error.
	 *
	 * @return void
	 */
	public function purge_logs_handler() {

		$prune_value = floatval( automator_filter_input( 'automator_manual_purge_days', INPUT_POST ) );

		// Verify nonce.
		if ( ! wp_verify_nonce( automator_filter_input( '_wpnonce', INPUT_POST ), 'uncanny_automator' ) ) {
			$this->redirect(
				array(
					'error_message' => esc_attr_x( 'Invalid nonce.', 'Prune logs', 'uncanny-automator' ),
				)
			);
		}

		// Begin prune.
		try {
			$this->prune_logs( $prune_value );
		} catch ( Exception $e ) {
			$this->redirect(
				array(
					'error_message' => $e->getMessage(),
				)
			);
		}

		update_option( 'automator_last_manual_prune_date', time() );

		$this->redirect(
			array(
				'pruned' => 1,
			)
		);

		die;
	}

	/**
	 * Prune logs.
	 *
	 * @param float $prune_value
	 *
	 * @return void
	 * @throws Exception
	 */
	public function prune_logs( $prune_value ) {

		// Validates the input. Throws an Exception if there is an error.
		$this->validate_input( $prune_value );

		// 1 day is equals 24 hours. We multiply it by the prune_value and convert it to seconds.
		$days_in_sec = 24 * $prune_value * 60 * 60;

		$dt = new DateTime();
		$dt->setTimestamp( time() - $days_in_sec );
		$dt->setTimezone( new DateTimeZone( Automator()->get_timezone_string() ) );

		$previous_dt_string = $dt->format( $this->mysql_timestamp_format );

		self::delete_logs_from( $previous_dt_string );

		return true;

	}

	/**
	 * Delete logs from a specific datetime string.
	 *
	 * @param string $datetime_string
	 *
	 * @return true
	 *
	 * @todo - Build a feedback handler so we know what happens in case the logs arent successful.
	 */
	public static function delete_logs_from( $datetime_string ) {

		// Create a new instance of this class passing false as argument to the __construct so we dont reload the hooks.
		$instance = new self( false );

		// Retrieves all recipes that are not `in progress`, and are not `completed with notice`.
		$recipe_logs = $instance->get_recipe_logs_from_date( $datetime_string );

		// Delete all logs.
		foreach ( $recipe_logs as $recipe_log ) {
			$instance->purge_logs( $recipe_log['automator_recipe_id'], $recipe_log['ID'], $recipe_log['run_number'] );
		}

		return true;
	}

	/**
	 * Purge logs.
	 *
	 * @param int $recipe_id
	 * @param int $log_id
	 * @param int $run_number
	 *
	 * @return void
	 */
	public function purge_logs( $recipe_id, $log_id, $run_number ) {

		// Prune api logs.
		automator_purge_api_logs( $recipe_id, $log_id );
		// Prune recipe logs.
		automator_purge_recipe_logs( $recipe_id, $log_id );
		// Prune trigger logs.
		automator_purge_trigger_logs( $recipe_id, $log_id );
		// Prune action logs.
		automator_purge_action_logs( $recipe_id, $log_id );
		// Prune closure logs.
		automator_purge_closure_logs( $recipe_id, $log_id );

		do_action( 'automator_recipe_log_deleted', $recipe_id, $log_id, $run_number );
	}

	/**
	 * Retrieves all recipe logs from a specific date.
	 *
	 * @param string $datetime_string In the format of 'Y-m-d H:i:s'.
	 *
	 * @return array
	 */
	public function get_recipe_logs_from_date( $datetime_string ) {

		global $wpdb;

		return (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT `ID`, `automator_recipe_id`, `run_number`
					FROM {$wpdb->prefix}uap_recipe_log
					WHERE `date_time` < %s
					AND ( `completed` <> %d AND `completed` <> %d )",
				$datetime_string,
				Automator_Status::IN_PROGRESS,
				Automator_Status::COMPLETED_WITH_NOTICE
			),
			ARRAY_A
		);

	}

	/**
	 * Retrieves a specific user log.
	 *
	 * @param int $user_id
	 *
	 * @return array
	 */
	public function get_user_logs( $user_id ) {

		global $wpdb;

		return (array) $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$wpdb->prefix}uap_recipe_log WHERE user_id=%d", $user_id ),
			ARRAY_A
		);

	}

	/**
	 * Validates the input.
	 *
	 * @param float $prune_value
	 *
	 * @return true If there are no exception.
	 * @throws Exception
	 */
	public function validate_input( $prune_value ) {

		// Must not be zero.
		if ( empty( $prune_value ) || ! is_numeric( $prune_value ) ) {
			throw new Exception(
				rawurlencode( esc_attr_x( 'Invalid input. Please ensure that the "Days" field contains a valid numeric value, is not empty, and is not equal to zero.', 'Prune logs', 'uncanny-automator' ) ),
				400
			);
		}

		// Add sane amount up to 0.01.
		if ( $prune_value < $this->minimum_input ) {
			throw new Exception(
				rawurlencode( esc_attr_x( 'The field "Days" must be greater than or equals to ' . $this->minimum_input, 'Prune logs', 'uncanny-automator' ) ),
				400
			);
		}

		return true;

	}

	/**
	 * Redirects back to logs settings.
	 *
	 * @param array $args
	 *
	 * @return void
	 */
	protected function redirect( $args = array() ) {

		$url = add_query_arg(
			$args,
			$this->get_logs_settings_url()
		);

		wp_safe_redirect( $url );
		die;
	}

	/**
	 * Update the setting to delete user records on user delete
	 *
	 * @return void
	 */
	public function maybe_update_user_deleted_setting() {

		if ( ! automator_filter_has_var( '_wpnonce', INPUT_POST ) ) {
			return;
		}

		if ( ! wp_verify_nonce( automator_filter_input( '_wpnonce', INPUT_POST ), 'uncanny_automator' ) ) {
			return;
		}

		if ( ! automator_filter_has_var( 'automator_delete_user_records_on_user_delete', INPUT_POST ) ) {
			return;
		}

		$is_enabled = automator_filter_input( 'automator_delete_user_records_on_user_delete', INPUT_POST );

		update_option( 'automator_delete_user_records_on_user_delete', $is_enabled );

		return;
	}

	public function maybe_update_automator_delete_data_on_uninstall() {

		if ( ! automator_filter_has_var( '_wpnonce', INPUT_POST ) ) {
			return;
		}

		if ( ! wp_verify_nonce( automator_filter_input( '_wpnonce', INPUT_POST ), 'uncanny_automator' ) ) {
			return;
		}

		if ( ! automator_filter_has_var( 'automator_delete_data_on_uninstall', INPUT_POST ) ) {
			return;
		}

		$is_enabled = automator_filter_input( 'automator_delete_data_on_uninstall', INPUT_POST );

		update_option( 'automator_delete_data_on_uninstall', $is_enabled );

		return;
	}

	/**
	 * @return void
	 */
	public function maybe_update_delete_recipe_on_completion_setting() {

		if ( ! automator_filter_has_var( '_wpnonce', INPUT_POST ) ) {
			return;
		}

		if ( ! wp_verify_nonce( automator_filter_input( '_wpnonce', INPUT_POST ), 'uncanny_automator' ) ) {
			return;
		}

		if ( ! automator_filter_has_var( 'automator_delete_recipe_records_on_completion', INPUT_POST ) ) {
			return;
		}

		$is_enabled = automator_filter_input( 'automator_delete_recipe_records_on_completion', INPUT_POST );

		update_option( 'automator_delete_recipe_records_on_completion', $is_enabled );

		return;
	}

	/**
	 * Get the URL with the field to prune the logs
	 *
	 * @return string The URL
	 */
	public function get_logs_settings_url() {
		return add_query_arg(
			array(
				'post_type' => 'uo-recipe',
				'page'      => 'uncanny-automator-config',
				'tab'       => 'general',
				'general'   => 'logs',
			),
			admin_url( 'edit.php' )
		);
	}

	/**
	 * @param $params
	 *
	 * @return mixed|null
	 */
	public static function should_remove_log( $params ) {
		$setting_on = get_option( 'automator_delete_recipe_records_on_completion', false );

		if ( empty( $setting_on ) || false === boolval( $setting_on ) ) {
			$setting_on = false;
		} else {
			$setting_on = true;
		}

		return apply_filters( 'automator_recipe_remove_entry_on_completion', $setting_on, $params );
	}
}
