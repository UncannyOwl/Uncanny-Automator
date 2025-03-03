<?php

namespace Uncanny_Automator;

/**
 * Class Admin_Settings_General_Improve_Automator
 *
 * @since   3.7
 * @version 3.7
 * @package Uncanny_Automator
 * @author  Agustin B.
 */
class Admin_Settings_General_Improve_Automator {
	/**
	 * Class constructor
	 */
	public function __construct() {

		// Define the tab
		$this->create_tab();

		// Add checkbox to Allow usage tracking
		$this->allow_usage_tracking_setting();

		// Add the feedback and review sections
		$this->send_feedback_section();
		$this->add_review_section();

	}

	/**
	 * Adds the tab using the automator_settings_tab filter
	 */
	private function create_tab() {
		// Add the tab using the filter
		add_filter(
			'automator_settings_general_tabs',
			function( $tabs ) {
				// General
				$tabs['improve-automator'] = (object) array(
					'name'     => esc_html__( 'Improve Automator', 'uncanny-automator' ),
					'function' => array( $this, 'tab_output' ),
					'preload'  => false, // Determines if the content should be loaded even if the tab is not selected
					'icon'     => 'heart',
				);

				return $tabs;
			},
			10,
			1
		);
	}

	/**
	 * Outputs the content of the "General" tab
	 */
	public function tab_output() {
		// Load the view
		include Utilities::automator_get_view( 'admin-settings/tab/general/improve-automator.php' );
	}

	/**
	 * Adds the option to enable usage tracking
	 *
	 * @return undefined
	 */
	private function allow_usage_tracking_setting() {

		// Check if we should add it first
		// We will show only this to Free users that don't have an automatorplugin.com account connected
		if (
			// Has Free
			! is_automator_pro_active()

			// Make sure it's NOT connected
			// Connected sites have this enabled by default
			&& false === Api_Server::get_license_type()
		) {
			// Register the setting
			add_action(
				'admin_init',
				function() {
					// Allow usage tracking switch
					register_setting(
						'uncanny_automator_improve_automator_usage_tracking',
						'automator_reporting',
						array(
							'sanitize_callback' => 'absint',
						)
					);
				}
			);

			// Add the switch
			add_action(
				'automator_settings_general_improve_automator_content',
				function() {
					// Check if the setting is enabled
					$is_usage_tracking_enabled = automator_get_option( 'automator_reporting', false );

					// Check if updated and reset the state.
					$updated = $this->save_usage_tracking_setting();
					if ( ! empty( $updated ) ) {
						$is_usage_tracking_enabled = 'enabled' === $updated;
					}

					// Load the view
					include Utilities::automator_get_view( 'admin-settings/tab/general/improve-automator/usage-tracking.php' );
				},
				10
			);
		}
	}

	/**
	 * Save the usage tracking setting
	 *
	 * @return mixed string || null
	 */
	private function save_usage_tracking_setting() {

		$key = 'uncanny_automator_improve_automator_usage_tracking';

		// Check if we are on the correct options page.
		if ( ! automator_filter_has_var( 'option_page', INPUT_POST ) ) {
			return;
		}

		if ( automator_filter_input( 'option_page', INPUT_POST ) !== $key ) {
			return;
		}

		// Validate the nonce.
		if ( ! automator_filter_has_var( '_wpnonce', INPUT_POST ) ) {
			return;
		}

		if ( ! wp_verify_nonce( automator_filter_input( '_wpnonce', INPUT_POST ), "{$key}-options" ) ) {
			return;
		}

		// Sanitize and save the setting
		if ( automator_filter_has_var( 'automator_reporting', INPUT_POST ) ) {
			// Save enabled state.
			if ( automator_filter_input( 'automator_reporting', INPUT_POST, FILTER_VALIDATE_BOOLEAN ) ) {
				automator_update_option( 'automator_reporting', 1 );
				return 'enabled';
			}
			// Delete the option if it's disabled.
			automator_delete_option( 'automator_reporting' );
			return 'disabled';
		}
	}

	/**
	 * Adds the feedback section
	 *
	 * @return undefined
	 */
	private function send_feedback_section() {
		// Add the section
		add_action(
			'automator_settings_general_improve_automator_content',
			function() {
				// Load the view
				include Utilities::automator_get_view( 'admin-settings/tab/general/improve-automator/feedback.php' );
			},
			15
		);
	}

	/**
	 * Adds the review section
	 *
	 * @return undefined
	 */
	private function add_review_section() {
		// Add the section
		add_action(
			'automator_settings_general_improve_automator_content',
			function() {
				// Load the view
				include Utilities::automator_get_view( 'admin-settings/tab/general/improve-automator/review.php' );
			},
			20
		);
	}
}

new Admin_Settings_General_Improve_Automator();
