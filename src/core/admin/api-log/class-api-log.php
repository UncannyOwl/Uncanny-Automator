<?php

namespace Uncanny_Automator;

/**
 * Class Api_Log
 *
 * @package Uncanny_Automator
 */
class Api_Log {

	/**
	 *
	 */
	public function __construct() {
		include_once 'class-api-log-table.php';

		if ( defined( 'AUTOMATOR_API_LOGS' ) && AUTOMATOR_API_LOGS ) {
			add_action( 'admin_menu', array( $this, 'register_options_menu_page' ) );
			add_filter( 'automator_logs_header_tabs', array( $this, 'register_tab' ) );
		}

		add_action( 'automator_log_body', array( $this, 'log_body' ) );
		add_filter( 'automator_enqueue_global_assets', array( $this, 'add_log_page' ) );
		add_filter( 'automator_log_pages', array( $this, 'add_log_page' ) );
	}

	/**
	 * register_options_menu_page
	 *
	 * @return void
	 */
	public function register_options_menu_page() {

		$parent_slug = 'options.php';
		$function    = array( Admin_Menu::get_instance(), 'logs_options_menu_page_output' );
		$title       = esc_attr__( 'API log', 'uncanny-automator' );

		add_submenu_page(
			$parent_slug,
			$title,
			$title,
			'manage_options',
			'uncanny-automator-api-log',
			$function,
			9
		);
	}

	/**
	 * @param $tabs
	 *
	 * @return mixed
	 */
	public function register_tab( $tabs ) {
		$tabs['uncanny-automator-api-log'] = esc_attr__( 'API log', 'uncanny-automator' );
		return $tabs;
	}

	/**
	 * @param $current_tab
	 * @param array $args
	 */
	public function log_body( $current_tab, $args = array() ) {

		$headings = array(
			'type'          => esc_attr__( 'Type', 'uncanny-automator' ),
			'date'          => esc_attr__( 'Date', 'uncanny-automator' ),
			'title'         => esc_attr__( 'Title', 'uncanny-automator' ),
			'endpoint'      => esc_attr__( 'Endpoint', 'uncanny-automator' ),
			'status'        => esc_attr__( 'Response code', 'uncanny-automator' ),
			'completed'     => esc_attr__( 'Status', 'uncanny-automator' ),
			'error_message' => esc_attr__( 'Notes', 'uncanny-automator' ),
			'time_spent'    => esc_attr__( 'Response time (ms)', 'uncanny-automator' ),
		);

		if ( ! defined( 'AUTOMATOR_PRO_FILE' ) ) {
			$headings['price']   = esc_attr__( 'App credits charged', 'uncanny-automator' );
			$headings['balance'] = esc_attr__( 'App credits left', 'uncanny-automator' );
		}

		$headings['actions'] = esc_attr__( 'Actions', 'uncanny-automator' );

		$headings = wp_parse_args( $args, $headings );

		$sortables = array(
			'type'          => array( 'type', true ),
			'date'          => array( 'date', true ),
			'title'         => array( 'title', true ),
			'completed'     => array( 'completed', true ),
			'error_message' => array( 'error_message', true ),
			'recipe_title'  => array( 'recipe_title', true ),
			'status'        => array( 'status', true ),
			'time_spent'    => array( 'time_spent', true ),
			'endpoint'      => array( 'endpoint', true ),
		);

		$sortables = apply_filters( 'automator_setup_api_logs_sortables', $sortables );

		//Prepare Table of elements
		$wp_list_table = new Api_Log_Table();
		$wp_list_table->set_columns( $headings );
		$wp_list_table->set_sortable_columns( $sortables );
		$wp_list_table->set_tab( $current_tab );
		$wp_list_table->prepare_items();
		$wp_list_table->display();
	}

	/**
	 * @param $pages
	 *
	 * @return mixed
	 */
	public function add_log_page( $pages ) {
		$pages[] = 'uncanny-automator-api-log';
		return $pages;
	}

	/**
	 * @param $action_log_id
	 *
	 * @return string
	 */
	public static function resend_button_html( $action_log_id ) {
		return '<div class="uap-logs-details-re-run-action-wrapper">

			<uo-button
				size="small"
				color="secondary"
				class="uap-logs-details-re-run-action"
				data-item="' . $action_log_id . '"
			>
				<uo-icon id="repeat"></uo-icon> ' . __( 'Resend', 'uncanny-automator' ) . '
			</uo-button>

		</div>';
	}
}
