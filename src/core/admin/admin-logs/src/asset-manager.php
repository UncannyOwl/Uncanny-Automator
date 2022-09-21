<?php
namespace Uncanny_Automator\Admin_Logs;

class Asset_Manager {

	public function enqueue_assets() {

		$this->enqueue_assets_base();

		if ( ! $this->in_admin_logs_page() ) {
			return;
		}

		if ( $this->is_pro_installed() ) {

			$this->enqueue_assets_pro();

		}

	}

	public function enqueue_assets_base() {
		// @TODO: Enqueue base asset files here..
	}


	public function enqueue_assets_pro() {

		do_action( 'automator_admin_log_asset_pro_manager_before_load', $this );

		// Load Select2 CSS if not loaded yet.
		if ( ! wp_script_is( 'uap-logs-pro-select2' ) ) {
			wp_enqueue_script(
				'uap-logs-pro-select2',
				\Uncanny_Automator_Pro\Utilities::get_vendor_asset( 'select2/js/select2.min.js' ),
				array( 'jquery' ),
				\Uncanny_Automator\Utilities::automator_get_version(),
				true
			);
		}

		// Load Select2 CSS if not loaded yet.
		if ( ! wp_style_is( 'uap-logs-pro-select2' ) ) {
			// Select2 CSS.
			wp_enqueue_style(
				'uap-logs-pro-select2',
				\Uncanny_Automator_Pro\Utilities::get_vendor_asset( 'select2/css/select2.min.css' ),
				array(),
				\Uncanny_Automator\Utilities::automator_get_version(),
				false
			);
		}

		// Load Moment JS if not loaded yet.
		if ( ! wp_script_is( 'uauap-logs-pro-moment' ) ) {
			// DateRangePicker
			wp_enqueue_script(
				'uap-logs-pro-moment',
				\Uncanny_Automator_Pro\Utilities::get_vendor_asset( 'daterangepicker/js/moment.min.js' ),
				array( 'jquery' ),
				\Uncanny_Automator\Utilities::automator_get_version(),
				true
			);
		}

		// Load DateRangerPicker JS if not loaded yet.
		if ( ! wp_script_is( 'uap-logs-pro-daterangepicker' ) ) {
			wp_enqueue_script(
				'uap-logs-pro-daterangepicker',
				\Uncanny_Automator_Pro\Utilities::get_vendor_asset( 'daterangepicker/js/daterangepicker.js' ),
				array( 'jquery', 'uap-logs-pro-moment' ),
				\Uncanny_Automator\Utilities::automator_get_version(),
				true
			);
		}

		// Load DateRangerPicker CSS if not loaded yet.
		if ( ! wp_style_is( 'uap-logs-pro-daterangepicker' ) ) {
			wp_enqueue_style(
				'uap-logs-pro-daterangepicker',
				\Uncanny_Automator_Pro\Utilities::get_vendor_asset( 'daterangepicker/css/daterangepicker.css' ),
				array(),
				\Uncanny_Automator\Utilities::automator_get_version(),
				false
			);
		}

		// Load main JS if not loaded yet.
		if ( ! wp_style_is( 'uap-logs-pro' ) ) {
			wp_enqueue_script(
				'uap-logs-pro',
				\Uncanny_Automator_Pro\Utilities::get_js( 'admin/logs.js' ),
				array(
					'jquery',
					'uap-logs-pro-select2',
					'uap-logs-pro-moment',
					'uap-logs-pro-daterangepicker',
				),
				\Uncanny_Automator\Utilities::automator_get_version(),
				true
			);
		}

		do_action( 'automator_admin_log_asset_pro_manager_after_load', $this );

	}

	public function is_pro_installed() {

		return defined( 'AUTOMATOR_PRO_PLUGIN_VERSION' ) && method_exists( '\Uncanny_Automator_Pro\Utilities', 'get_vendor_asset' );

	}

	public function in_admin_logs_page() {

		return automator_filter_input( 'page' ) && 'uncanny-automator-admin-logs' === automator_filter_input( 'page' );

	}

}
