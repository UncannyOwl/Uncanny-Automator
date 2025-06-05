<?php
namespace Uncanny_Automator\Integrations\Deepseek\Actions;

use Uncanny_Automator\Recipe\Action;
use Uncanny_Automator\Core\Lib\AI\Core\Traits\Base_AI_Provider_Trait;
use Uncanny_Automator\Core\Lib\AI\Core\Traits\Base_Payload_Message_Array_Builder_Trait;
use Uncanny_Automator\Core\Lib\AI\Http\Response;

/**
 * DeepSeek chat generation action.
 *
 * Provides AI text generation capabilities using DeepSeek's reasoning models
 * through the Uncanny Automator AI framework.
 *
 * @package Uncanny_Automator\Integrations\Deepseek\Actions
 * @since 5.6
 */
class Deepseek_Chat_Generate extends Action {

	use Base_AI_Provider_Trait;
	use Base_Payload_Message_Array_Builder_Trait;

	/**
	 * Setup action configuration.
	 *
	 * @return void
	 */
	protected function setup_action() {

		$this->set_integration( 'DEEPSEEK' );
		$this->set_action_code( 'DEEPSEEK_CHAT_GENERATE' );
		$this->set_action_meta( 'DEEPSEEK_CHAT_GENERATE_META' );
		$this->set_is_pro( false );
		$this->set_requires_user( false );

		$this->set_sentence(
			sprintf(
				/* translators: 1: Prompt field placeholder, 2: Model field placeholder */
				esc_html_x(
					'Use {{a prompt:%1$s}} to generate a text response with {{a DeepSeek model:%2$s}}',
					'DeepSeek',
					'uncanny-automator'
				),
				'PROMPT:' . $this->get_action_meta(),
				'MODEL:' . $this->get_action_meta()
			)
		);

		$this->set_readable_sentence(
			esc_html_x(
				'Use {{a prompt}} to generate a text response with {{a DeepSeek model}}',
				'DeepSeek',
				'uncanny-automator'
			)
		);

		$this->set_wpautop( false );
		$this->set_background_processing( false );

		$x = array(
			'prompt_tokens'                              => $metadata['prompt_tokens'] ?? '',
			'completion_tokens'                          => $metadata['completion_tokens'] ?? '',
			'total_tokens'                               => $metadata['total_tokens'] ?? '',
			'prompt_tokens_details_cached_tokens'        => $metadata['prompt_tokens_details']['cached_tokens'] ?? '',
			'completion_tokens_details_reasoning_tokens' => $metadata['completion_tokens_details']['reasoning_tokens'] ?? '',
			'prompt_cache_hit_tokens'                    => $metadata['prompt_cache_hit_tokens'] ?? '',
			'prompt_cache_miss_tokens'                   => $metadata['prompt_cache_miss_tokens'] ?? '',
		);

		$this->set_action_tokens(
			array(
				'RESPONSE'                           => array(
					'name' => esc_attr_x( 'Response', 'DeepSeek', 'uncanny-automator' ),
					'type' => 'text',
				),
				'PROMPT_TOKENS'                      => array(
					'name' => esc_attr_x( 'Prompt tokens usage', 'DeepSeek', 'uncanny-automator' ),
				),
				'COMPLETION_TOKENS'                  => array(
					'name' => esc_attr_x( 'Completion tokens usage', 'DeepSeek', 'uncanny-automator' ),
				),
				'TOTAL_TOKENS'                       => array(
					'name' => esc_attr_x( 'Total tokens usage', 'DeepSeek', 'uncanny-automator' ),
				),
				'PROMPT_TOKEN_DETAILS_CACHED_TOKENS' => array(
					'name' => esc_attr_x( 'Prompt cache hit tokens', 'DeepSeek', 'uncanny-automator' ),
				),
				'COMPLETION_TOKEN_DETAILS_REASONING_TOKENS' => array(
					'name' => esc_attr_x( 'Completion reasoning tokens usage', 'DeepSeek', 'uncanny-automator' ),
				),
				'PROMPT_CACHE_HIT_TOKENS'            => array(
					'name' => esc_attr_x( 'Prompt cache hit tokens', 'DeepSeek', 'uncanny-automator' ),
				),
				'PROMPT_CACHE_MISS_TOKENS'           => array(
					'name' => esc_attr_x( 'Prompt cache miss tokens', 'DeepSeek', 'uncanny-automator' ),
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

		// phpcs:ignore Uncanny_Automator.Strings.SentenceCase.PotentialCaseIssue
		$description_max_tokens = esc_html_x( 'The maximum number of tokens to generate before stopping. Note that DeepSeek models may stop before reaching this maximum. This parameter only specifies the absolute maximum number of tokens to generate.', 'DeepSeek', 'uncanny-automator' );

		$description_temperature = esc_html_x( 'Amount of randomness injected into the response. Defaults to 1.0. Ranges from 0.0 to 1.0. Use temperature closer to 0.0 for analytical / multiple choice, and closer to 1.0 for creative and generative tasks.', 'DeepSeek', 'uncanny-automator' );

		$description_system = esc_html_x( 'Add context or instructions for DeepSeek to follow.', 'DeepSeek', 'uncanny-automator' );

		$models = array(
			array(
				'text'  => 'DeepSeek R1 (deepseek-r1)',
				'value' => 'deepseek-r1',
			),
			array(
				'text'  => 'DeepSeek Chat v3 (deepseek-chat)',
				'value' => 'deepseek-chat',
			),
			array(
				'text'  => 'DeepSeek Reasoner (deepseek-reasoner)',
				'value' => 'deepseek-reasoner',
			),
			array(
				'text'  => 'DeepSeek Coder v2.5 (deepseek-coder)',
				'value' => 'deepseek-coder',
			),
		);

		return array(
			array(
				'option_code'     => 'MODEL',
				'label'           => esc_attr_x( 'Model', 'DeepSeek', 'uncanny-automator' ),
				'input_type'      => 'select',
				'required'        => true,
				'options'         => $models,
				'options_show_id' => false,
				'relevant_tokens' => array(),
			),
			array(
				'option_code'     => 'TEMPERATURE',
				'label'           => esc_attr_x( 'Temperature', 'DeepSeek', 'uncanny-automator' ),
				'input_type'      => 'text',
				'placeholder'     => '1',
				'default'         => '1',
				'description'     => $description_temperature,
				'relevant_tokens' => array(),
			),
			array(
				'option_code'     => 'MAX_TOKENS',
				'label'           => esc_attr_x( 'Max tokens', 'DeepSeek', 'uncanny-automator' ),
				'input_type'      => 'text',
				'placeholder'     => '2048',
				'default'         => '2048',
				'description'     => $description_max_tokens,
				'relevant_tokens' => array(),
			),
			array(
				'option_code'     => 'SYSTEM_CONTENT',
				'label'           => esc_attr_x( 'System message', 'DeepSeek', 'uncanny-automator' ),
				'input_type'      => 'textarea',
				'description'     => $description_system,
				'required'        => false,
				'relevant_tokens' => array(),
			),
			array(
				'option_code'       => $this->get_action_meta(),
				'label'             => esc_attr_x( 'Prompt', 'DeepSeek', 'uncanny-automator' ),
				'input_type'        => 'textarea',
				'supports_markdown' => true,
				'required'          => true,
				'relevant_tokens'   => array(),
			),
		);
	}

	/**
	 * Process the action execution.
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

		$temperature    = $this->get_parsed_meta_value( 'TEMPERATURE', 1 );
		$max_tokens     = $this->get_parsed_meta_value( 'MAX_TOKENS', 2048 );
		$model          = $this->get_parsed_meta_value( 'MODEL', '' );
		$system_content = $this->get_parsed_meta_value( 'SYSTEM_CONTENT', '' );
		$prompt         = $this->get_parsed_meta_value( $this->get_action_meta(), '' );

		if ( empty( $max_tokens ) ) {
			$max_tokens = 2048;
		}
		if ( empty( $temperature ) ) {
			$temperature = 1;
		}

		$provider        = $this->get_provider( 'DEEPSEEK' );
		$payload_builder = $this->get_payload_builder( $provider );

		$messages = $this->create_simple_message( $system_content, $prompt );

		$payload = $payload_builder
			->endpoint( 'https://api.deepseek.com/v1/chat/completions' )
			->model( $model )
			->temperature( (float) $temperature )
			->max_tokens( (int) $max_tokens )
			->messages( $messages )
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
	 * @param Response $ai_response
	 *
	 * @return $this
	 * @throws \Exception
	 */
	private function hydrate_tokens_from_response( Response $ai_response ) {

		if ( ! $ai_response instanceof Response ) {
			throw new \Exception( 'Invalid AI response', 400 );
		}

		$content  = $ai_response->get_content();
		$metadata = $ai_response->get_meta_data();

		$this->hydrate_tokens(
			array(
				'RESPONSE'                           => $content,
				'PROMPT_TOKENS'                      => $metadata['prompt_tokens'],
				'COMPLETION_TOKENS'                  => $metadata['completion_tokens'],
				'TOTAL_TOKENS'                       => $metadata['total_tokens'],
				'PROMPT_TOKEN_DETAILS_CACHED_TOKENS' => $metadata['prompt_tokens_details_cached_tokens'],
				'COMPLETION_TOKEN_DETAILS_REASONING_TOKENS' => $metadata['completion_tokens_details_reasoning_tokens'],
				'PROMPT_CACHE_HIT_TOKENS'            => $metadata['prompt_cache_hit_tokens'],
				'PROMPT_CACHE_MISS_TOKENS'           => $metadata['prompt_cache_miss_tokens'],
			)
		);

		return $this;
	}
}
