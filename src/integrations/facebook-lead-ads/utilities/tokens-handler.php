<?php

namespace Uncanny_Automator\Integrations\Facebook_Lead_Ads\Utilities;

use WP_Post;
use WP_REST_Request;

/**
 * Class Tokens_Handler
 *
 * Handles token analysis and processing for Facebook Lead Ads.
 *
 * @package Uncanny_Automator\Integrations\Facebook_Lead_Ads\Utilities
 */
class Tokens_Handler {

	/**
	 * Meta key for Facebook Lead Ads integration.
	 *
	 * @var string
	 */
	const META = 'FB_LEAD_ADS_META';

	/**
	 * The post meta for the token record.
	 *
	 * @var string
	 */
	const POST_META_KEY = 'meta_form_fields';

	/**
	 * Analyzes tokens and updates post metadata with form fields.
	 *
	 * @param WP_Post        $wp_post The WordPress post object to update.
	 * @param WP_REST_Request $request The REST request containing token data.
	 *
	 * @return void
	 */
	public function analyze_tokens( WP_Post $wp_post, WP_REST_Request $request ) {

		// Validate and retrieve the necessary input data.
		$input_data = $this->validate_input();
		if ( ! $input_data ) {
			return;
		}

		// Extract form and page IDs.
		$form_id = $input_data['form_id'];
		$page_id = $input_data['page_id'];

		// Fetch form fields.
		$form_fields = $this->fetch_form_fields( $page_id, $form_id );
		if ( is_wp_error( $form_fields ) ) {
			automator_log( $form_fields->get_error_message(), self::class, true );
			return;
		}

		// Update post meta with form fields.
		$this->update_post_meta( $wp_post->ID, $form_fields );
	}

	/**
	 * Validates and retrieves input data from POST.
	 *
	 * @return array|null Associative array with 'form_id' and 'page_id', or null on failure.
	 */
	private function validate_input() {
		$option_code = automator_filter_input( 'optionCode', INPUT_POST );

		if ( self::META !== $option_code ) {
			return null;
		}

		$input_values = automator_filter_input_array( 'optionValue', INPUT_POST );
		$form_id      = absint( $input_values['FORMS'] ?? 0 );
		$page_id      = absint( $input_values[ self::META ] ?? 0 );

		return array(
			'form_id' => $form_id,
			'page_id' => $page_id,
		);
	}

	/**
	 * Fetches form fields using the Client API.
	 *
	 * @param int $page_id The ID of the Facebook page.
	 * @param int $form_id The ID of the Facebook form.
	 *
	 * @return array|WP_Error The form fields data or WP_Error on failure.
	 */
	private function fetch_form_fields( $page_id, $form_id ) {
		$credentials_manager = new Credentials_Manager();
		$page_access_token   = $credentials_manager->get_page_access_token( $page_id );

		$client = new Client();
		return $client->get_form_fields( $page_id, $form_id, $page_access_token );
	}

	/**
	 * Updates post metadata with form fields.
	 *
	 * @param int   $post_id The ID of the post to update.
	 * @param array $form_fields The form fields data to store in the post meta.
	 *
	 * @return void
	 */
	private function update_post_meta( $post_id, array $form_fields ) {
		$fields = $form_fields['data']['questions'] ?? array();
		update_post_meta( $post_id, self::POST_META_KEY, $fields );
	}

	/**
	 * Maps lead data to their corresponding keys and sanitizes the output using htmlentities.
	 *
	 * @param array $lead_data The lead data array containing `name` and `values`.
	 * @param array $field_map The field map array containing `key` definitions.
	 *
	 * @return array Mapped and sanitized lead data.
	 */
	public static function map_lead_data( array $lead_data, array $field_map ) {

		$output = array();

		if ( ! isset( $lead_data['field_data'] ) || ! is_array( $lead_data['field_data'] ) ) {
			return $output;
		}

		foreach ( $lead_data['field_data'] as $field ) {
			$key   = $field['name'] ?? '';
			$value = $field['values'] ?? '';

			if ( is_array( $value ) ) {
				// Sanitize each element in the array.
				$sanitized_values = array_map( 'htmlentities', $value );
				$output[ $key ]   = implode( ', ', $sanitized_values );
				continue;
			}

			// Sanitize scalar values.
			$output[ $key ] = htmlentities( (string) $value );
		}

		return $output;
	}
}
