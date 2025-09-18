<?php

namespace Uncanny_Automator\Integrations\Github;

use Exception;

/**
 * Github Integration
 *
 * @package Uncanny_Automator
 *
 * @property Github_App_Helpers $helpers
 */
class Github_Integration extends \Uncanny_Automator\App_Integrations\App_Integration {

	/**
	 * Get the integration config.
	 *
	 * @return array
	 */
	public static function get_config() {
		return array(
			'integration'  => 'GITHUB',    // Integration code.
			'name'         => 'GitHub',    // Integration name.
			'api_endpoint' => 'v2/github', // Automator API server endpoint.
			'settings_id'  => 'github',    // Settings ID ( Settings url / tab id ).
		);
	}

	/**
	 * Integration Set-up.
	 *
	 * @return void
	 */
	protected function setup() {
		// Define helpers with common config values.
		$this->helpers = new Github_App_Helpers( self::get_config() );

		// Set the icon URL.
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/github-icon.svg' );

		// Finalize setup via the parent class with the common config.
		$this->setup_app_integration( self::get_config() );
	}

	/**
	 * Load Integration Classes.
	 *
	 * @return void
	 */
	public function load() {
		// Load settings page.
		new Github_Settings(
			$this->dependencies,
			$this->get_settings_config()
		);

		// Load actions.
		new GITHUB_ADD_COMMENT_ISSUE_OR_PR( $this->dependencies );
		new GITHUB_ADD_RELEASE_TAG_TO_BRANCH( $this->dependencies );
		new GITHUB_ADD_LABEL_TO_ISSUE_PR( $this->dependencies );
		new GITHUB_REMOVE_LABEL_FROM_ISSUE_PR( $this->dependencies );
	}

	/**
	 * Check if app is connected.
	 *
	 * @return bool
	 */
	protected function is_app_connected() {
		try {
			$credentials = $this->helpers->get_credentials();
			return ! empty( $credentials['github_id'] );
		} catch ( Exception $e ) {
			return false;
		}
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		// Recipe options AJAX handlers.
		add_action( 'wp_ajax_automator_github_get_repo_options', array( $this->helpers, 'get_repo_options_ajax' ) );
		add_action( 'wp_ajax_automator_github_get_repo_issues_and_pr_options', array( $this->helpers, 'get_repo_issues_and_pr_options_ajax' ) );
		add_action( 'wp_ajax_automator_github_get_repo_tag_options', array( $this->helpers, 'get_repo_tag_options_ajax' ) );
		add_action( 'wp_ajax_automator_github_get_repo_branches_options', array( $this->helpers, 'get_repo_branches_options_ajax' ) );
		add_action( 'wp_ajax_automator_github_get_repo_label_options', array( $this->helpers, 'get_repo_label_options_ajax' ) );
	}
}
