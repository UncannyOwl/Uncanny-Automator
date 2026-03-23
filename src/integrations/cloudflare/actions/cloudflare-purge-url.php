<?php

namespace Uncanny_Automator\Integrations\Cloudflare;

/**
 * Class Cloudflare_Purge_Url
 *
 * @package Uncanny_Automator
 * @method \Uncanny_Automator\Integrations\Cloudflare\Cloudflare_Helpers get_item_helpers()
 */
class Cloudflare_Purge_Url extends \Uncanny_Automator\Recipe\Action {

	/**
	 * Setup action.
	 *
	 * @return void
	 */
	protected function setup_action() {
		$this->set_integration( 'CLOUDFLARE' );
		$this->set_action_code( 'CLOUDFLARE_PURGE_URL' );
		$this->set_action_meta( 'CLOUDFLARE_URL' );
		$this->set_is_pro( false );
		$this->set_requires_user( false );
		$this->set_background_processing( true );
		// translators: %1$s is the URL.
		$this->set_sentence( sprintf( esc_html_x( 'Purge Cloudflare cache for {{a specific URL:%1$s}}', 'Cloudflare', 'uncanny-automator' ), $this->get_action_meta() ) );
		$this->set_readable_sentence( esc_html_x( 'Purge Cloudflare cache for {{a specific URL}}', 'Cloudflare', 'uncanny-automator' ) );
		$this->set_action_tokens(
			array(
				'PURGED_URL' => array(
					'name' => esc_html_x( 'Purged URL', 'Cloudflare', 'uncanny-automator' ),
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
				'label'           => esc_html_x( 'URL', 'Cloudflare', 'uncanny-automator' ),
				'input_type'      => 'url',
				'required'        => true,
				'relevant_tokens' => array(),
				'description'     => esc_html_x( 'Enter the full URL to purge from Cloudflare cache. The URL must map to a post so that related URLs can also be purged.', 'Cloudflare', 'uncanny-automator' ),
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
			$this->add_log_error( 'A URL is required to purge cache.' );
			return false;
		}

		if ( false === filter_var( $url, FILTER_VALIDATE_URL ) ) {
			$this->add_log_error( sprintf( 'Invalid URL provided: %s', $url ) );
			return false;
		}

		$result = $this->get_item_helpers()->purge_url_cache( $url );

		if ( false === $result ) {
			$this->add_log_error( sprintf( 'Failed to purge cache for URL: %s. The URL could not be resolved to a post, or the Cloudflare plugin is not properly configured.', $url ) );
			return false;
		}

		$this->hydrate_tokens(
			array(
				'PURGED_URL' => $url,
			)
		);

		return true;
	}
}
