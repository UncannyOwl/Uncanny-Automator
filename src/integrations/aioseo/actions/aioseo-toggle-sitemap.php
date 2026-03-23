<?php

namespace Uncanny_Automator\Integrations\Aioseo;

/**
 * Class Aioseo_Toggle_Sitemap
 *
 * @package Uncanny_Automator
 */
class Aioseo_Toggle_Sitemap extends \Uncanny_Automator\Recipe\Action {

	/**
	 * Setup action.
	 *
	 * @return void
	 */
	protected function setup_action() {
		$this->set_integration( 'AIOSEO' );
		$this->set_action_code( 'AIOSEO_TOGGLE_SITEMAP' );
		$this->set_action_meta( 'SITEMAP_STATUS' );
		$this->set_is_pro( false );
		$this->set_requires_user( false );
		$this->set_background_processing( true );
		// translators: %1$s is the status.
		$this->set_sentence( sprintf( esc_html_x( 'Set AIOSEO XML sitemap to {{a status:%1$s}}', 'All in One SEO', 'uncanny-automator' ), $this->get_action_meta() ) );
		$this->set_readable_sentence( esc_html_x( 'Set AIOSEO XML sitemap to {{a status}}', 'All in One SEO', 'uncanny-automator' ) );
	}

	/**
	 * Define action options.
	 *
	 * @return array[]
	 */
	public function options() {
		return array(
			array(
				'option_code'           => $this->get_action_meta(),
				'label'                 => esc_html_x( 'Status', 'All in One SEO', 'uncanny-automator' ),
				'input_type'            => 'select',
				'required'              => true,
				'supports_custom_value' => false,
				'options'               => array(
					array(
						'text'  => esc_html_x( 'Enabled', 'All in One SEO', 'uncanny-automator' ),
						'value' => 'enable',
					),
					array(
						'text'  => esc_html_x( 'Disabled', 'All in One SEO', 'uncanny-automator' ),
						'value' => 'disable',
					),
				),
			),
		);
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

		$status = sanitize_text_field( $parsed[ $this->get_action_meta() ] ?? '' );
		$enable = 'enable' === $status;

		aioseo()->options->sitemap->general->enable = $enable;
		aioseo()->options->save();

		return true;
	}
}
