<?php
namespace Uncanny_Automator\Integrations\OpenAI\Actions;

use Uncanny_Automator\Api_Server;
use Uncanny_Automator\Recipe\Action_Tokens;
use Uncanny_Automator\OpenAI\HTTP_Client;
use Uncanny_Automator\Integrations\OpenAI\Actions\Fields\Image_Generate_Fields;

// Hydrators
use Uncanny_Automator\Integrations\OpenAI\Hydrators\Image_Response_Hydrator;

/**
 * OpenAI Image Generate
 *
 * @since 5.6
 */
class OpenAI_Image_Generate extends \Uncanny_Automator\Recipe\Action {

	/**
	 * Import Action tokens trait.
	 */
	use Action_Tokens;

	/**
	 * The option key for the OpenAI API key.
	 *
	 * @var string
	 */
	const API_KEY_OPTION_KEY = 'automator_open_ai_secret';


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

		// The sentence that appear after saving the action.
		$this->set_sentence(
			sprintf(
				// translators: %1$s: The action meta.
				esc_html_x( 'Use {{a prompt:%1$s}} to generate an image', 'OpenAI', 'uncanny-automator' ),
				$this->get_action_meta()
			)
		);

		// The sentence that appear in the integration action selection.
		$this->set_readable_sentence(
			esc_html_x( 'Use {{a prompt}} to generate an image', 'OpenAI', 'uncanny-automator' )
		);

		// Set the action tokens.
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
	 * @param int    $user_id
	 * @param array  $action_data
	 * @param int    $recipe_id
	 * @param array  $args
	 * @param       $parsed
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

		// Set the output format.
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

		// Action runner will automatically log all exception as action error.
		$openai = new HTTP_Client( Api_Server::get_instance() );
		$openai->set_endpoint( 'v1/images/generations' );
		$openai->set_api_key( automator_get_option( self::API_KEY_OPTION_KEY, '' ) );
		$openai->set_request_body( $request_body );

		// Dispatch the request.
		$openai->send_request();

		// Use the image hydrator to hydrate the tokens from response.
		$tokens = $this->image_hydrator->hydrate_from_response( $openai->get_response() );

		// Send the tokens to the action tokens hydrator.
		$this->hydrate_tokens( $tokens );

		return true;
	}
}
