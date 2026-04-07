<?php

namespace Uncanny_Automator\Integrations\OpenAI;

use Uncanny_Automator\Recipe\App_Action;

/**
 * Class OPEN_AI_IMAGE_GENERATE_DALL_E
 *
 * @package Uncanny_Automator
 *
 * @since 5.3  - Added Dall-E-3 and Dall-E-3 (hd).
 * @since 4.11 - Intro.
 *
 * @property OpenAI_App_Helpers $helpers
 * @property OpenAI_Api_Caller $api
 */
class OPEN_AI_IMAGE_GENERATE_DALL_E extends App_Action {

	/**
	 * Default Model.
	 *
	 * @var string
	 */
	private $default_model = 'dall-e-2';

	/**
	 * Default HTTP request body.
	 *
	 * @var array
	 */
	private $default_request_body = array(
		'model'  => 'dall-e-2',
		'prompt' => '',
		'n'      => 1,
		'size'   => '1024x1024',
	);

	/**
	 * @return void
	 */
	protected function setup_action() {

		$this->set_integration( 'OPEN_AI' );
		$this->set_action_code( 'OPEN_AI_IMAGE_GENERATE' );
		$this->set_action_meta( 'OPEN_AI_IMAGE_GENERATE_META' );
		$this->set_support_link( Automator()->get_author_support_link( $this->get_action_code(), 'knowledge-base/open-ai/' ) );
		$this->set_requires_user( false );
		$this->set_wpautop( false );
		$this->set_background_processing( false );

		$this->set_sentence(
			sprintf(
				// translators: %1$s is the input field name
				esc_attr_x( 'Use {{a prompt:%1$s}} to generate an image with DALL-E', 'OpenAI', 'uncanny-automator' ),
				$this->get_action_meta()
			)
		);

		$this->set_readable_sentence( esc_attr_x( 'Use {{a prompt}} to generate an image with DALL-E', 'OpenAI', 'uncanny-automator' ) );

		$this->set_action_tokens( $this->create_action_tokens(), $this->get_action_code() );

		add_action( 'wp_ajax_automator_openai_generate_sizes', array( $this, 'generate_sizes' ) );
	}

	/**
	 * @return array
	 */
	public function options() {

		$model = array(
			'option_code'           => 'MODEL',
			'label'                 => esc_attr_x( 'Model', 'OpenAI', 'uncanny-automator' ),
			'input_type'            => 'select',
			'options'               => array(
				array(
					'value' => 'dall-e-3',
					'text'  => esc_attr_x( 'Dall-E 3', 'OpenAI', 'uncanny-automator' ),
				),
				array(
					'value' => 'dall-e-3-hd',
					'text'  => esc_attr_x( 'Dall-E 3 HD', 'OpenAI', 'uncanny-automator' ),
				),
				array(
					'value' => $this->default_model,
					'text'  => esc_attr_x( 'Dall-E 2', 'OpenAI', 'uncanny-automator' ),
				),
			),
			'required'              => false,
			'default_value'         => $this->default_model,
			'options_show_id'       => false,
			'supports_custom_value' => false,
		);

		$image_size = array(
			'option_code'     => 'SIZE',
			'label'           => esc_attr_x( 'Size', 'OpenAI', 'uncanny-automator' ),
			'input_type'      => 'select',
			'options'         => array(),
			'ajax'            => array(
				'endpoint'      => 'automator_openai_generate_sizes',
				'event'         => 'parent_fields_change',
				'listen_fields' => array( 'MODEL' ),
			),
			'options_show_id' => false,
		);

		return array(
			$model,
			$image_size,
			$this->helpers->get_prompt_field( $this->get_action_meta() ),
		);
	}

	/**
	 * Generates sizes endpoint for our OpenAI action.
	 *
	 * @return void
	 */
	public function generate_sizes() {

		Automator()->utilities->ajax_auth_check();

		$selected_model = isset( $_POST['values']['MODEL'] ) ? sanitize_text_field( wp_unslash( $_POST['values']['MODEL'] ) ) : $this->default_model; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		$options = array(
			array(
				'text'  => esc_attr_x( '256x256', 'OpenAI', 'uncanny-automator' ),
				'value' => '256x256',
			),
			array(
				'text'  => esc_attr_x( '512x512', 'OpenAI', 'uncanny-automator' ),
				'value' => '512x512',
			),
			array(
				'text'  => esc_attr_x( '1024x1024', 'OpenAI', 'uncanny-automator' ),
				'value' => '1024x1024',
			),
		);

		if ( str_starts_with( $selected_model, 'dall-e-3' ) ) {
			$options = array(
				array(
					'text'  => esc_attr_x( '1024x1024', 'OpenAI', 'uncanny-automator' ),
					'value' => '1024x1024',
				),
				array(
					'text'  => esc_attr_x( '1024x1792', 'OpenAI', 'uncanny-automator' ),
					'value' => '1024x1792',
				),
				array(
					'text'  => esc_attr_x( '1792x1024', 'OpenAI', 'uncanny-automator' ),
					'value' => '1792x1024',
				),
			);
		}

		wp_send_json(
			array(
				'success' => true,
				'options' => $options,
			)
		);
	}

	/**
	 * Creates an HTTP Request body for OpenAI Consumption.
	 *
	 * @param string $model The model.
	 * @param array  $args The arguments.
	 *
	 * @return array
	 */
	protected function create_body( $model, $args ) {

		if ( empty( $model ) ) {
			$model = $this->default_model;
		}

		$args = wp_parse_args( $args, $this->default_request_body );

		if ( 'dall-e-3-hd' === $model ) {
			$args['model']   = 'dall-e-3';
			$args['quality'] = 'hd';
		}

		return $args;
	}

	/**
	 * @param int   $user_id
	 * @param array $action_data
	 * @param int   $recipe_id
	 * @param array $args
	 * @param array $parsed
	 *
	 * @return bool
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		$prompt = isset( $parsed[ $this->get_action_meta() ] ) ? sanitize_textarea_field( $parsed[ $this->get_action_meta() ] ) : '';
		$size   = isset( $parsed['SIZE'] ) ? $parsed['SIZE'] : '1024x1024';
		$model  = isset( $parsed['MODEL'] ) ? $parsed['MODEL'] : $this->default_model;

		$request_args = array(
			'model'  => $model,
			'prompt' => $prompt,
			'size'   => $size,
			'n'      => 1,
		);

		$body = $this->create_body( $model, $request_args );
		$body = apply_filters( 'automator_openai_image_generate', $body );

		$response = $this->api->openai_request( 'v1/images/generations', $body );

		$attachment_id = $this->insert_to_media( $response['data'][0]['url'], $prompt );
		$this->hydrate_tokens_from_response( $response, $attachment_id );

		return true;
	}

	/**
	 * Inserts the image URL to the media library.
	 *
	 * @param string $image_url The image URL.
	 * @param string $prompt The prompt.
	 *
	 * @return int The attachment ID.
	 */
	public function insert_to_media( $image_url = '', $prompt = '' ) {

		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$description = apply_filters( 'automator_openai_image_generate_description', $prompt );

		$attachment_id = media_sideload_image( $image_url, null, $description, 'id' );

		if ( is_wp_error( $attachment_id ) ) {
			throw new \Exception( esc_html( $attachment_id->get_error_message() ), 500 );
		}

		return $attachment_id;
	}

	/**
	 * Hydrates this specific action tokens.
	 *
	 * @param array    $response The API response.
	 * @param int|null $attachment_id The attachment ID.
	 *
	 * @return void
	 */
	protected function hydrate_tokens_from_response( $response, $attachment_id ) {

		if ( empty( $response['data'][0]['url'] ) ) {
			throw new \Exception( 'OpenAI has responded with an empty image URL. Please try different prompt.', 400 );
		}

		$attachment_url = wp_get_attachment_url( $attachment_id );

		$this->hydrate_tokens(
			array(
				'ATTACHMENT_ID'        => $attachment_id,
				'ATTACHMENT_URL'       => $attachment_url,
				'OPENAI_GEN_IMAGE_URL' => $response['data'][0]['url'],
			),
			$this->get_action_code()
		);
	}

	/**
	 * Creates Action Tokens.
	 *
	 * @return array
	 */
	private function create_action_tokens() {
		return array(
			'ATTACHMENT_ID'        => array(
				'name' => esc_html_x( 'Attachment ID', 'OpenAI', 'uncanny-automator' ),
				'type' => 'int',
			),
			'ATTACHMENT_URL'       => array(
				'name' => esc_html_x( 'Attachment URL', 'OpenAI', 'uncanny-automator' ),
				'type' => 'url',
			),
			'OPENAI_GEN_IMAGE_URL' => array(
				'name' => esc_html_x( 'OpenAI generated image URL', 'OpenAI', 'uncanny-automator' ),
				'type' => 'url',
			),
		);
	}
}
