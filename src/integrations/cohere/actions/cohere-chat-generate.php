<?php
namespace Uncanny_Automator\Integrations\Cohere\Actions;

use Uncanny_Automator\Recipe\App_Action;
use Uncanny_Automator\Core\Lib\AI\Http\Payload;
use Uncanny_Automator\Core\Lib\AI\Provider\Cohere_Provider;

// Traits
use Uncanny_Automator\Core\Lib\AI\Core\Traits\Base_AI_Provider_Trait;
use Uncanny_Automator\Core\Lib\AI\Core\Traits\Base_Payload_Message_Array_Builder_Trait;

/**
 * Cohere chat generation action.
 *
 * Provides AI text generation capabilities using Cohere's Command models
 * through the Uncanny Automator AI framework.
 *
 * @package Uncanny_Automator\Integrations\Cohere\Actions
 * @since 5.6
 */
class Cohere_Chat_Generate extends App_Action {

	use Base_AI_Provider_Trait;
	use Base_Payload_Message_Array_Builder_Trait;

	/**
	 * Setup action configuration.
	 *
	 * @return void
	 */
	protected function setup_action() {

		$this->set_integration( 'COHERE' );
		$this->set_action_code( 'COHERE_CHAT_GENERATE' );
		$this->set_action_meta( 'COHERE_CHAT_PROMPT' );
		$this->set_support_link( Automator()->get_author_support_link( $this->get_action_code(), 'knowledge-base/cohere/' ) );
		$this->set_requires_user( false );

		$this->set_sentence(
			sprintf(
				/* translators: 1: Prompt field placeholder, 2: Model field placeholder */
				esc_html_x(
					'Use {{a prompt:%1$s}} to generate a text response with {{a Cohere model:%2$s}}',
					'Cohere',
					'uncanny-automator'
				),
				'PROMPT:' . $this->get_action_meta(),
				'MODEL:' . $this->get_action_meta()
			)
		);

		$this->set_readable_sentence(
			esc_html_x(
				'Use {{a prompt}} to generate a text response with {{a Cohere model}}',
				'Human-readable sentence for Cohere AI response generation action',
				'uncanny-automator'
			)
		);

		$this->set_options_callback( array( $this, 'options' ) );

		$this->set_action_tokens(
			array(
				'RESPONSE_TEXT'        => array(
					'name' => esc_attr_x( 'Response text', 'Token name for AI-generated response text', 'uncanny-automator' ),
					'type' => 'text',
				),
				'API_VERSION'          => array(
					'name' => esc_attr_x( 'API version', 'Token name for API version used', 'uncanny-automator' ),
					'type' => 'text',
				),
				'BILLED_INPUT_TOKENS'  => array(
					'name' => esc_attr_x( 'Billed input tokens', 'Token name for billed input token count', 'uncanny-automator' ),
					'type' => 'int',
				),
				'BILLED_OUTPUT_TOKENS' => array(
					'name' => esc_attr_x( 'Billed output tokens', 'Token name for billed output token count', 'uncanny-automator' ),
					'type' => 'int',
				),
				'INPUT_TOKENS'         => array(
					'name' => esc_attr_x( 'Input tokens', 'Token name for input token count', 'uncanny-automator' ),
					'type' => 'int',
				),
				'OUTPUT_TOKENS'        => array(
					'name' => esc_attr_x( 'Output tokens', 'Token name for output token count', 'uncanny-automator' ),
					'type' => 'int',
				),
			),
			$this->action_code
		);
	}

	/**
	 * Define action option fields.
	 *
	 * @return array Action field configuration array
	 */
	public function options() {
		// Model options
		$model_options = array(
			array(
				'text'  => 'Command A (command-a-03-2025)',
				'value' => 'command-a-03-2025',
			),
			array(
				'text'  => 'Command R7B (command-r7b-12-2024)',
				'value' => 'command-r7b-12-2024',
			),
			array(
				'text'  => 'Command R+ (command-r-plus-08-2024)',
				'value' => 'command-r-plus-08-2024',
			),
			array(
				'text'  => 'Command R+ (command-r-plus-04-2024)',
				'value' => 'command-r-plus-04-2024',
			),
			array(
				'text'  => 'Command R+ (command-r-plus)',
				'value' => 'command-r-plus',
			),
			array(
				'text'  => 'Command R (command-r-08-2024)',
				'value' => 'command-r-08-2024',
			),
			array(
				'text'  => 'Command R (command-r-03-2024)',
				'value' => 'command-r-03-2024',
			),
			array(
				'text'  => 'Command (command)',
				'value' => 'command',
			),
			array(
				'text'  => 'Command (light)',
				'value' => 'command-light',
			),
			array(
				'text'  => 'Command (nightly)',
				'value' => 'command-nightly',
			),
			array(
				'text'  => 'Command (light-nightly)',
				'value' => 'command-light-nightly',
			),
		);

		$safety_options = array(
			array(
				'text'  => esc_html_x( 'Contextual (default)', 'Safety mode option for contextual filtering', 'uncanny-automator' ),
				'value' => 'CONTEXTUAL',
			),
			array(
				'text'  => esc_html_x( 'Strict', 'Safety mode option for strict filtering', 'uncanny-automator' ),
				'value' => 'STRICT',
			),
			array(
				'text'  => esc_html_x( 'Off', 'Safety mode option for disabled filtering', 'uncanny-automator' ),
				'value' => 'off',
			),
		);

		// Model selection field
		$model_field = array(
			'option_code'     => 'MODEL',
			'label'           => esc_attr_x( 'Model', 'Label for model selection field', 'uncanny-automator' ),
			'input_type'      => 'select',
			'required'        => true,
			'options'         => $model_options,
			'options_show_id' => false,
			'description'     => esc_html_x( 'The Command models are AI text-generators you can generate a text with. They can pull in info from tokens, translate text, draft copy, and more.', 'Description for model selection field', 'uncanny-automator' ),
			'relevant_tokens' => array(),
		);

		// Safety field.
		$safety_field = array(
			'option_code'     => 'COHERE_SAFETY',
			'label'           => esc_attr_x( 'Safety mode', 'Label for safety mode field', 'uncanny-automator' ),
			'input_type'      => 'select',
			'required'        => true,
			'options'         => $safety_options,
			'options_show_id' => false,
			'description'     => esc_html_x( 'Used to select the safety instruction inserted into the prompt. Defaults to CONTEXTUAL. When OFF is specified, the safety instruction will be omitted. This parameter is only compatible newer Cohere models, starting with Command R 08-2024 and Command R+ 08-2024.', 'Description for safety mode field', 'uncanny-automator' ),
			'relevant_tokens' => array(),
		);

		// Temperature field
		$temperature_field = array(
			'option_code'     => 'TEMPERATURE',
			'label'           => esc_attr_x( 'Temperature', 'Label for temperature field', 'uncanny-automator' ),
			'input_type'      => 'text',
			'placeholder'     => '0.3',
			'default'         => '0.3',
			'description'     => esc_html_x( 'Defaults to 0.3. A non-negative float controlling randomness: lower values yield more predictable output, higher values yield more varied output.', 'Description for temperature field', 'uncanny-automator' ),
			'relevant_tokens' => array(),
		);

		// Max tokens field
		$max_tokens_field = array(
			'option_code'     => 'MAX_TOKENS',
			'label'           => esc_attr_x( 'Maximum tokens', 'Label for maximum tokens field', 'uncanny-automator' ),
			'input_type'      => 'text',
			'placeholder'     => '2048',
			// phpcs:ignore Uncanny_Automator.Strings.SentenceCase.PotentialCaseIssue
			'description'     => esc_html_x( 'The maximum number of tokens the model will generate as part of the response. Note: Setting a low value may result in incomplete generations.', 'Description for maximum tokens field', 'uncanny-automator' ),
			'relevant_tokens' => array(),
		);

		// System message field
		$system_message_field = array(
			'option_code'     => 'SYSTEM_CONTENT',
			'label'           => esc_attr_x( 'System instructions', 'Label for system instructions field', 'uncanny-automator' ),
			'description'     => esc_attr_x( 'Add context or instructions to have Cohere respond with those details in mind.', 'Description for system instructions field', 'uncanny-automator' ),
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
			$safety_field,
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
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		// Extract parameters from parsed data.
		$model          = $this->get_parsed_meta_value( 'MODEL', '' );
		$safety         = $this->get_parsed_meta_value( 'COHERE_SAFETY', 'CONTEXTUAL' );
		$temperature    = $this->get_parsed_meta_value( 'TEMPERATURE', 0.3 );
		$max_tokens     = $this->get_parsed_meta_value( 'MAX_TOKENS', 2048 );
		$system_content = $this->get_parsed_meta_value( 'SYSTEM_CONTENT', '' );
		$prompt         = $this->get_parsed_meta_value( $this->get_action_meta(), '' );

			// Add sane default incase, the user removed the value by accident.
		if ( empty( $max_tokens ) ) {
			$max_tokens = 2048;
		}

		// Use the traits to get the provider and payload builder.
		/** @var Cohere_Provider $provider */
		$provider = $this->get_provider( 'COHERE' );
		/** @var Payload $payload_builder */
		$payload_builder = $this->get_payload_builder( $provider );

		// Use the trait to build the message array.
		$messages = $this->create_simple_message( $system_content, $prompt );

		// Build the payload
		$payload = $payload_builder
			->endpoint( 'https://api.cohere.ai/v1/chat' )
			->body( 'model', $model )
			->body( 'safety_mode', $safety )
			->body( 'message', $prompt )
			->body( 'temperature', (float) $temperature )
			->body( 'max_tokens', (int) $max_tokens )
			->messages( $messages )
			->json_content();

		// Build the final payload.
		$payload = $payload->build();

		// Send the request
		$response    = $provider->send_request( $payload );
		$ai_response = $provider->parse_response( $response );
		$meta_data   = $ai_response->get_meta_data();

		$this->hydrate_tokens(
			array(
				'RESPONSE_TEXT'        => $ai_response->get_content(),
				'API_VERSION'          => $meta_data['api_version']['version'] ?? '',
				'BILLED_INPUT_TOKENS'  => $meta_data['billed_units']['input_tokens'] ?? '',
				'BILLED_OUTPUT_TOKENS' => $meta_data['billed_units']['output_tokens'] ?? '',
				'INPUT_TOKENS'         => $meta_data['tokens']['input_tokens'] ?? '',
				'OUTPUT_TOKENS'        => $meta_data['tokens']['output_tokens'] ?? '',
			)
		);

		return true;
	}
}
