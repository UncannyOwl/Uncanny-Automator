<?php
namespace Uncanny_Automator\Integrations\Gemini\Actions;

use Uncanny_Automator\Recipe\Action;
use Uncanny_Automator\Core\Lib\AI\Core\Traits\Base_AI_Provider_Trait;
use Uncanny_Automator\Core\Lib\AI\Core\Traits\Base_Payload_Message_Array_Builder_Trait;
use Uncanny_Automator\Core\Lib\AI\Http\Response;

/**
 * Google Gemini chat generation action.
 *
 * @since 5.6
 * @package Uncanny_Automator
 */
class Gemini_Chat_Generate extends Action {

	use Base_AI_Provider_Trait;
	use Base_Payload_Message_Array_Builder_Trait;

	/**
	 * Setup action.
	 */
	protected function setup_action() {

		$this->set_integration( 'GEMINI' );
		$this->set_action_code( 'GEMINI_CHAT_GENERATE' );
		$this->set_action_meta( 'GEMINI_CHAT_GENERATE_META' );
		$this->set_is_pro( false );
		$this->set_requires_user( false );

		$this->set_sentence(
			sprintf(
				/* translators: 1: Prompt field placeholder, 2: Model field placeholder */
				esc_html_x(
					'Use {{a prompt:%1$s}} to generate a text response with {{a Gemini model:%2$s}}',
					'Google Gemini',
					'uncanny-automator'
				),
				'PROMPT:' . $this->get_action_meta(),
				'MODEL:' . $this->get_action_meta()
			)
		);

		$this->set_readable_sentence(
			esc_html_x(
				'Use {{a prompt}} to generate a text response with {{a Gemini model}}',
				'Google Gemini',
				'uncanny-automator'
			)
		);

		$this->set_action_tokens(
			array(
				'RESPONSE'        => array(
					'name' => esc_html_x( 'Response', 'Gemini', 'uncanny-automator' ),
					'type' => 'text',
				),
				'PROMPT_TOKENS'   => array(
					'name' => esc_html_x( 'Prompt tokens usage', 'Gemini', 'uncanny-automator' ),
				),
				'RESPONSE_TOKENS' => array(
					'name' => esc_html_x( 'Response tokens usage', 'Gemini', 'uncanny-automator' ),
				),
				'TOTAL_TOKENS'    => array(
					'name' => esc_html_x( 'Total tokens usage', 'Gemini', 'uncanny-automator' ),
				),
			),
			$this->get_action_code()
		);
	}

	/**
	 * Define action option fields.
	 *
	 * @return array
	 */
	public function options() {

		$model_options = array(
			array(
				'text'  => 'Gemini 2.5 Flash Preview 05-20',
				'value' => 'gemini-2.5-flash-preview-05-20',
			),
			array(
				'text'  => 'Gemini 2.5 Pro Preview',
				'value' => 'gemini-2.5-pro-preview-05-06',
			),
			array(
				'text'  => 'Gemini 2.0 Flash',
				'value' => 'gemini-2.0-flash',
			),
			array(
				'text'  => 'Gemini 2.0 Flash-Lite',
				'value' => 'gemini-2.0-flash-lite',
			),
			array(
				'text'  => 'Gemini 1.5 Flash',
				'value' => 'gemini-1.5-flash',
			),
			array(
				'text'  => 'Gemini 1.5 Flash-8B',
				'value' => 'gemini-1.5-flash-8b',
			),
			array(
				'text'  => 'Gemini 1.5 Pro',
				'value' => 'gemini-1.5-pro',
			),
		);

		$model_field = array(
			'option_code'     => 'MODEL',
			'label'           => esc_attr_x( 'Model', 'Gemini', 'uncanny-automator' ),
			'input_type'      => 'select',
			'required'        => true,
			'options'         => $model_options,
			'options_show_id' => false,
			'description'     => esc_html_x( 'Select the Gemini model to use. Gemini 1.5 Pro has the largest context window (1 million tokens).', 'Gemini', 'uncanny-automator' ),
			'relevant_tokens' => array(),
		);

		$temperature_field = array(
			'option_code'     => 'TEMPERATURE',
			'label'           => esc_attr_x( 'Temperature', 'Gemini', 'uncanny-automator' ),
			'input_type'      => 'text',
			'placeholder'     => '0.7',
			'description'     => esc_html_x( 'Sampling temperature (0-1). Higher = more random.', 'Gemini', 'uncanny-automator' ),
			'relevant_tokens' => array(),
		);

		$max_tokens_field = array(
			'option_code'     => 'MAX_TOKENS',
			'label'           => esc_attr_x( 'Maximum output tokens', 'Gemini', 'uncanny-automator' ),
			'input_type'      => 'text',
			'placeholder'     => '2048',
			'description'     => esc_html_x( 'Maximum number of tokens to generate.', 'Gemini', 'uncanny-automator' ),
			'relevant_tokens' => array(),
		);

		$system_message_field = array(
			'option_code'     => 'SYSTEM_CONTENT',
			'label'           => esc_attr_x( 'System instructions', 'Gemini', 'uncanny-automator' ),
			'description'     => esc_attr_x( 'Add context or instructions to have Gemini respond with those details in mind.', 'Gemini', 'uncanny-automator' ),
			'input_type'      => 'textarea',
			'required'        => false,
			'relevant_tokens' => array(),
		);

		$prompt_field = array(
			'option_code'       => $this->get_action_meta(),
			'label'             => esc_attr_x( 'Prompt', 'Gemini', 'uncanny-automator' ),
			'input_type'        => 'textarea',
			'supports_markdown' => true,
			'required'          => true,
			'relevant_tokens'   => array(),
		);

		return array(
			$model_field,
			$temperature_field,
			$max_tokens_field,
			$system_message_field,
			$prompt_field,
		);
	}
	/**
	 * Process action.
	 *
	 * @param mixed $user_id The user ID.
	 * @param mixed $action_data The data.
	 * @param mixed $recipe_id The ID.
	 * @param mixed $args The arguments.
	 * @param mixed $parsed The parsed.
	 * @return mixed
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		$temperature    = $this->get_parsed_meta_value( 'TEMPERATURE', '0.7' );
		$max_tokens     = $this->get_parsed_meta_value( 'MAX_TOKENS', '2048' );
		$model          = $this->get_parsed_meta_value( 'MODEL', 'gemini-2.0-flash' );
		$system_content = $this->get_parsed_meta_value( 'SYSTEM_CONTENT', '' );
		$prompt         = $this->get_parsed_meta_value( $this->get_action_meta(), '' );

		if ( empty( $max_tokens ) ) {
			$max_tokens = 2048;
		}

		if ( empty( $temperature ) ) {
			$temperature = 0.7;
		}

		$provider        = $this->get_provider( 'GEMINI' );
		$payload_builder = $this->get_payload_builder( $provider );

		// Build system_content and user_content as single objects, not a nested array
		$system_content_obj = $this->build_gemini_system_content( $system_content );
		$user_content_arr   = $this->build_gemini_user_content( $prompt );

		$payload = $payload_builder
			->endpoint( 'https://generativelanguage.googleapis.com/v1beta/models/' . $model . ':generateContent' )
			->body( 'system_instruction', $system_content_obj )
			->body( 'contents', $user_content_arr )
			->body(
				'generationConfig',
				array(
					'temperature'     => (float) $temperature,
					'maxOutputTokens' => (int) $max_tokens,
				)
			)
			->json_content()
			->build();

		$response    = $provider->send_request( $payload );
		$ai_response = $provider->parse_response( $response );

		$this->hydrate_tokens_from_response( $ai_response );

		return true;
	}

	/**
	 * Hydrate action tokens from AI response.
	 *
	 * @param Response $response
	 *
	 * @throws \Exception
	 *
	 * @return $this
	 */
	protected function hydrate_tokens_from_response( Response $response ) {

		// Just get the raw response and format it. Provider stays lean, action becomes fat.
		$raw_response   = $response->get_raw();
		$usage_metadata = $raw_response['usageMetadata'] ?? array();

		$tokens = array(
			'RESPONSE'        => $response->get_content(),
			'PROMPT_TOKENS'   => $usage_metadata['promptTokenCount'] ?? 0,
			'RESPONSE_TOKENS' => $usage_metadata['candidatesTokenCount'] ?? 0,
			'TOTAL_TOKENS'    => $usage_metadata['totalTokenCount'] ?? 0,
		);

		$this->hydrate_tokens( $tokens );
	}

	/**
	 * Build content structure for Gemini API.
	 * Returns an array of one “content” object with a `parts` key.
	 *
	 * @param string $prompt
	 *
	 * @return array
	 */
	private function build_gemini_user_content( $prompt ) {

		$user_content = array(
			'parts' => array(
				array( 'text' => $prompt ),
			),
		);

		// Contents must be an array of objects.
		return array( $user_content );
	}

	/**
	 * Build system content structure for Gemini API.
	 * Returns a single associative array with a `parts` key.
	 *
	 * @param string $system_content
	 *
	 * @return array
	 */
	private function build_gemini_system_content( $system_content ) {

		return array(
			'parts' => array(
				array( 'text' => $system_content ),
			),
		);
	}
}
