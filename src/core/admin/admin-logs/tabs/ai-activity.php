<?php
/**
 * Uncanny_Automator\Admin_Logs_Ai_Activity
 *
 * @package Uncanny_Automator
 */

namespace Uncanny_Automator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adds the AI activity tab to the Logs page.
 */
class Admin_Logs_Ai_Activity {

	/**
	 * Defines the tab.
	 */
	public function __construct() {
		$this->create_tab();
	}

	/**
	 * Adds the tab after the Recipe, Trigger and Action tabs, for users who can manage AI.
	 *
	 * @return void
	 */
	private function create_tab() {
		add_filter(
			'automator_admin_logs_top_level_tabs_items',
			function ( $tabs ) {
				if ( ! Admin_Ai_Settings::current_user_can_manage_ai() ) {
					return $tabs;
				}

				$tabs['ai-activity'] = (object) array(
					'name'     => esc_html__( 'AI activity', 'uncanny-automator' ),
					'function' => array( $this, 'tab_output' ),
					'preload'  => false,
				);

				return $tabs;
			},
			10,
			1
		);
	}

	/**
	 * Outputs the content of the "AI activity" tab. The Logs view already wraps it in the site auth element.
	 *
	 * @return void
	 */
	public function tab_output() {
		echo '<uap-f-page-logs-ai-activity></uap-f-page-logs-ai-activity>';
	}
}

new Admin_Logs_Ai_Activity();
