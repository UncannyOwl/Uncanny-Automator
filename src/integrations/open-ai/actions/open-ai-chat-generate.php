<?php
namespace Uncanny_Automator\Integrations\OpenAI\Actions;

use Uncanny_Automator\Core\Lib\AI\Core\Traits\Base_AI_Provider_Trait;
use Uncanny_Automator\Core\Lib\AI\Core\Traits\Base_Payload_Message_Array_Builder_Trait;
use Uncanny_Automator\Core\Lib\AI\Http\Response;
use Uncanny_Automator\Recipe\Action;

/**
 * Class OpenAI_Chat_Generate
 *
 * A handler class for wrapping chat generate action.
 *
 * @since 4.10
 *
 * @since 5.6 - Migrated to v3.
 *
 * @package Uncanny_Automator
 */
class OpenAI_Chat_Generate extends Action {

	use Base_Payload_Message_Array_Builder_Trait;
	use Base_AI_Provider_Trait;

	/**
	 * Setup Action.
	 *
	 * @return void.
	 */
	protected function setup_action() {

		$this->set_integration( 'OPEN_AI' );
		$this->set_action_code( 'OPEN_AI_CHAT_GENERATE' );
		$this->set_action_meta( 'OPEN_AI_CHAT_GENERATE_META' );
		$this->set_is_pro( false );
		$this->set_support_link(
			Automator()->get_author_support_link(
				$this->get_action_code(),
				'knowledge-base/open-ai/'
			)
		);
		$this->set_requires_user( false );

		$this->set_sentence(
			sprintf(
				/* translators: 1: Prompt, 2: Instructions, 3: Model */
				esc_html_x(
					'Use {{a prompt:%1$s}} to generate text with the GPT model',
					'OpenAI',
					'uncanny-automator'
				),
				$this->get_action_meta()
			)
		);

		$this->set_readable_sentence(
			esc_html_x(
				'Use {{a prompt}} to generate text with the GPT model',
				'OpenAI',
				'uncanny-automator'
			)
		);

		$this->set_wpautop( false );
		$this->set_background_processing( false );

		$this->set_action_tokens(
			array(
				'RESPONSE'                => array(
					'name' => esc_html_x( 'Response', 'Open Ai', 'uncanny-automator' ),
					'type' => 'text',
				),
				'USAGE_PROMPT_TOKENS'     => array(
					'name' => esc_html_x( 'Prompt tokens usage', 'Open Ai', 'uncanny-automator' ),
				),
				'USAGE_COMPLETION_TOKENS' => array(
					'name' => esc_html_x( 'Completion tokens usage', 'Open Ai', 'uncanny-automator' ),
				),
				'USAGE_TOTAL_TOKENS'      => array(
					'name' => esc_html_x( 'Total tokens usage', 'Open Ai', 'uncanny-automator' ),
				),
			),
			$this->get_action_code()
		);
	}

	/**
	 * Loads options.
	 *
	 * @return array The list of option fields.
	 */
	public function options() {

		$description_max_len = wp_kses_post(
			sprintf(
				/* translators: 1: Learn more about tokens, 2: Learn more about tokens */
				esc_html_x(
					'The maximum number of tokens allowed for the prompt and response. %1$sLearn more about tokens%2$s.',
					'OpenAI',
					'uncanny-automator'
				),
				'<a href="https://platform.openai.com/docs/api-reference/chat/create#chat/create-max_tokens" target="_blank">',
				'</a>'
			)
		);

		$description_models = wp_kses_post(
			sprintf(
				/* translators: 1: Learn more about GPT models, 2: Learn more about GPT models */
				esc_html_x(
					'Only GPT models that your account has access to are listed. %1$sLearn more about GPT models%2$s.',
					'OpenAI',
					'uncanny-automator'
				),
				'<a href="https://platform.openai.com/docs/models/overview" target="_blank">',
				'</a>'
			)
		);

		$model = array(
			'option_code'     => 'MODEL',
			'label'           => esc_attr_x( 'Model', 'Open Ai', 'uncanny-automator' ),
			'description'     => $description_models,
			'input_type'      => 'select',
			'required'        => true,
			'options'         => array(),
			'options_show_id' => false,
			'ajax'            => array(
				'endpoint' => 'automator_openai_get_gpt_models',
				'event'    => 'on_load',
			),
		);

		$temperature = array(
			'option_code' => 'TEMPERATURE',
			'label'       => esc_attr_x( 'Temperature', 'Open Ai', 'uncanny-automator' ),
			'input_type'  => 'text',
			'placeholder' => '1',
			'description' => esc_html_x( 'What sampling temperature to use, between 0 and 2. Higher values like 0.8 will make the output more random, while lower values like 0.2 will make it more focused and deterministic.', 'Open AI', 'uncanny-automator' ),
		);

		$max_len = array(
			'option_code' => 'MAX_LEN',
			'label'       => esc_attr_x( 'Maximum length', 'Open Ai', 'uncanny-automator' ),
			'description' => $description_max_len,
			'input_type'  => 'text',
		);

		$system_content = array(
			'option_code' => 'SYSTEM_CONTENT',
			'label'       => esc_attr_x( 'System message', 'Open Ai', 'uncanny-automator' ),
			'description' => esc_attr_x( 'Add context or instructions to have GPT respond with those details in mind.', 'Open Ai', 'uncanny-automator' ),
			'input_type'  => 'textarea',
			'required'    => false,
		);

		$prompt = array(
			'option_code'       => $this->get_action_meta(),
			'label'             => esc_attr_x( 'Prompt', 'Open Ai', 'uncanny-automator' ),
			'input_type'        => 'textarea',
			'supports_markdown' => true,
			'required'          => true,
		);

		return array(
			$model,
			$temperature,
			$max_len,
			$system_content,
			$prompt,
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
	 * @throws \Exception
	 *
	 * @return void.
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		$temperature = $this->get_parsed_meta_value( 'TEMPERATURE', 1 );
		$max_tokens  = $this->get_parsed_meta_value( 'MAX_LEN', 2048 );
		$model       = $this->get_parsed_meta_value( 'MODEL', 'gpt-3.5-turbo' );

		// If the temperature is not set, set it to 1.
		if ( empty( $temperature ) ) {
			$temperature = 1;
		}

		// If the max tokens is not set, set it to 2048.
		if ( empty( $max_tokens ) ) {
			$max_tokens = 2048;
		}

		// Migration for 3.5-turbo-0301 to 3.5-turbo.
		$model          = $this->handle_model( $model, $action_data );
		$system_content = $this->get_parsed_meta_value( 'SYSTEM_CONTENT' ) ?? '';
		$prompt         = $this->get_parsed_meta_value( $this->get_action_meta() ) ?? '';

		/** @var \Uncanny_Automator\Core\Lib\AI\Provider\OpenAI_Provider $provider */
		$provider        = $this->get_provider( 'OPENAI' );
		$payload_builder = $this->get_payload_builder( $provider );

		$messages = $this->create_simple_message( $system_content, $prompt );

		$payload = $payload_builder->model( $model )
			->endpoint( 'https://api.openai.com/v1/chat/completions' )
			->temperature( $temperature )
			->max_completion_tokens( $max_tokens )
			->messages( $messages )
			->json_content()
			->build();

		$response    = $provider->send_request( $payload );
		$ai_response = $provider->parse_response( $response );

		$this->hydrate_tokens_from_response( $ai_response );

		return true;
	}

	/**
	 * Hydrates this specific action tokens.
	 *
	 * @param Response $ai_response.
	 *
	 * @return self
	 */
	private function hydrate_tokens_from_response( $ai_response ) {

		if ( ! $ai_response instanceof Response ) {
			throw new \Exception( 'Invalid AI response', 400 );
		}

		$response_text = $ai_response->get_content();

		$this->hydrate_tokens(
			array(
				'RESPONSE'                => $response_text,
				'USAGE_PROMPT_TOKENS'     => $ai_response->get_meta_data()['prompt_tokens'] ?? 0,
				'USAGE_COMPLETION_TOKENS' => $ai_response->get_meta_data()['completion_tokens'] ?? 0,
				'USAGE_TOTAL_TOKENS'      => $ai_response->get_meta_data()['total_tokens'] ?? 0,
			)
		);

		return $this;
	}

	/**
	 * Handles the model value before sending to OpenAI.
	 *
	 * @param string $model
	 * @return string
	 */
	private function handle_model( $model, $action_data ) {

		$model_new_values = array(
			'gpt-3.5-turbo-0301' => 'gpt-3.5-turbo', // Move all 3.5 turbo 0301 requests to turbo.
		);

		// Migrate the model.
		$this->migrate_model( $model_new_values, $action_data );

		// But run with migrated value.
		return $model_new_values[ $model ] ?? $model;
	}

	/**
	 * Do a little natural field value migration.
	 *
	 * @param string[] $model_new_values
	 * @param mixed[] $action_data
	 *
	 * @since 5.9
	 *
	 * @return int|bool - The number of rows updated, otherwise, bool.
	 */
	private function migrate_model( $model_new_values, $action_data ) {

		$action_id   = $action_data['ID'] ?? 0;
		$model_saved = $action_data['meta']['MODEL'] ?? null;

		if ( empty( $action_id ) || empty( $model_saved ) ) {
			return false;
		}

		if ( isset( $model_new_values[ $model_saved ] ) ) {
			return update_post_meta( $action_id, 'MODEL', $model_new_values[ $model_saved ] );
		}

		return false;
	}
}
