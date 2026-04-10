<?php

namespace Uncanny_Automator\Integrations\Facebook;

use Uncanny_Automator\App_Integrations\App_Helpers;
use Exception;

/**
 * Class Facebook_App_Helpers
 *
 * @package Uncanny_Automator
 *
 * @property Facebook_Api_Caller $api
 */
class Facebook_App_Helpers extends App_Helpers {

	/**
	 * The facebook bridge.
	 *
	 * @var Facebook_Bridge
	 */
	private $facebook_bridge;

	/**
	 * Set class properties.
	 *
	 * @return void
	 */
	public function set_properties() {
		$this->facebook_bridge = Facebook_Bridge::get_instance();
	}

	/**
	 * Is connected.
	 *
	 * @return bool
	 */
	public function is_connected() {
		return $this->facebook_bridge->user_has_connected_facebook();
	}

	/**
	 * Validate credentials.
	 *
	 * @param array $credentials The credentials to validate.
	 * @param array $args        Additional arguments.
	 *
	 * @return array The validated credentials.
	 * @throws Exception If credentials are invalid.
	 */
	public function validate_credentials( $credentials, $args = array() ) {
		if ( ! $this->facebook_bridge->is_vault_credentials( $credentials ) ) {
			throw new Exception(
				esc_html_x( 'Facebook is not connected. Please connect your account in Automator settings.', 'Facebook', 'uncanny-automator' )
			);
		}

		return $credentials;
	}

	////////////////////////////////////////////////////////////
	// Integration Methods
	////////////////////////////////////////////////////////////

	/**
	 * Get linked pages.
	 *
	 * Returns cached pages if available, otherwise fetches from API via bridge.
	 *
	 * @param bool $force_refresh Force refresh the pages.
	 *
	 * @return array
	 * @throws Exception
	 */
	public function get_linked_pages( $force_refresh = false ) {
		// Return cached pages if not forcing refresh.
		if ( ! $force_refresh ) {
			$existing_pages = $this->get_linked_pages_option_data();
			if ( ! empty( $existing_pages ) ) {
				return $existing_pages;
			}
		}

		// Fetch and store via bridge (single source of truth).
		return $this->facebook_bridge->fetch_and_store_linked_pages( $this->api );
	}

	/**
	 * Get unformatted linked pages option data.
	 *
	 * @return array
	 */
	public function get_linked_pages_option_data() {
		return $this->facebook_bridge->get_facebook_pages_settings();
	}

	/**
	 * Get linked pages for dropdowns (AJAX).
	 *
	 * @return void
	 */
	public function get_linked_pages_ajax() {
		Automator()->utilities->verify_nonce();

		try {
			$pages = $this->get_linked_pages( $this->is_ajax_refresh() );
		} catch ( Exception $e ) {
			$this->ajax_error( $e->getMessage() );
		}

		$options = array();
		foreach ( $pages as $page ) {
			$options[] = array(
				'value' => $page['value'],
				'text'  => $page['text'],
			);
		}

		$this->ajax_success( $options );
	}

	/**
	 * Get Linked Pages UI select config.
	 *
	 * @param string $option_code The option code.
	 *
	 * @return array
	 */
	public function get_linked_pages_select_config( $option_code ) {
		return array(
			'option_code'            => $option_code,
			'label'                  => esc_html_x( 'Facebook Page', 'Facebook', 'uncanny-automator' ),
			'input_type'             => 'select',
			'options'                => array(),
			'required'               => true,
			'supports_custom_value'  => false,
			'show_label_in_sentence' => true,
			'relevant_tokens'        => array(),
			'ajax'                   => array(
				'endpoint' => 'automator_facebook_get_linked_pages',
				'event'    => 'on_load',
			),
		);
	}

	/**
	 * Get linked page ID from parsed.
	 *
	 * @param array  $parsed   The parsed data.
	 * @param string $meta_key The meta key.
	 *
	 * @return string
	 * @throws Exception
	 */
	public function get_linked_page_id_from_parsed( $parsed, $meta_key ) {
		if ( ! isset( $parsed[ $meta_key ] ) ) {
			throw new Exception( esc_html_x( 'Facebook page is required', 'Facebook', 'uncanny-automator' ) );
		}

		return (string) sanitize_text_field( $parsed[ $meta_key ] );
	}

	/**
	 * Get post link token config.
	 *
	 * @return array
	 */
	public function get_post_link_token_config() {
		return array(
			'POST_LINK' => array(
				'name' => esc_html_x( 'Link to Facebook post', 'Facebook', 'uncanny-automator' ),
				'type' => 'url',
			),
		);
	}

	/**
	 * Hydrate post link token.
	 *
	 * @param string $post_id The post ID.
	 *
	 * @return array
	 */
	public function hydrate_post_link_token( $post_id ) {
		return array(
			'POST_LINK' => 'https://www.facebook.com/' . $post_id,
		);
	}
}
