<?php

namespace Uncanny_Automator\Integrations\Aioseo;

/**
 * Class Aioseo_Integration
 *
 * @package Uncanny_Automator
 */
class Aioseo_Integration extends \Uncanny_Automator\Integration {

	/**
	 * Integration setup.
	 *
	 * @return void
	 */
	protected function setup() {
		$this->helpers = new Aioseo_Helpers();
		$this->set_integration( 'AIOSEO' );
		$this->set_name( 'All in One SEO' );
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/aioseo-icon.svg' );
	}

	/**
	 * Load triggers and actions.
	 *
	 * @return void
	 */
	public function load() {
		// Triggers.
		new Aioseo_Seo_Data_Updated( $this->helpers );
		new Aioseo_Seo_Score_Reached( $this->helpers );

		// Actions.
		new Aioseo_Set_Seo_Title( $this->helpers );
		new Aioseo_Set_Seo_Description( $this->helpers );
		new Aioseo_Delete_Seo_Title( $this->helpers );
		new Aioseo_Delete_Seo_Description( $this->helpers );
		new Aioseo_Set_Focus_Keyphrase( $this->helpers );
		new Aioseo_Toggle_Robots_Txt();
		new Aioseo_Toggle_Sitemap();
		new Aioseo_Regenerate_Sitemap();
	}

	/**
	 * Check if AIOSEO is active (free or pro).
	 *
	 * @return bool
	 */
	public function plugin_active() {
		return function_exists( 'aioseo' );
	}
}
