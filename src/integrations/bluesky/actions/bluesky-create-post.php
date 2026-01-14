<?php

namespace Uncanny_Automator\Integrations\Bluesky;

/**
 * Bluesky - Create Post
 *
 * @property Bluesky_App_Helpers $helpers
 * @property Bluesky_Api_Caller $api
 */
class BLUESKY_CREATE_POST extends \Uncanny_Automator\Recipe\App_Action {

	/**
	 * Prefix
	 *
	 * @var string
	 */
	protected $prefix = 'BLUESKY_CREATE_POST';

	/**
	 * Define the action.
	 *
	 * @return void
	 */
	protected function setup_action() {
		$this->set_integration( 'BLUESKY' );
		$this->set_action_code( $this->prefix . '_CODE' );
		$this->set_action_meta( $this->prefix . '_META' );
		$this->set_is_pro( false );
		$this->set_support_link( Automator()->get_author_support_link( $this->action_code, 'knowledge-base/bluesky/' ) );
		$this->set_requires_user( false );
		$this->set_wpautop( false );
		$this->set_sentence(
			sprintf(
				// translators: the text "a post" placeholder.
				esc_attr_x( 'Create {{a post:%1$s}} on Bluesky', 'Bluesky', 'uncanny-automator' ),
				'NON_EXISTING:' . $this->get_action_meta()
			)
		);
		$this->set_readable_sentence( esc_attr_x( 'Create {{a post}} on Bluesky', 'Bluesky', 'uncanny-automator' ) );
	}

	/**
	 * Define options
	 *
	 * @return array
	 */
	public function options() {

		// Main post.
		$post_field = array(
			'option_code'       => $this->get_action_meta(),
			'label'             => esc_html_x( 'Post', 'Bluesky', 'uncanny-automator' ),
			'input_type'        => 'textarea',
			'supports_markdown' => true,
			'required'          => true,
			'description'       => esc_html_x(
				'Messages posted to Bluesky have a 300 character limit. URLs count as 20 characters regardless of length, and URL protocols (http://, https://) are not counted. You may use links (e.g. https://example.com), mentions (e.g. @user.bsky.social), and hashtags (e.g. #WordPress). HTML is not supported.',
				'Bluesky',
				'uncanny-automator'
			),
			'relevant_tokens'   => array(),
		);

		// Media embed options.
		$media_embed_radio = array(
			'input_type'      => 'radio',
			'option_code'     => 'MEDIA_EMBED_TYPE',
			'label'           => esc_html_x( 'Media embed', 'Bluesky', 'uncanny-automator' ),
			'required'        => false,
			'default_value'   => 'default',
			'options'         => array(
				array(
					'value' => 'default',
					'text'  => esc_html_x( 'None', 'Bluesky', 'uncanny-automator' ),
				),
				array(
					'value' => 'upload',
					'text'  => esc_html_x( 'Upload media', 'Bluesky', 'uncanny-automator' ),
				),
				array(
					'value' => 'external',
					'text'  => esc_html_x( 'External media', 'Bluesky', 'uncanny-automator' ),
				),
				array(
					'value' => 'website',
					'text'  => esc_html_x( 'Website', 'Bluesky', 'uncanny-automator' ),
				),
			),
			'relevant_tokens' => array(),
		);

		$dynamic_config = array(
			'default_state'    => 'hidden',
			'visibility_rules' => array(
				array(
					'operator'             => 'AND',
					'rule_conditions'      => array(
						array(
							'option_code' => 'MEDIA_EMBED_TYPE',
							'compare'     => '==',
							'value'       => 'upload',
						),
					),
					'resulting_visibility' => 'show',
				),
			),
		);

		// WP Media embed.
		$media_embed_field = array(
			'option_code'        => 'UPLOADED_MEDIA',
			'label'              => esc_html_x( 'Uploaded media', 'Bluesky', 'uncanny-automator' ),
			'description'        => esc_html_x( 'Upload / select a media file to embed in the post.', 'Bluesky', 'uncanny-automator' ),
			'input_type'         => 'file',
			'file_types'         => array(
				'image/jpeg',
				'image/png',
				'image/gif',
				'image/webp',
				'video/mp4',
				'video/quicktime',
			),
			'required'           => false,
			'dynamic_visibility' => $dynamic_config,
			'relevant_tokens'    => array(),
		);

		$dynamic_config['visibility_rules'][0]['rule_conditions'][0]['value'] = 'external';

		// External media.
		$external_media = array(
			'option_code'        => 'EXTERNAL_MEDIA',
			'label'              => esc_html_x( 'External media', 'Bluesky', 'uncanny-automator' ),
			'input_type'         => 'url',
			'required'           => false,
			'description'        => esc_html_x( 'Enter the URL of the media you want to embed in the post.', 'Bluesky', 'uncanny-automator' ),
			'dynamic_visibility' => $dynamic_config,
			'relevant_tokens'    => array(),
		);

		$dynamic_config['visibility_rules'][0]['rule_conditions'][0]['value'] = 'website';

		// External website.
		$external_website = array(
			'option_code'        => 'EXTERNAL_WEBSITE',
			'label'              => esc_html_x( 'Website', 'Bluesky', 'uncanny-automator' ),
			'input_type'         => 'url',
			'required'           => false,
			'description'        => esc_html_x( 'Enter the URL of a website to create a preview embed (Social card).', 'Bluesky', 'uncanny-automator' ),
			'dynamic_visibility' => $dynamic_config,
			'relevant_tokens'    => array(),
		);

		return array(
			$post_field,
			$media_embed_radio,
			$media_embed_field,
			$external_media,
			$external_website,
		);
	}

	/**
	 * Define tokens.
	 *
	 * @return array
	 */
	public function define_tokens() {
		return array(
			'POST_URL' => array(
				'name' => esc_html_x( 'Post URL', 'Bluesky', 'uncanny-automator' ),
				'type' => 'url',
			),
		);
	}

	/**
	 * Process the action.
	 *
	 * @param int $user_id
	 * @param array $action_data
	 * @param int $recipe_id
	 * @param array $args
	 * @param array $parsed
	 *
	 * @return bool
	 * @throws Exception
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		// Get the post text.
		$text = $this->get_parsed_meta_value( $this->get_action_meta(), $action_data );

		// Get the media embed.
		$media = $this->get_media_embed( $action_data );

		// Format the post text and any selected media into a record for the Bluesky API.
		$record = $this->helpers->get_formatted_post_record( $text, $media );
		$body   = array(
			'action' => 'create_post',
			'record' => wp_json_encode( $record ),
		);

		// Create the post.
		$response = $this->api->api_request( $body, $action_data );
		$data     = isset( $response['data'] ) ? $response['data'] : array();
		$url      = isset( $data['uri'] ) ? $data['uri'] : '';
		$status   = isset( $data['validationStatus'] ) ? $data['validationStatus'] : ''; // valid, unknown

		if ( 'valid' !== $status ) {
			throw new \Exception(
				sprintf(
					esc_html_x( 'The post was not created. Please try again.', 'Bluesky', 'uncanny-automator' )
				)
			);
		}

		// Hydrate the url token.
		$this->hydrate_tokens(
			array(
				'POST_URL' => $this->format_url( $url ),
			)
		);

		return true;
	}

	/**
	 * Get the media embed.
	 *
	 * @param string $media_embed_type
	 * @param array $action_data
	 *
	 * @return mixed array|false
	 */
	private function get_media_embed( $action_data ) {

		// Get the media embed type.
		$media_embed_type = $this->get_parsed_meta_value( 'MEDIA_EMBED_TYPE', $action_data );
		if ( 'default' === $media_embed_type ) {
			return false;
		}

		switch ( $media_embed_type ) {
			case 'upload':
				$upload = $this->get_parsed_meta_value( 'UPLOADED_MEDIA', $action_data );
				$upload = is_string( $upload ) ? json_decode( $upload, true ) : $upload;
				return array(
					'type'  => $media_embed_type,
					'media' => ! empty( $upload ) && is_array( $upload ) ? $upload : false,
				);
			case 'external':
				return array(
					'type'  => $media_embed_type,
					'media' => $this->get_parsed_meta_value( 'EXTERNAL_MEDIA', $action_data ),
				);
			case 'website':
				return array(
					'type'  => $media_embed_type,
					'media' => $this->get_parsed_meta_value( 'EXTERNAL_WEBSITE', $action_data ),
				);
			default:
				return false;
		}
	}

	/**
	 * Format the url from the Bluesky API response.
	 *
	 * @param string $url
	 *
	 * @return string
	 */
	private function format_url( $url ) {
		$post_rkey = basename( $url );
		$handle    = $this->helpers->get_credential_setting( 'handle' );
		return 'https://bsky.app/profile/' . $handle . '/post/' . $post_rkey;
	}
}
