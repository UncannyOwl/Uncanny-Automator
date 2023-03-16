<?php
namespace Uncanny_Automator;

/**
 * Class OPEN_AI_CHAT_GENERATE
 *
 * A handler class for wrapping chat generate action.
 *
 * @since 4.10
 * @package Uncanny_Automator
 */
class OPEN_AI_CHAT_GENERATE {

	use Recipe\Actions;

	use Recipe\Action_Tokens;

	public function __construct() {

		$this->setup_action();

	}

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

		$this->set_support_link( Automator()->get_author_support_link( $this->get_action_code(), 'knowledge-base/open-ai/' ) );

		$this->set_requires_user( false );

		$this->set_sentence(
			sprintf(
				/* translators: Action sentence */
				esc_attr__( 'Use {{a prompt:%1$s}} to generate text with the GPT model', 'uncanny-automator' ),
				$this->get_action_meta()
			)
		);

		/* translators: Action sentence */
		$this->set_readable_sentence( esc_attr__( 'Use {{a prompt}} to generate text with the GPT model', 'uncanny-automator' ) );

		$this->set_options_callback( array( $this, 'load_options' ) );

		$this->set_wpautop( false );

		$this->set_background_processing( false );

		$this->set_action_tokens(
			array(
				'RESPONSE'                => array(
					'name' => __( 'Response', 'uncanny-automator' ),
					'type' => 'text',
				),
				'USAGE_PROMPT_TOKENS'     => array(
					'name' => __( 'Prompt tokens usage', 'uncanny-automator' ),
				),
				'USAGE_COMPLETION_TOKENS' => array(
					'name' => __( 'Completion tokens usage', 'uncanny-automator' ),
				),
				'USAGE_TOTAL_TOKENS'      => array(
					'name' => __( 'Total tokens usage', 'uncanny-automator' ),
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

		$description = wp_kses_post(
			sprintf(
				/* translators: Action field description */
				__(
					'The maximum number of tokens allowed for the generated answer. By default, the number of tokens the model can return will be (4096 - prompt tokens). %1$sLearn more about tokens%2$s.',
					'uncanny-automator'
				),
				'<a href="https://platform.openai.com/docs/api-reference/chat/create#chat/create-max_tokens" target="_blank">',
				'</a>'
			)
		);

		return Automator()->utilities->keep_order_of_options(
			array(
				'options_group' => array(
					$this->get_action_meta() => array(
						array(
							'option_code'     => 'MODEL',
							/* translators: Action field */
							'label'           => esc_attr__( 'Model', 'uncanny-automator' ),
							'input_type'      => 'select',
							'required'        => true,
							'options'         => array(
								'gpt-3.5-turbo'      => 'gpt-3.5-turbo',
								'gpt-3.5-turbo-0301' => 'gpt-3.5-turbo-0301',
							),
							'options_show_id' => false,
						),
						array(
							'option_code' => 'TEMPERATURE',
							/* translators: Action field */
							'label'       => esc_attr__( 'Temperature', 'uncanny-automator' ),
							'input_type'  => 'text',
							'placeholder' => '1',
							'description' => esc_html__( 'What sampling temperature to use, between 0 and 2. Higher values like 0.8 will make the output more random, while lower values like 0.2 will make it more focused and deterministic.', 'uncanny-automator' ),
						),
						array(
							'option_code' => 'MAX_LEN',
							/* translators: Action field */
							'label'       => esc_attr__( 'Maximum length', 'uncanny-automator' ),
							'description' => $description,
							'input_type'  => 'text',
						),
						array(
							'option_code' => 'SYSTEM_CONTENT',
							/* translators: Action field */
							'label'       => esc_attr__( 'System message', 'uncanny-automator' ),
							'description' => esc_attr__( 'Add context or instructions to have GPT respond with those details in mind.', 'uncanny-automator' ),
							'input_type'  => 'textarea',
							'required'    => false,
						),
						array(
							'option_code' => $this->get_action_meta(),
							/* translators: Action field */
							'label'       => esc_attr__( 'Prompt', 'uncanny-automator' ),
							'input_type'  => 'textarea',
							'required'    => true,
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

		$model          = isset( $parsed['MODEL'] ) ? sanitize_text_field( $parsed['MODEL'] ) : null;
		$temperature    = ! empty( $parsed['TEMPERATURE'] ) ? sanitize_text_field( $parsed['TEMPERATURE'] ) : 1;
		$max_tokens     = ! empty( $parsed['MAX_LEN'] ) ? sanitize_text_field( $parsed['MAX_LEN'] ) : null;
		$system_content = isset( $parsed['SYSTEM_CONTENT'] ) ? sanitize_textarea_field( $parsed['SYSTEM_CONTENT'] ) : '';
		$prompt         = isset( $parsed[ $this->get_action_meta() ] ) ? sanitize_textarea_field( $parsed[ $this->get_action_meta() ] ) : '';

		try {

			$this->set_helpers( new Open_AI_Helpers( false ) );

			$body = array(
				'action'         => 'generate_chat',
				'prompt'         => $prompt,
				'temperature'    => $temperature,
				'model'          => $model,
				'max_tokens'     => $max_tokens,
				'system_content' => $system_content,
				'access_token'   => get_option( 'automator_open_ai_secret', '' ),
			);

			$body = apply_filters( 'automator_openai_chat_generate', $body );

			require_once UA_ABSPATH . 'src/integrations/open-ai/client/chat-completions/chat-completions.php';

			$client = new Chat_Completions_Client( Api_Server::get_instance(), $this->get_helpers() );

			$response = $client->set_parameters( $body )->send_request( $action_data );

			$this->hydrate_tokens_from_response( $response );

			Automator()->complete->action( $user_id, $action_data, $recipe_id );

		} catch ( \Exception $e ) {

			$action_data['complete_with_errors'] = true;

			Automator()->complete->action( $user_id, $action_data, $recipe_id, $e->getMessage() );

		}

	}

	/**
	 * Hydrates this specific action tokens.
	 *
	 * @param array $response.
	 *
	 * @return self
	 */
	private function hydrate_tokens_from_response( $response = array() ) {

		$response_text = isset( $response['data']['choices'][0]['message']['content'] ) ? $response['data']['choices'][0]['message']['content'] : '';

		if ( 0 === strlen( $response_text ) ) {
			throw new \Exception( 'The model predicted a completion that results in no output. Consider adjusting your prompt.', 400 );
		}

		$this->hydrate_tokens(
			array(
				'RESPONSE'                => $response_text,
				'USAGE_PROMPT_TOKENS'     => $response['data']['usage']['prompt_tokens'],
				'USAGE_COMPLETION_TOKENS' => $response['data']['usage']['completion_tokens'],
				'USAGE_TOTAL_TOKENS'      => $response['data']['usage']['total_tokens'],
			)
		);

		return $this;

	}

}
