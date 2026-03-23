<?php

namespace Uncanny_Automator\Integrations\Wp_Rocket;

/**
 * Class Wp_Rocket_Purge_Url
 *
 * @package Uncanny_Automator
 * @method \Uncanny_Automator\Integrations\Wp_Rocket\Wp_Rocket_Helpers get_item_helpers()
 */
class Wp_Rocket_Purge_Url extends \Uncanny_Automator\Recipe\Action {

	/**
	 * Setup action.
	 *
	 * @return void
	 */
	protected function setup_action() {
		$this->set_integration( 'WP_ROCKET' );
		$this->set_action_code( 'WP_ROCKET_PURGE_URL' );
		$this->set_action_meta( 'WP_ROCKET_URL' );
		$this->set_is_pro( false );
		$this->set_requires_user( false );
		$this->set_background_processing( true );
		// translators: %1$s is the URL.
		$this->set_sentence( sprintf( esc_html_x( 'Purge WP Rocket cache for {{a specific URL:%1$s}}', 'WP Rocket', 'uncanny-automator' ), $this->get_action_meta() ) );
		$this->set_readable_sentence( esc_html_x( 'Purge WP Rocket cache for {{a specific URL}}', 'WP Rocket', 'uncanny-automator' ) );
		$this->set_action_tokens(
			array(
				'PURGED_URL' => array(
					'name' => esc_html_x( 'Purged URL', 'WP Rocket', 'uncanny-automator' ),
					'type' => 'url',
				),
			),
			$this->get_action_code()
		);
	}

	/**
	 * Define action options.
	 *
	 * @return array[]
	 */
	public function options() {
		return array(
			array(
				'option_code'     => $this->get_action_meta(),
				'label'           => esc_html_x( 'URL', 'WP Rocket', 'uncanny-automator' ),
				'input_type'      => 'url',
				'required'        => true,
				'relevant_tokens' => array(),
				'description'     => esc_html_x( 'Enter the full URL to purge from cache (e.g. https://example.com/my-page/)', 'WP Rocket', 'uncanny-automator' ),
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

		$url = $parsed[ $this->get_action_meta() ] ?? '';

		if ( empty( $url ) ) {
			$this->add_log_error( esc_html_x( 'A URL is required to purge cache.', 'WP Rocket', 'uncanny-automator' ) );
			return false;
		}

		if ( false === filter_var( $url, FILTER_VALIDATE_URL ) ) {
			$this->add_log_error( sprintf( esc_html_x( 'Invalid URL provided: %s', 'WP Rocket', 'uncanny-automator' ), $url ) );
			return false;
		}

		$this->get_item_helpers()->purge_url_cache( $url );

		$this->hydrate_tokens(
			array(
				'PURGED_URL' => $url,
			)
		);

		return true;
	}
}
