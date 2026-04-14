<?php

namespace Uncanny_Automator\Integrations\OpenAI;

use Exception;

/**
 * OpenAI App Helpers
 *
 * @package Uncanny_Automator
 *
 * @property OpenAI_Api_Caller $api
 */
class OpenAI_App_Helpers extends \Uncanny_Automator\App_Integrations\App_Helpers {

	////////////////////////////////////////////////////////////
	// Properties
	////////////////////////////////////////////////////////////

	/**
	 * Set additional properties for the OpenAI integration.
	 *
	 * @return void
	 */
	public function set_properties() {
		// Maintain backward compatibility with the legacy option key.
		$this->set_credentials_option_name( 'automator_open_ai_secret' );
	}

	////////////////////////////////////////////////////////////
	// Abstract methods
	////////////////////////////////////////////////////////////

	/**
	 * Validate credentials.
	 *
	 * OpenAI credentials are a simple string (the API key).
	 *
	 * @param mixed $credentials The credentials.
	 * @param array $args Optional arguments.
	 *
	 * @return string The credentials string.
	 */
	public function validate_credentials( $credentials, $args = array() ) {
		return is_string( $credentials ) ? $credentials : '';
	}

	////////////////////////////////////////////////////////////
	// Integration-specific methods
	////////////////////////////////////////////////////////////

	/**
	 * Determine if the connected user has access to OpenAI's GPT-4 model.
	 *
	 * @return bool True if has access. Otherwise, false.
	 */
	public function has_gpt4_access() {
		return 'yes' === $this->determine_gpt4_access();
	}

	/**
	 * Determine if the connected API key has access to GPT-4.
	 *
	 * Cached in uap_options with no time-based expiry. Cleared on disconnect or recheck.
	 *
	 * @return string Returns 'yes' if the connected api key has access to GPT-4 API. Returns 'no' otherwise.
	 */
	public function determine_gpt4_access() {

		$credentials = $this->get_credentials();

		if ( empty( $credentials ) ) {
			return 'no';
		}

		$option_key = $this->get_option_key( 'gpt4_access' );
		$cached     = automator_get_option( $option_key, false );

		if ( false !== $cached ) {
			return $cached;
		}

		try {
			$response        = $this->api->get_model( 'gpt-4' );
			$has_gpt4_access = ! empty( $response ) ? 'yes' : 'no';
		} catch ( Exception $e ) {
			$has_gpt4_access = 'no';
		}

		automator_update_option( $option_key, $has_gpt4_access );

		return $has_gpt4_access;
	}

	/**
	 * AJAX handler: Fetch GPT models from the Automator API.
	 *
	 * @return void
	 */
	public function get_gpt_models_ajax() {
		$this->fetch_models( 'gpt_models', 'get_gpt_models' );
	}

	/**
	 * AJAX handler: Fetch image generation models.
	 *
	 * @return void
	 */
	public function get_image_generation_models_ajax() {
		$this->fetch_models( 'image_generation_models', 'get_image_generation_models' );
	}

	/**
	 * Fetch models from the API with caching.
	 *
	 * @param string $option_suffix The option key suffix for caching.
	 * @param string $api_action    The API action name.
	 *
	 * @return void
	 */
	private function fetch_models( $option_suffix, $api_action ) {

		Automator()->utilities->verify_nonce();

		$option_key = $this->get_option_key( $option_suffix );
		$cached     = $this->get_app_option( $option_key );

		if ( ! empty( $cached['data'] ) && ! $cached['refresh'] && ! $this->is_ajax_refresh() ) {
			$this->ajax_success( $cached['data'] );
		}

		$items = array();

		try {
			$response = $this->api->api_request( $api_action );

			if ( isset( $response['data'] ) && is_array( $response['data'] ) ) {
				foreach ( $response['data'] as $model ) {
					$items[] = array(
						'value' => $model,
						'text'  => $model,
					);
				}
			}

			if ( ! empty( $items ) ) {
				$this->save_app_option( $option_key, $items );
			}
		} catch ( Exception $e ) {
			$this->ajax_error( $e->getMessage() );
		}

		$this->ajax_success( $items );
	}

	////////////////////////////////////////////////////////////
	// Common action option fields
	////////////////////////////////////////////////////////////

	/**
	 * Get a content textarea field.
	 *
	 * @param string $option_code The option code.
	 * @param string $label       The field label. Default 'Content'.
	 *
	 * @return array The field definition.
	 */
	public function get_content_field( $option_code, $label = '' ) {

		if ( empty( $label ) ) {
			$label = esc_attr_x( 'Content', 'OpenAI', 'uncanny-automator' );
		}

		return array(
			'option_code' => $option_code,
			'label'       => $label,
			'input_type'  => 'textarea',
			'required'    => true,
		);
	}

	/**
	 * Get a prompt textarea field with markdown support.
	 *
	 * @param string $option_code The option code.
	 * @param string $label       The field label. Default 'Prompt'.
	 *
	 * @return array The field definition.
	 */
	public function get_prompt_field( $option_code, $label = '' ) {

		if ( empty( $label ) ) {
			$label = esc_attr_x( 'Prompt', 'OpenAI', 'uncanny-automator' );
		}

		return array(
			'option_code'       => $option_code,
			'label'             => $label,
			'input_type'        => 'textarea',
			'supports_markdown' => true,
			'required'          => true,
		);
	}

	////////////////////////////////////////////////////////////
	// Legacy migration
	////////////////////////////////////////////////////////////

	/**
	 * Migrate text models for legacy actions.
	 *
	 * @param int $recipe_id The recipe ID.
	 *
	 * @return void
	 */
	public function migrate_text_models( $recipe_id ) {

		if ( 'yes' === get_post_meta( $recipe_id, 'uap_openai_model_updated', true ) ) {
			return;
		}

		global $wpdb;

		$actions = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID FROM $wpdb->posts WHERE post_type = 'uo-action' AND post_parent = %d",
				$recipe_id
			),
			ARRAY_A
		);

		foreach ( $actions as $action ) {
			$model = get_post_meta( absint( $action['ID'] ), 'MODEL', true );

			if ( 'text-curie-001' === $model ) {
				update_post_meta( $action['ID'], 'MODEL', 'davinci-002' );
				update_post_meta( $action['ID'], 'MODEL_readable', 'davinci-002' );
			}

			if ( 'text-ada-001' === $model || 'text-babbage-001' === $model ) {
				update_post_meta( $action['ID'], 'MODEL', 'babbage-002' );
				update_post_meta( $action['ID'], 'MODEL_readable', 'babbage-002' );
			}
		}

		update_post_meta( $recipe_id, 'uap_openai_model_updated', 'yes' );
	}
}
