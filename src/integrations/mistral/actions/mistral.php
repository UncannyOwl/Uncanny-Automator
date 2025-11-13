<?php

declare(strict_types=1);

namespace Uncanny_Automator\Integrations\Mistral\Actions;

use Uncanny_Automator\Recipe\App_Action;
use Uncanny_Automator\Core\Lib\AI\Core\Traits\Base_AI_Provider_Trait;
use Uncanny_Automator\Core\Lib\AI\Core\Traits\Base_Payload_Message_Array_Builder_Trait;
use Uncanny_Automator\Core\Lib\AI\Http\Response;

/**
 * Mistral AI chat generation action.
 *
 * Provides AI text generation capabilities using Mistral's open-source models
 * through the Uncanny Automator AI framework.
 *
 * @package Uncanny_Automator\Integrations\Mistral\Actions
 * @since 5.6
 */
class Mistral_Chat_Generate extends App_Action {

	use Base_AI_Provider_Trait;
	use Base_Payload_Message_Array_Builder_Trait;

	/**
	 * Setup action configuration.
	 *
	 * @return void
	 */
	protected function setup_action() {

		$this->set_integration( 'MISTRAL' );
		$this->set_action_code( 'MISTRAL_CHAT_GENERATE' );
		$this->set_action_meta( 'MISTRAL_CHAT_PROMPT' );
		$this->set_support_link( 'https://automatorplugin.com/knowledge-base/mistral/' );
		$this->set_requires_user( false );

		$this->set_sentence(
			sprintf(
				/* translators: 1: Prompt field placeholder, 2: Model field placeholder */
				esc_html_x(
					'Use {{a prompt:%1$s}} to generate a text response with {{a Le Chat model:%2$s}}',
					'Mistral',
					'uncanny-automator'
				),
				'PROMPT:' . $this->get_action_meta(),
				'MODEL:' . $this->get_action_meta()
			)
		);

		$this->set_readable_sentence(
			esc_html_x(
				'Use {{a prompt}} to generate a text response with {{a Le Chat model}}',
				'Human-readable sentence for Mistral AI response generation action',
				'uncanny-automator'
			)
		);

		$this->set_options_callback( array( $this, 'options' ) );

		$this->set_action_tokens(
			array(
				'RESPONSE_TEXT'   => array(
					'name' => esc_attr_x( 'Response text', 'Token name for AI-generated response text', 'uncanny-automator' ),
					'type' => 'text',
				),
				'MODEL'           => array(
					'name' => esc_attr_x( 'Model', 'Token name for AI model used', 'uncanny-automator' ),
					'type' => 'text',
				),
				'FINISH_REASON'   => array(
					'name' => esc_attr_x( 'Finish reason', 'Token name for completion finish reason', 'uncanny-automator' ),
					'type' => 'text',
				),
				'PROMPT_TOKENS'   => array(
					'name' => esc_attr_x( 'Prompt tokens', 'Token name for prompt token count', 'uncanny-automator' ),
					'type' => 'int',
				),
				'RESPONSE_TOKENS' => array(
					'name' => esc_attr_x( 'Response tokens', 'Token name for response token count', 'uncanny-automator' ),
					'type' => 'int',
				),
				'TOTAL_TOKENS'    => array(
					'name' => esc_attr_x( 'Total tokens', 'Token name for total token count', 'uncanny-automator' ),
					'type' => 'int',
				),
			),
			$this->action_code
		);
	}

	/**
	 * Define action option fields.
	 *
	 * @return array
	 */
	public function options() {
		// Model options
		$model_options = array(
			array(
				'text'  => 'Mistral Medium',
				'value' => 'mistral-medium-2505',
			),
			array(
				'text'  => 'Codestral',
				'value' => 'codestral-2501',
			),
			array(
				'text'  => 'Mistral OCR',
				'value' => 'mistral-ocr-2505',
			),
			array(
				'text'  => 'Mistral Saba',
				'value' => 'mistral-saba-2502',
			),
			array(
				'text'  => 'Mistral Large',
				'value' => 'mistral-large-2411',
			),
			array(
				'text'  => 'Ministral 3B',
				'value' => 'ministral-3b-2410',
			),
			array(
				'text'  => 'Ministral 8B',
				'value' => 'ministral-8b-2410',
			),
			array(
				'text'  => 'Mistral Moderation',
				'value' => 'mistral-moderation-2411',
			),
			array(
				'text'  => 'Devstral Small (Free model)',
				'value' => 'devstral-small-2505',
			),
			array(
				'text'  => 'Mistral Small (Free model)',
				'value' => 'mistral-small-2503',
			),
			array(
				'text'  => 'Mistral Nemo (Free model)',
				'value' => 'open-mistral-nemo',
			),
		);

		// Model selection field.
		$model_field = array(
			'option_code'     => 'MODEL',
			'label'           => esc_attr_x( 'Model', 'Label for model selection field', 'uncanny-automator' ),
			'input_type'      => 'select',
			'required'        => true,
			'options'         => $model_options,
			'options_show_id' => false,
			'description'     => esc_html_x( 'Select the Mistral model to use. Mistral 8x22B is more capable but uses more tokens.', 'Description for model selection field', 'uncanny-automator' ),
			'relevant_tokens' => array(),
		);

		// Temperature field
		$temperature_field = array(
			'option_code'     => 'TEMPERATURE',
			'label'           => esc_attr_x( 'Temperature', 'Label for temperature field', 'uncanny-automator' ),
			'input_type'      => 'text',
			'placeholder'     => '0.7',
			'description'     => esc_html_x( 'Sampling temperature (0-1). Higher = more random.', 'Description for temperature field', 'uncanny-automator' ),
			'relevant_tokens' => array(),
		);

		// Max tokens field
		$max_tokens_field = array(
			'option_code'     => 'MAX_TOKENS',
			'label'           => esc_attr_x( 'Maximum tokens', 'Label for maximum tokens field', 'uncanny-automator' ),
			'input_type'      => 'text',
			'placeholder'     => '2048',
			'description'     => esc_html_x( 'Maximum number of tokens to generate.', 'Description for maximum tokens field', 'uncanny-automator' ),
			'relevant_tokens' => array(),
		);

		// System message field
		$system_message_field = array(
			'option_code'     => 'SYSTEM_CONTENT',
			'label'           => esc_attr_x( 'System instructions', 'Label for system instructions field', 'uncanny-automator' ),
			'description'     => esc_attr_x( 'Add context or instructions to have Mistral respond with those details in mind.', 'Description for system instructions field', 'uncanny-automator' ),
			'input_type'      => 'textarea',
			'required'        => false,
			'relevant_tokens' => array(),
		);

		// Prompt field
		$prompt_field = array(
			'option_code'       => $this->get_action_meta(),
			'label'             => esc_attr_x( 'Prompt', 'Label for prompt field', 'uncanny-automator' ),
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
	 * Process the action execution.
	 *
	 * @param int   $user_id     User ID executing the action
	 * @param array $action_data Action configuration data
	 * @param int   $recipe_id   Recipe ID containing this action
	 * @param array $args        Additional arguments
	 * @param array $parsed      Parsed field values
	 *
	 * @return bool True on success, false on failure
	 *
	 * @throws \Exception If action execution fails
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		try {
			// Extract parameters using get_parsed_meta_value like Claude action
			$temperature    = $this->get_parsed_meta_value( 'TEMPERATURE', '0.7' );
			$max_tokens     = $this->get_parsed_meta_value( 'MAX_TOKENS', '2048' );
			$model          = $this->get_parsed_meta_value( 'MODEL', 'mixtral-8x7b-instruct-v0.1' );
			$system_content = $this->get_parsed_meta_value( 'SYSTEM_CONTENT', '' );
			$prompt         = $this->get_parsed_meta_value( $this->get_action_meta(), '' );

			if ( empty( $temperature ) ) {
				$temperature = '0.7';
			}

			if ( empty( $max_tokens ) ) {
				$max_tokens = '2048';
			}

			// Use AI framework traits to get provider
			/** @var \Uncanny_Automator\Core\Lib\AI\Provider\Mistral_Provider $provider */
			$provider        = $this->get_provider( 'MISTRAL' );
			$payload_builder = $this->get_payload_builder( $provider );

			// Create message structure using trait helper
			$messages = $this->create_simple_message( $system_content, $prompt );

			// Build the payload
			$payload = $payload_builder
				->endpoint( 'https://api.mistral.ai/v1/chat/completions' )
				->body( 'model', $model )
				->body( 'messages', $messages )
				->body( 'temperature', (float) $temperature )
				->body( 'max_tokens', (int) $max_tokens )
				->json_content()
				->build();

			// Send the request
			$response    = $provider->send_request( $payload );
			$ai_response = $provider->parse_response( $response );
			$meta_data   = $ai_response->get_meta_data();

			// Set tokens
			$this->hydrate_tokens(
				array(
					'RESPONSE_TEXT'   => $ai_response->get_content(),
					'MODEL'           => $meta_data['model'] ?? '',
					'FINISH_REASON'   => $response['choices'][0]['finish_reason'] ?? '',
					'PROMPT_TOKENS'   => $meta_data['prompt_tokens'] ?? 0,
					'RESPONSE_TOKENS' => $meta_data['completion_tokens'] ?? 0,
					'TOTAL_TOKENS'    => $meta_data['total_tokens'] ?? 0,
				)
			);

			return true;

		} catch ( \Exception $e ) {
			$this->add_log_error( 'Mistral API error: ' . $e->getMessage() );
			return false;
		}
	}

	/**
	 * Hydrate action tokens from AI response.
	 *
	 * @param Response $ai_response AI response object
	 *
	 * @return $this Current action instance for method chaining
	 *
	 * @throws \Exception If AI response is invalid
	 */
	private function hydrate_tokens_from_response( Response $ai_response ) {

		if ( ! $ai_response instanceof Response ) {
			throw new \Exception( 'Invalid AI response', 400 );
		}

		$content  = $ai_response->get_content();
		$metadata = $ai_response->get_meta_data();

		$this->hydrate_tokens(
			array(
				'RESPONSE_TEXT'   => $content,
				'MODEL'           => $metadata['model'],
				'FINISH_REASON'   => $metadata['finish_reason'] ?? '',
				'PROMPT_TOKENS'   => $metadata['prompt_tokens'] ?? null,
				'RESPONSE_TOKENS' => $metadata['completion_tokens'] ?? null,
				'TOTAL_TOKENS'    => $metadata['total_tokens'] ?? null,
			)
		);

		return $this;
	}
}
