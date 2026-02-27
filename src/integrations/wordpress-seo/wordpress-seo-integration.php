<?php

namespace Uncanny_Automator\Integrations\Wordpress_Seo;

/**
 * Class Wordpress_Seo_Integration
 *
 * @package Uncanny_Automator
 */
class Wordpress_Seo_Integration extends \Uncanny_Automator\Integration {

	/**
	 * Setup Automator integration.
	 *
	 * @return void
	 */
	protected function setup() {
		$this->set_integration( 'WORDPRESS_SEO' );
		$this->set_name( 'Yoast SEO' );
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/wordpress-seo-icon.svg' );
		$this->helpers = new Wordpress_Seo_Helpers();
		$this->register_hooks();
	}

	/**
	 * Load Integration Classes.
	 *
	 * @return void
	 */
	public function load() {
		new Yoast_Seo_Data_Updated( $this->helpers );
		new Yoast_Seo_Score_Reached( $this->helpers );
		new Yoast_Set_Seo_Title( $this->helpers );
		new Yoast_Set_Seo_Description( $this->helpers );
		new Yoast_Delete_Seo_Title( $this->helpers );
		new Yoast_Delete_Seo_Description( $this->helpers );
		new Yoast_Set_Focus_Keyword( $this->helpers );
		new Yoast_Delete_Focus_Keyword( $this->helpers );
	}

	/**
	 * Register AJAX hooks for dynamic dropdowns.
	 *
	 * @return void
	 */
	protected function register_hooks() {
		add_action( 'wp_ajax_automator_yoast_fetch_posts_for_triggers', array( $this->helpers, 'ajax_fetch_posts_for_triggers' ) );
		add_action( 'wp_ajax_automator_yoast_fetch_posts_by_type', array( $this->helpers, 'ajax_fetch_posts_by_type' ) );
	}

	/**
	 * Check if Yoast SEO is active.
	 *
	 * @return bool
	 */
	public function plugin_active() {
		return defined( 'WPSEO_VERSION' );
	}
}
