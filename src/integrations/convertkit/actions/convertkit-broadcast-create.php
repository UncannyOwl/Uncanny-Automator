<?php

namespace Uncanny_Automator\Integrations\ConvertKit;

/**
 * ConvertKit - Create a broadcast (v4 only)
 *
 * @property ConvertKit_App_Helpers $helpers
 * @property ConvertKit_Api_Caller $api
 */
class CONVERTKIT_BROADCAST_CREATE extends \Uncanny_Automator\Recipe\App_Action {

	/**
	 * Setup Action.
	 *
	 * @return void
	 */
	protected function setup_action() {
		$this->set_integration( 'CONVERTKIT' );
		$this->set_action_code( 'CONVERTKIT_BROADCAST_CREATE' );
		$this->set_action_meta( 'CONVERTKIT_BROADCAST_CREATE_META' );
		$this->set_is_pro( false );
		$this->set_requires_user( false );
		$this->set_background_processing( true );
		$this->set_support_link( Automator()->get_author_support_link( $this->get_action_code(), 'knowledge-base/convertkit/' ) );
		$this->set_readable_sentence( esc_attr_x( 'Create {{a broadcast}}', 'ConvertKit', 'uncanny-automator' ) );
		$this->set_sentence(
			sprintf(
				// translators: %1$s is the subject line
				esc_attr_x( 'Create {{a broadcast:%1$s}}', 'ConvertKit', 'uncanny-automator' ),
				$this->get_action_meta()
			)
		);
	}

	/**
	 * Requires OAuth (v4) connection.
	 *
	 * @return bool
	 */
	public function requirements_met() {
		return ! $this->helpers->is_v3();
	}

	/**
	 * Define options.
	 *
	 * @return array
	 */
	public function options() {
		return array(
			array(
				'option_code' => $this->get_action_meta(),
				'label'       => esc_attr_x( 'Subject', 'ConvertKit', 'uncanny-automator' ),
				'input_type'  => 'text',
				'required'    => true,
			),
			array(
				'option_code'      => 'CONTENT',
				'label'            => esc_attr_x( 'Content', 'ConvertKit', 'uncanny-automator' ),
				'input_type'       => 'textarea',
				'supports_tinymce' => true,
				'supports_media'   => true,
				'required'         => true,
			),
			array(
				'option_code' => 'PREVIEW_TEXT',
				'label'       => esc_attr_x( 'Preview text', 'ConvertKit', 'uncanny-automator' ),
				'input_type'  => 'text',
				'required'    => false,
			),
			array(
				'option_code' => 'DESCRIPTION',
				'label'       => esc_attr_x( 'Description', 'ConvertKit', 'uncanny-automator' ),
				'input_type'  => 'text',
				'required'    => false,
			),
			array(
				'option_code'   => 'PUBLIC',
				'label'         => esc_attr_x( 'Publish to Creator Profile', 'ConvertKit', 'uncanny-automator' ),
				'input_type'    => 'checkbox',
				'is_toggle'     => true,
				'required'      => false,
				'default_value' => false,
			),
			array(
				'option_code' => 'SEND_AT',
				'label'       => esc_attr_x( 'Send at', 'ConvertKit', 'uncanny-automator' ),
				'input_type'  => 'text',
				'required'    => false,
				'description' => esc_attr_x( 'ISO 8601 datetime (e.g. 2025-06-01T09:00:00Z). Leave empty to save as draft.', 'ConvertKit', 'uncanny-automator' ),
			),
			array(
				'option_code'   => 'TAG_FILTER_TYPE',
				'label'         => esc_attr_x( 'Subscriber filter', 'ConvertKit', 'uncanny-automator' ),
				'input_type'    => 'select',
				'required'      => false,
				'default_value' => 'all_subscribers',
				'options'       => array(
					array(
						'value' => 'all_subscribers',
						'text'  => esc_attr_x( 'All subscribers', 'ConvertKit', 'uncanny-automator' ),
					),
					array(
						'value' => 'all',
						'text'  => esc_attr_x( 'Subscribers with all of these tags', 'ConvertKit', 'uncanny-automator' ),
					),
					array(
						'value' => 'any',
						'text'  => esc_attr_x( 'Subscribers with any of these tags', 'ConvertKit', 'uncanny-automator' ),
					),
					array(
						'value' => 'none',
						'text'  => esc_attr_x( 'Subscribers with none of these tags', 'ConvertKit', 'uncanny-automator' ),
					),
				),
			),
			array_merge(
				$this->helpers->get_tag_option_config( 'FILTER_TAGS' ),
				array(
					'label'                     => esc_attr_x( 'Tags', 'ConvertKit', 'uncanny-automator' ),
					'required'                  => false,
					'supports_multiple_values'  => true,
					'options_show_id'           => false,
					'dependency'                => 'TAG_FILTER_TYPE',
					'dependency_any_conditions' => array( 'all', 'any', 'none' ),
				)
			),
		);
	}

	/**
	 * Define tokens.
	 *
	 * @return array
	 */
	public function define_tokens() {
		return array(
			'BROADCAST_ID'         => array(
				'name' => esc_html_x( 'Broadcast ID', 'ConvertKit', 'uncanny-automator' ),
				'type' => 'int',
			),
			'BROADCAST_PUBLIC_URL' => array(
				'name' => esc_html_x( 'Broadcast public URL', 'ConvertKit', 'uncanny-automator' ),
				'type' => 'url',
			),
			'BROADCAST_DRAFT_URL'  => array(
				'name' => esc_html_x( 'Broadcast draft URL', 'ConvertKit', 'uncanny-automator' ),
				'type' => 'url',
			),
			'BROADCAST_SEND_AT'    => array(
				'name' => esc_html_x( 'Broadcast send at', 'ConvertKit', 'uncanny-automator' ),
				'type' => 'text',
			),
		);
	}

	/**
	 * Process the action.
	 *
	 * @param int   $user_id
	 * @param array $action_data
	 * @param int   $recipe_id
	 * @param array $args
	 * @param array $parsed
	 *
	 * @return bool
	 * @throws \Exception
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		$subject = sanitize_text_field( $parsed[ $this->get_action_meta() ] ?? '' );
		$content = $parsed['CONTENT'] ?? '';

		if ( empty( $subject ) ) {
			throw new \Exception(
				esc_html_x( 'Please provide a subject line.', 'ConvertKit', 'uncanny-automator' )
			);
		}

		if ( empty( $content ) ) {
			throw new \Exception(
				esc_html_x( 'Please provide broadcast content.', 'ConvertKit', 'uncanny-automator' )
			);
		}

		// Build the broadcast payload — all fields are top-level per Kit v4 API.
		$broadcast = array(
			'subject' => $subject,
			'content' => $content,
		);

		$preview_text = sanitize_text_field( $parsed['PREVIEW_TEXT'] ?? '' );
		$description  = sanitize_text_field( $parsed['DESCRIPTION'] ?? '' );
		$public       = ! empty( $parsed['PUBLIC'] );
		$send_at      = sanitize_text_field( $parsed['SEND_AT'] ?? '' );

		if ( ! empty( $preview_text ) ) {
			$broadcast['preview_text'] = $preview_text;
		}

		if ( ! empty( $description ) ) {
			$broadcast['description'] = $description;
		}

		if ( $public ) {
			$broadcast['public'] = true;
		}

		if ( ! empty( $send_at ) ) {
			$broadcast['send_at'] = $send_at;
		}

		// Build subscriber filter from tag filter type + selected tags.
		$subscriber_filter = $this->build_subscriber_filter( $parsed, $action_data );

		if ( ! empty( $subscriber_filter ) ) {
			$broadcast['subscriber_filter'] = $subscriber_filter;
		}

		$response = $this->api->api_request(
			array(
				'action'    => 'create_broadcast',
				'broadcast' => wp_json_encode( $broadcast ),
			),
			$action_data
		);

		$data     = $response['data']['broadcast'] ?? array();
		$is_draft = empty( $data['send_at'] );

		$this->hydrate_tokens(
			array(
				'BROADCAST_ID'         => $data['id'] ?? '',
				'BROADCAST_PUBLIC_URL' => ! $is_draft ? ( $data['public_url'] ?? '' ) : '',
				'BROADCAST_DRAFT_URL'  => $is_draft && ! empty( $data['id'] )
					? 'https://app.kit.com/campaigns/' . $data['id'] . '/draft'
					: '-',
				'BROADCAST_SEND_AT'    => ! empty( $data['send_at'] )
					? $this->helpers->get_formatted_time( $data['send_at'] )
					: '-',
			)
		);

		return true;
	}

	/**
	 * Build the subscriber_filter array from parsed tag filter options.
	 *
	 * @param array $parsed      The parsed action meta values.
	 * @param array $action_data The raw action data.
	 *
	 * @return array Empty array for "all subscribers", otherwise the filter structure.
	 */
	private function build_subscriber_filter( $parsed, $action_data ) {

		$filter_type = sanitize_text_field( $parsed['TAG_FILTER_TYPE'] ?? 'all_subscribers' );

		if ( 'all_subscribers' === $filter_type ) {
			return array();
		}

		// Collect tag IDs from multi-value select.
		$tag_ids = $this->get_multi_select_values( 'FILTER_TAGS', $parsed, $action_data );

		if ( empty( $tag_ids ) ) {
			return array();
		}

		// Cast to integers.
		$tag_ids = array_map( 'absint', $tag_ids );
		$tag_ids = array_filter( $tag_ids );

		if ( empty( $tag_ids ) ) {
			return array();
		}

		return array(
			array(
				$filter_type => array(
					array(
						'type' => 'tag',
						'ids'  => array_values( $tag_ids ),
					),
				),
			),
		);
	}

	/**
	 * Get values from a multi-select option.
	 *
	 * @param string $option_code The option code.
	 * @param array  $parsed      The parsed values.
	 * @param array  $action_data The raw action data.
	 *
	 * @return array
	 */
	private function get_multi_select_values( $option_code, $parsed, $action_data ) {

		// Check for JSON-encoded array in meta (supports_multiple_values).
		$raw = $action_data['meta'][ $option_code ] ?? $parsed[ $option_code ] ?? '';

		if ( is_array( $raw ) ) {
			return $raw;
		}

		$decoded = json_decode( $raw, true );

		if ( is_array( $decoded ) ) {
			return $decoded;
		}

		// Single value fallback.
		if ( ! empty( $raw ) ) {
			return array( $raw );
		}

		return array();
	}
}
