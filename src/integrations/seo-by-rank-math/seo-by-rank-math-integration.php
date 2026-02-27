<?php

namespace Uncanny_Automator\Integrations\Seo_By_Rank_Math;

/**
 * Class Seo_By_Rank_Math_Integration
 *
 * @package Uncanny_Automator
 */
class Seo_By_Rank_Math_Integration extends \Uncanny_Automator\Integration {

	/**
	 * Setup Automator integration.
	 *
	 * @return void
	 */
	protected function setup() {
		$this->set_integration( 'SEO_BY_RANK_MATH' );
		$this->set_name( 'Rank Math SEO' );
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/rank-math-icon.svg' );
		$this->helpers = new Seo_By_Rank_Math_Helpers();
		$this->register_hooks();
	}

	/**
	 * Load Integration Classes.
	 *
	 * @return void
	 */
	public function load() {
		new Rank_Math_Seo_Data_Updated( $this->helpers );
		new Rank_Math_Seo_Score_Reached( $this->helpers );
		new Rank_Math_Set_Seo_Title( $this->helpers );
		new Rank_Math_Set_Seo_Description( $this->helpers );
		new Rank_Math_Delete_Seo_Title( $this->helpers );
		new Rank_Math_Delete_Seo_Description( $this->helpers );
		new Rank_Math_Set_Focus_Keyword( $this->helpers );
		new Rank_Math_Delete_Focus_Keyword( $this->helpers );
	}

	/**
	 * Register AJAX hooks for dynamic dropdowns.
	 *
	 * @return void
	 */
	protected function register_hooks() {
		$this->helpers->register_metadata_hooks();
		add_action( 'wp_ajax_automator_rank_math_fetch_posts_for_triggers', array( $this->helpers, 'ajax_fetch_posts_for_triggers' ) );
		add_action( 'wp_ajax_automator_rank_math_fetch_posts_by_type', array( $this->helpers, 'ajax_fetch_posts_by_type' ) );
	}

	/**
	 * Check if Rank Math SEO is active.
	 *
	 * @return bool
	 */
	public function plugin_active() {
		return defined( 'RANK_MATH_VERSION' );
	}
}
