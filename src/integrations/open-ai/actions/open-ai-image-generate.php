<?php
namespace Uncanny_Automator;

use Uncanny_Automator\OpenAI\HTTP_Client;
use Uncanny_Automator\Recipe;

/**
 * Class OPEN_AI_IMAGE_GENERATE
 *
 * @package Uncanny_Automator
 *
 * @since 5.3  - Added Dall-E-3 and Dall-E-3 (hd).
 * @since 4.11 - Intro.
 */
class OPEN_AI_IMAGE_GENERATE {

	use Recipe\Actions, Recipe\Action_Tokens;

	/**
	 * Default Model.
	 *
	 * @var string
	 */
	private $default_model = 'dall-e-2';

	/**
	 * Default HTTP request body.
	 *
	 * @var (string|int)[]
	 */
	private $default_request_body = array(
		'model'  => 'dall-e-2',
		'prompt' => '',
		'n'      => 1,
		'size'   => '1024x1024',
	);

	public function __construct() {

		$this->set_helpers( new Open_AI_Helpers( false ) );

		$this->setup_action();

		add_action( 'wp_ajax_automator_openai_generate_sizes', array( $this, 'generate_sizes' ) );

	}

	/**
	 * Setup Action.
	 *
	 * @return void.
	 */
	protected function setup_action() {

		$this->set_integration( 'OPEN_AI' );
		$this->set_action_code( 'OPEN_AI_IMAGE_GENERATE' );
		$this->set_action_meta( 'OPEN_AI_IMAGE_GENERATE_META' );
		$this->set_support_link( Automator()->get_author_support_link( $this->get_action_code(), 'knowledge-base/open-ai/' ) );
		$this->set_requires_user( false );

		$this->set_sentence(
			sprintf(
				/* translators: Action sentence */
				esc_attr_x( 'Use {{a prompt:%1$s}} to generate an image', 'OpenAI', 'uncanny-automator' ),
				$this->get_action_meta()
			)
		);

		/* translators: Action sentence */
		$this->set_readable_sentence( esc_attr_x( 'Use {{a prompt}} to generate an image', 'OpenAI', 'uncanny-automator' ) );

		$this->set_options_callback( array( $this, 'load_options' ) );
		$this->set_wpautop( false );

		// Disable background processing.
		$this->set_background_processing( false );

		$this->set_action_tokens( $this->create_action_tokens(), $this->get_action_code() );
		$this->register_action();

	}

	/**
	 * Loads options.
	 *
	 * @return array The list of option fields.
	 */
	public function load_options() {

		$model = array(
			'option_code'           => 'MODEL',
			/* translators: Action field */
			'label'                 => esc_attr_x( 'Model', 'OpenAI', 'uncanny-automator' ),
			'input_type'            => 'select',
			'options'               => array(
				'dall-e-3'           => esc_attr_x( 'Dall-E 3', 'OpenAI', 'uncanny-automator' ),
				'dall-e-3-hd'        => esc_attr_x( 'Dall-E 3 HD', 'OpenAI', 'uncanny-automator' ),
				$this->default_model => esc_attr_x( 'Dall-E 2', 'OpenAI', 'uncanny-automator' ),
			),
			'required'              => false,
			'default_value'         => $this->default_model,
			'options_show_id'       => false,
			'supports_custom_value' => false,
		);

		$image_size = array(
			'option_code'     => 'SIZE',
			/* translators: Action field */
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

		$prompt = array(
			'option_code'       => $this->get_action_meta(),
			/* translators: Action field */
			'label'             => esc_attr_x( 'Prompt', 'OpenAI', 'uncanny-automator' ),
			'input_type'        => 'textarea',
			'supports_markdown' => true,
			'required'          => true,
		);

		return Automator()->utilities->keep_order_of_options(
			array(
				'options_group' => array(
					$this->get_action_meta() => array(
						$model,
						$image_size,
						$prompt,
					),
				),
			)
		);

	}

	/**
	 * Generates sizes endpoint for our OpenAI action.
	 *
	 * @return (true|string[][])[]
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
	 * @param string $model
	 * @param mixed[] $args
	 *
	 * @return void
	 */
	protected function create_body( $model, $args ) {

		// Apply default if empty.
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
	 * Processes action.
	 *
	 * @param $user_id
	 * @param $action_data
	 * @param $recipe_id
	 * @param $args
	 * @param $parsed
	 *
	 * @return void.
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		$prompt = isset( $parsed[ $this->get_action_meta() ] ) ? sanitize_textarea_field( $parsed[ $this->get_action_meta() ] ) : '';
		$size   = isset( $parsed['SIZE'] ) ? $parsed['SIZE'] : '1024x1024';
		$model  = isset( $parsed['MODEL'] ) ? $parsed['MODEL'] : $this->default_model;

		try {

			$args = array(
				'model'  => $model,
				'prompt' => $prompt,
				'size'   => $size,
				'n'      => 1,
			);

			$body = $this->create_body( $model, $args );

			$body = apply_filters( 'automator_openai_image_generate', $body );

			require_once dirname( __DIR__ ) . '/client/http-client.php';

			$client = new HTTP_Client( Api_Server::get_instance() );
			$client->set_endpoint( 'v1/images/generations' );
			$client->set_api_key( (string) automator_get_option( 'automator_open_ai_secret', '' ) );
			$client->set_request_body( $body );

			try {

				$client->send_request();
				$response = $client->get_response();
				// Hydrates tokens based on the generated image.
				$attachment_id = $this->insert_to_media( $response['data'][0]['url'], $prompt );
				$this->hydrate_tokens_from_response( $response, $attachment_id );

			} catch ( \Exception $e ) {

				$action_data['complete_with_errors'] = true;
				return Automator()->complete->action( $user_id, $action_data, $recipe_id, $e->getMessage() );

			}

			Automator()->complete->action( $user_id, $action_data, $recipe_id );

		} catch ( \Exception $e ) {

			$action_data['complete_with_errors'] = true;

			Automator()->complete->action( $user_id, $action_data, $recipe_id, $e->getMessage() );

		}

	}

	/**
	 * Inserts the image URL to the media library.
	 *
	 * @param string $image_url The image url.
	 * @param string $prompt The prompt.
	 *
	 * @return int The attachment ID.
	 */
	public function insert_to_media( $image_url = '', $prompt = '' ) {

		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$attachment_id = media_sideload_image( $image_url, null, $prompt, 'id' );

		if ( is_wp_error( $attachment_id ) ) {
			automator_log( $attachment_id->get_error_message(), 'OpenAI insert_to_media error', true, 'openai' );
		}

		return $attachment_id;

	}

	/**
	 * Hydrates this specific action tokens.
	 *
	 * @param array $response.
	 *
	 * @return self
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
	 * @return string[][]
	 */
	private function create_action_tokens() {
		return array(
			'ATTACHMENT_ID'        => array(
				'name' => __( 'Attachment ID', 'uncanny-automator' ),
				'type' => 'int',
			),
			'ATTACHMENT_URL'       => array(
				'name' => __( 'Attachment URL', 'uncanny-automator' ),
				'type' => 'url',
			),
			'OPENAI_GEN_IMAGE_URL' => array(
				'name' => __( 'OpenAI generated image URL', 'uncanny-automator' ),
				'type' => 'url',
			),
		);
	}

}
