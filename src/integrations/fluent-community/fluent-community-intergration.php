<?php
namespace Uncanny_Automator\Integrations\Fluent_Community;

/**
 * Class Fluent_Community_Integration
 *
 * @package Uncanny_Automator
 */
class Fluent_Community_Integration extends \Uncanny_Automator\Integration {

	/**
	 * Integration Set-up.
	 */
	protected function setup() {
		$this->helpers = new Fluent_Community_Helpers();

		$this->set_integration( 'FLUENT_COMMUNITY' );

		$this->set_name( 'Fluent Community' );

		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/fluent-community-icon.svg' );

		$this->register_hooks();
	}

	/**
	 * Bootstrap actions, triggers, settings page, etc.
	 *
	 * @return void
	 */
	public function load() {

		//triggers
		new FLUENTCOMMUNITY_USER_ENROLLED_COURSE( $this->helpers );
		new FLUENTCOMMUNITY_USER_COURSE_COMPLETED( $this->helpers );
		new FLUENTCOMMUNITY_USER_SPACE_JOINED( $this->helpers );
		new FLUENTCOMMUNITY_USER_SPACE_POSTED( $this->helpers );
		new FLUENTCOMMUNITY_USER_LESSON_COMPLETED( $this->helpers );
		//actions
		new FLUENTCOMMUNITY_ENROLL_USER_COURSE( $this->helpers );
		new FLUENTCOMMUNITY_ADD_USER_TO_SPACE( $this->helpers );
	}

	/**
	 * Check if the plugin is active.
	 *
	 * @return bool Returns true if \Fluent_Community class is active. Returns false, othwerwise.
	 */
	public function plugin_active() {
		return class_exists( '\FluentCommunity\App\Services\Helper' );
	}


	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'wp_ajax_automator_fluentcommunity_lessons_fetch', array( $this->helpers, 'ajax_fetch_lessons_by_course' ) );
		add_action( 'wp_ajax_automator_fluentcommunity_lessons_fetch_for_action', array( $this->helpers, 'ajax_fetch_lessons_by_course_for_action' ) );
	}
}
