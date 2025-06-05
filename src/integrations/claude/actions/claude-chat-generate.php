<?php
namespace Uncanny_Automator\Integrations\Claude\Actions;

use Uncanny_Automator\Core\Lib\AI\Core\Traits\Base_AI_Provider_Trait;
use Uncanny_Automator\Core\Lib\AI\Core\Traits\Base_Payload_Message_Array_Builder_Trait;
use Uncanny_Automator\Core\Lib\AI\Http\Response;
use Uncanny_Automator\Recipe\Action;

/**
 * Anthropic Claude chat generation action.
 *
 * Provides AI text generation capabilities using Anthropic's Claude models
 * through the Uncanny Automator AI framework.
 *
 * @package Uncanny_Automator\Integrations\Claude\Actions
 * @since 5.6
 */
class Claude_Chat_Generate extends Action {

	use Base_AI_Provider_Trait;
	use Base_Payload_Message_Array_Builder_Trait;

	/**
	 * Setup action configuration.
	 *
	 * @return void
	 */
	protected function setup_action() {

		$this->set_integration( 'CLAUDE' );
		$this->set_action_code( 'CLAUDE_CHAT_GENERATE' );
		$this->set_action_meta( 'CLAUDE_CHAT_GENERATE_META' );
		$this->set_is_pro( false );
		$this->set_requires_user( false );

		$this->set_sentence(
			sprintf(
				/* translators: 1: Prompt field placeholder, 2: Model field placeholder */
				esc_html_x(
					'Use {{a prompt:%1$s}} to generate a text response with {{a Claude model:%2$s}}',
					'Claude',
					'uncanny-automator'
				),
				'PROMPT:' . $this->get_action_meta(),
				'MODEL:' . $this->get_action_meta()
			)
		);

		$this->set_readable_sentence(
			esc_html_x(
				'Use {{a prompt}} to generate a text response with {{a Claude model}}',
				'Claude',
				'uncanny-automator'
			)
		);

		$this->set_wpautop( false );
		$this->set_background_processing( false );

		$this->set_action_tokens(
			array(
				'RESPONSE'                    => array(
					'name' => esc_attr_x( 'Response', 'Claude', 'uncanny-automator' ),
					'type' => 'text',
				),
				'USAGE_PROMPT_TOKENS'         => array(
					'name' => esc_attr_x( 'Prompt tokens usage', 'Claude', 'uncanny-automator' ),
				),
				'USAGE_COMPLETION_TOKENS'     => array(
					'name' => esc_attr_x( 'Completion tokens usage', 'Claude', 'uncanny-automator' ),
				),
				'USAGE_TOTAL_TOKENS'          => array(
					'name' => esc_attr_x( 'Total tokens usage', 'Claude', 'uncanny-automator' ),
				),
				'USAGE_CACHE_CREATION_TOKENS' => array(
					'name' => esc_attr_x( 'Cache creation tokens usage', 'Claude', 'uncanny-automator' ),
				),
				'USAGE_CACHE_READ_TOKENS'     => array(
					'name' => esc_attr_x( 'Cache read tokens usage', 'Claude', 'uncanny-automator' ),
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

		// translators: 1: Link to the documentation, 2: External link icon
		$description_max_tokens = sprintf( esc_html_x( 'The maximum number of tokens to generate before stopping. Note that Claude models may stop before reaching this maximum. This parameter only specifies the absolute maximum number of tokens to generate. Different models have different maximum values for this parameter. %s', 'Claude', 'uncanny-automator' ), '<a href="https://docs.anthropic.com/en/docs/about-claude/models/overview" target="_blank">' . esc_html_x( 'Learn more', 'Claude', 'uncanny-automator' ) . '</a> <uo-icon id="external-link"></uo-icon>.' ); // phpcs:ignore Uncanny_Automator.Strings.SentenceCase.PotentialCaseIssue

		// translators: 1: Link to the documentation, 2: External link icon
		$description_temperature = sprintf( esc_html_x( 'Amount of randomness injected into the response. Defaults to 1.0. Ranges from 0.0 to 1.0. Use temperature closer to 0.0 for analytical / multiple choice, and closer to 1.0 for creative and generative tasks. %s', 'Claude', 'uncanny-automator' ), '<a href="https://docs.anthropic.com/en/api/complete#body-temperature" target="_blank">' . esc_html_x( 'Learn more', 'Claude', 'uncanny-automator' ) . '</a> <uo-icon id="external-link"></uo-icon>.' );

		$description_system = esc_html_x( 'Add context or instructions for Claude to follow.', 'Claude', 'uncanny-automator' );

		$models = array(
			array(
				'text'  => 'Claude Opus 4 (20250514)',
				'value' => 'claude-opus-4-20250514',
			),
			array(
				'text'  => 'Claude Sonnet 4 (20250514)',
				'value' => 'claude-sonnet-4-20250514',
			),
			array(
				'text'  => 'Claude Sonnet 3.7 (20250219)',
				'value' => 'claude-3-7-sonnet-20250219',
			),
			array(
				'text'  => 'Claude Sonnet 3.5 (20241022)',
				'value' => 'claude-3-5-sonnet-20241022',
			),
			array(
				'text'  => 'Claude Haiku 3.5 (20241022)',
				'value' => 'claude-3-5-haiku-20241022',
			),
			array(
				'text'  => 'Claude Opus 3 (20240229)',
				'value' => 'claude-3-opus-20240229',
			),
		);

		return array(
			array(
				'option_code'     => 'MODEL',
				'label'           => esc_attr_x( 'Model', 'Claude', 'uncanny-automator' ),
				'input_type'      => 'select',
				'required'        => true,
				'options'         => $models,
				'options_show_id' => false,
			),
			array(
				'option_code' => 'TEMPERATURE',
				'label'       => esc_attr_x( 'Temperature', 'Claude', 'uncanny-automator' ),
				'input_type'  => 'text',
				'placeholder' => '1',
				'default'     => '1',
				'description' => $description_temperature,
			),
			array(
				'option_code' => 'MAX_TOKENS',
				'label'       => esc_attr_x( 'Max tokens', 'Claude', 'uncanny-automator' ),
				'input_type'  => 'text',
				'placeholder' => '2048',
				'default'     => '2048',
				'description' => $description_max_tokens,
			),
			array(
				'option_code' => 'SYSTEM_CONTENT',
				'label'       => esc_attr_x( 'System message', 'Claude', 'uncanny-automator' ),
				'input_type'  => 'textarea',
				'description' => $description_system,
				'required'    => false,
			),
			array(
				'option_code'       => $this->get_action_meta(),
				'label'             => esc_attr_x( 'Prompt', 'Claude', 'uncanny-automator' ),
				'input_type'        => 'textarea',
				'supports_markdown' => true,
				'required'          => true,
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

		$temperature    = $this->get_parsed_meta_value( 'TEMPERATURE' ) ?? 1;
		$max_tokens     = $this->get_parsed_meta_value( 'MAX_LEN' ) ?? 2048;
		$model          = $this->get_parsed_meta_value( 'MODEL' ) ?? '';
		$system_content = $this->get_parsed_meta_value( 'SYSTEM_CONTENT' ) ?? '';
		$prompt         = $this->get_parsed_meta_value( $this->get_action_meta() ) ?? '';

		if ( empty( $max_tokens ) ) {
			$max_tokens = 2048;
		}
		if ( empty( $temperature ) ) {
			$temperature = 1;
		}

		$provider        = $this->get_provider( 'CLAUDE' );
		$payload_builder = $this->get_payload_builder( $provider );

		$message = array(
			array(
				'role'    => 'user',
				'content' => $prompt,
			),
		);

		$payload = $payload_builder
			->endpoint( 'https://api.anthropic.com/v1/messages' )
			->body( 'system', $system_content )
			->model( $model )
			->temperature( (float) $temperature )
			->max_tokens( (int) $max_tokens )
			->messages( $message )
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
				'RESPONSE'                    => $content,
				'USAGE_PROMPT_TOKENS'         => $metadata['prompt_tokens'] ?? null,
				'USAGE_COMPLETION_TOKENS'     => $metadata['completion_tokens'] ?? null,
				'USAGE_TOTAL_TOKENS'          => $metadata['total_tokens'] ?? null,
				'USAGE_CACHE_CREATION_TOKENS' => $metadata['cache_creation_tokens'] ?? null,
				'USAGE_CACHE_READ_TOKENS'     => $metadata['cache_read_tokens'] ?? null,
			)
		);

		return $this;
	}
}
