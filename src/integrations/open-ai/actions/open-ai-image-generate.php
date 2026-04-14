<?php

namespace Uncanny_Automator\Integrations\OpenAI\Actions;

use Uncanny_Automator\Integrations\OpenAI\Actions\Fields\Image_Generate_Fields;
use Uncanny_Automator\Integrations\OpenAI\Hydrators\Image_Response_Hydrator;
use Uncanny_Automator\Integrations\OpenAI\OpenAI_App_Helpers;
use Uncanny_Automator\Integrations\OpenAI\OpenAI_Api_Caller;
use Uncanny_Automator\Recipe\App_Action;

/**
 * OpenAI Image Generate
 *
 * @since 5.6
 *
 * @property OpenAI_App_Helpers $helpers
 * @property OpenAI_Api_Caller $api
 *
 * @package Uncanny_Automator
 */
class OpenAI_Image_Generate extends App_Action {

	/**
	 * The image hydrator.
	 *
	 * @var Image_Response_Hydrator
	 */
	protected $image_hydrator;

	/**
	 * Setup the action.
	 *
	 * @return void
	 */
	public function setup_action() {

		$this->image_hydrator = new Image_Response_Hydrator();

		$this->set_integration( 'OPEN_AI' );
		$this->set_action_code( 'OPEN_AI_IMAGE_GENERATE_V2' );
		$this->set_action_meta( 'OPEN_AI_IMAGE_GENERATE_V2_META' );
		$this->set_requires_user( false );

		$this->set_sentence(
			sprintf(
				// translators: %1$s: The action meta.
				esc_html_x( 'Use {{a prompt:%1$s}} to generate an image', 'OpenAI', 'uncanny-automator' ),
				$this->get_action_meta()
			)
		);

		$this->set_readable_sentence(
			esc_html_x( 'Use {{a prompt}} to generate an image', 'OpenAI', 'uncanny-automator' )
		);

		$this->set_action_tokens(
			array(
				'IMAGE_ID'                  => array(
					'name' => esc_html_x( 'Image ID', 'OpenAI', 'uncanny-automator' ),
				),
				'IMAGE_URL'                 => array(
					'name' => esc_html_x( 'Image URL', 'OpenAI', 'uncanny-automator' ),
				),
				'INPUT_TOKENS'              => array(
					'name' => esc_html_x( 'Input tokens', 'OpenAI', 'uncanny-automator' ),
				),
				'INPUT_TOKENS_IMAGE_TOKENS' => array(
					'name' => esc_html_x( 'Input tokens (image)', 'OpenAI', 'uncanny-automator' ),
				),
				'INPUT_TOKENS_TEXT_TOKENS'  => array(
					'name' => esc_html_x( 'Input tokens (text)', 'OpenAI', 'uncanny-automator' ),
				),
				'OUTPUT_TOKENS'             => array(
					'name' => esc_html_x( 'Output tokens', 'OpenAI', 'uncanny-automator' ),
				),
				'TOTAL_TOKENS'              => array(
					'name' => esc_html_x( 'Total tokens', 'OpenAI', 'uncanny-automator' ),
				),
			),
			$this->get_action_code()
		);
	}

	/**
	 * Configure the action fields.
	 *
	 * @return array
	 */
	public function options() {

		$field_generator = new Image_Generate_Fields( $this->get_action_meta() );

		return $field_generator->get_fields();
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
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		$model             = $this->get_parsed_meta_value( 'MODEL', 'gpt-image-1' );
		$prompt            = $this->get_parsed_meta_value( 'PROMPT', '' );
		$image_size        = $this->get_parsed_meta_value( 'SIZE', '1024x1024' );
		$quality           = $this->get_parsed_meta_value( 'QUALITY', 'auto' );
		$image_format      = $this->get_parsed_meta_value( 'OUTPUT_FORMAT', 'png' );
		$background        = $this->get_parsed_meta_value( 'BACKGROUND', 'auto' );
		$moderation        = $this->get_parsed_meta_value( 'MODERATION', 'auto' );
		$compression_level = $this->get_parsed_meta_value( 'COMPRESSION', '' );

		$this->image_hydrator->set_output_format( $image_format );

		$request_body = array(
			'model'         => $model,
			'prompt'        => $prompt,
			'n'             => 1,
			'size'          => $image_size,
			'quality'       => $quality,
			'output_format' => $image_format,
			'background'    => $background,
			'moderation'    => $moderation,
		);

		if ( ! empty( $compression_level ) ) {
			$request_body['output_compression'] = intval( $compression_level );
		}

		$request_body = apply_filters( 'automator_openai_image_generate_request_body', $request_body, $this );

		$response = $this->api->openai_request( 'v1/images/generations', $request_body );

		$tokens = $this->image_hydrator->hydrate_from_response( $response );

		$this->hydrate_tokens( $tokens );

		return true;
	}
}
