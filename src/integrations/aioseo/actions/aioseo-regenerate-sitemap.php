<?php

namespace Uncanny_Automator\Integrations\Aioseo;

/**
 * Class Aioseo_Regenerate_Sitemap
 *
 * @package Uncanny_Automator
 */
class Aioseo_Regenerate_Sitemap extends \Uncanny_Automator\Recipe\Action {

	/**
	 * Setup action.
	 *
	 * @return void
	 */
	protected function setup_action() {
		$this->set_integration( 'AIOSEO' );
		$this->set_action_code( 'AIOSEO_REGENERATE_SITEMAP' );
		$this->set_action_meta( 'AIOSEO_REGENERATE' );
		$this->set_is_pro( false );
		$this->set_requires_user( false );
		$this->set_background_processing( true );
		$this->set_sentence( esc_html_x( 'Regenerate AIOSEO XML sitemap', 'All in One SEO', 'uncanny-automator' ) );
		$this->set_readable_sentence( esc_html_x( 'Regenerate AIOSEO XML sitemap', 'All in One SEO', 'uncanny-automator' ) );
	}

	/**
	 * Define action options.
	 *
	 * @return array[]
	 */
	public function options() {
		return array();
	}

	/**
	 * Process the action.
	 *
	 * @param int   $user_id     The user ID.
	 * @param array $action_data The action configuration.
	 * @param int   $recipe_id   The recipe ID.
	 * @param array $args        Additional arguments.
	 * @param array $parsed      Parsed token values.
	 *
	 * @return bool
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		if ( ! function_exists( 'aioseo' ) ) {
			$this->add_log_error( esc_html_x( 'All in One SEO is not available.', 'All in One SEO', 'uncanny-automator' ) );
			return false;
		}

		if ( ! aioseo()->options->sitemap->general->enable ) {
			$this->add_log_error( esc_html_x( 'AIOSEO XML sitemap is not enabled.', 'All in One SEO', 'uncanny-automator' ) );
			return false;
		}

		aioseo()->sitemap->scheduleRegeneration();

		return true;
	}
}
