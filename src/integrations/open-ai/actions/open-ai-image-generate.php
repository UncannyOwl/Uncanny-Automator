<?php
namespace Uncanny_Automator;

use Uncanny_Automator\Recipe;

/**
 * Class OPEN_AI_IMAGE_GENERATE
 *
 * @package Uncanny_Automator_Pro
 * @since 4.11
 */
class OPEN_AI_IMAGE_GENERATE {

	use Recipe\Actions;

	use Recipe\Action_Tokens;

	public function __construct() {

		$this->setup_action();

		$this->set_helpers( new Open_AI_Helpers( false ) );

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
				esc_attr__( 'Use {{a prompt:%1$s}} to generate an image', 'uncanny-automator' ),
				$this->get_action_meta()
			)
		);

		/* translators: Action sentence */
		$this->set_readable_sentence( esc_attr__( 'Use {{a prompt}} to generate an image', 'uncanny-automator' ) );

		$this->set_options_callback( array( $this, 'load_options' ) );

		$this->set_wpautop( false );

		$this->set_background_processing( false );

		$this->set_action_tokens(
			array(
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
			),
			$this->get_action_code()
		);

		$this->register_action();

	}

	/**
	 * Loads options.
	 *
	 * @return array The list of option fields.
	 */
	public function load_options() {

		return Automator()->utilities->keep_order_of_options(
			array(
				'options_group' => array(
					$this->get_action_meta() => array(
						array(
							'option_code' => $this->get_action_meta(),
							/* translators: Action field */
							'label'       => esc_attr__( 'Prompt', 'uncanny-automator' ),
							'input_type'  => 'textarea',
							'required'    => true,
						),
						array(
							'option_code'     => 'SIZE',
							/* translators: Action field */
							'label'           => esc_attr__( 'Size', 'uncanny-automator' ),
							'input_type'      => 'select',
							'options'         => array(
								'256x256'   => esc_attr__( '256x256', 'uncanny-automator' ),
								'512x512'   => esc_attr__( '512x512', 'uncanny-automator' ),
								'1024x1024' => esc_attr__( '1024x1024', 'uncanny-automator' ),
							),
							'options_show_id' => false,
						),
					),
				),
			)
		);

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

		$prompt = isset( $parsed[ $this->get_action_meta() ] ) ? sanitize_text_field( $parsed[ $this->get_action_meta() ] ) : '';
		$size   = isset( $parsed['SIZE'] ) ? sanitize_text_field( $parsed['SIZE'] ) : '1024x1024';

		try {

			$body = array(
				'action'       => 'generate_image',
				'prompt'       => $prompt,
				'size'         => $size,
				'access_token' => get_option( 'automator_open_ai_secret', '' ),
			);

			$body = apply_filters( 'automator_openai_image_generate', $body );

			$response = $this->get_helpers()->api_request( $body, $action_data );

			if ( empty( $response['data']['data'][0]['url'] ) ) {
				throw new \Exception( 'OpenAI has responded with an empty image URL. Please try different prompt.', 400 );
			}

			// Hydrates tokens based on the generated image.
			$attachment_id  = $this->insert_to_media( $response['data']['data'][0]['url'], $prompt );
			$attachment_url = wp_get_attachment_url( $attachment_id );

			$this->hydrate_tokens(
				array(
					'ATTACHMENT_ID'        => $attachment_id,
					'ATTACHMENT_URL'       => $attachment_url,
					'OPENAI_GEN_IMAGE_URL' => $response['data']['data'][0]['url'],
				),
				$this->get_action_code()
			);

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

}
