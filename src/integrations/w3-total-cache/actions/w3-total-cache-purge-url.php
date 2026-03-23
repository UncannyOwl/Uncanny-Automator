<?php

namespace Uncanny_Automator\Integrations\W3_Total_Cache;

/**
 * Class W3_Total_Cache_Purge_Url
 *
 * @package Uncanny_Automator
 * @method \Uncanny_Automator\Integrations\W3_Total_Cache\W3_Total_Cache_Helpers get_item_helpers()
 */
class W3_Total_Cache_Purge_Url extends \Uncanny_Automator\Recipe\Action {

	/**
	 * Setup action.
	 *
	 * @return void
	 */
	protected function setup_action() {
		$this->set_integration( 'W3_TOTAL_CACHE' );
		$this->set_action_code( 'W3_TOTAL_CACHE_PURGE_URL' );
		$this->set_action_meta( 'W3_TOTAL_CACHE_URL' );
		$this->set_is_pro( false );
		$this->set_requires_user( false );
		$this->set_background_processing( true );
		// translators: %1$s is the URL.
		$this->set_sentence( sprintf( esc_html_x( 'Purge W3 Total Cache for {{a specific URL:%1$s}}', 'W3 Total Cache', 'uncanny-automator' ), $this->get_action_meta() ) );
		$this->set_readable_sentence( esc_html_x( 'Purge W3 Total Cache for {{a specific URL}}', 'W3 Total Cache', 'uncanny-automator' ) );
		$this->set_action_tokens(
			array(
				'PURGED_URL' => array(
					'name' => esc_html_x( 'Purged URL', 'W3 Total Cache', 'uncanny-automator' ),
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
				'label'           => esc_html_x( 'URL', 'W3 Total Cache', 'uncanny-automator' ),
				'input_type'      => 'url',
				'required'        => true,
				'relevant_tokens' => array(),
				'description'     => esc_html_x( 'Enter the full URL to purge from cache (e.g. https://example.com/my-page/)', 'W3 Total Cache', 'uncanny-automator' ),
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
			$this->add_log_error( esc_html_x( 'A URL is required to purge cache.', 'W3 Total Cache', 'uncanny-automator' ) );
			return false;
		}

		if ( false === filter_var( $url, FILTER_VALIDATE_URL ) ) {
			// translators: %s is the URL.
			$this->add_log_error( sprintf( esc_html_x( 'Invalid URL provided: %s', 'W3 Total Cache', 'uncanny-automator' ), esc_url( $url ) ) );
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
