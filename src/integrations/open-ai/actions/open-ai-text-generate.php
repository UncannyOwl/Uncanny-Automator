<?php

namespace Uncanny_Automator\Integrations\OpenAI;

use Uncanny_Automator\Recipe\App_Action;

/**
 * @since 4.10
 * @package Uncanny_Automator
 *
 * @property OpenAI_App_Helpers $helpers
 * @property OpenAI_Api_Caller $api
 */
class OPEN_AI_TEXT_GENERATE extends App_Action {

	/**
	 * @return void
	 */
	protected function setup_action() {

		$this->set_integration( 'OPEN_AI' );
		$this->set_action_code( 'OPEN_AI_TEXT_GENERATE' );
		$this->set_action_meta( 'OPEN_AI_TEXT_GENERATE_META' );
		$this->set_is_pro( false );
		$this->set_support_link( Automator()->get_author_support_link( $this->get_action_code(), 'knowledge-base/open-ai/' ) );
		$this->set_requires_user( false );
		$this->set_wpautop( false );
		$this->set_background_processing( true );

		$this->set_sentence(
			sprintf(
				// translators: %1$s is the input field name
				esc_attr_x( 'Use {{a prompt:%1$s}} to generate text', 'OpenAI', 'uncanny-automator' ),
				$this->get_action_meta()
			)
		);

		$this->set_readable_sentence( esc_attr_x( 'Use {{a prompt}} to generate text', 'OpenAI', 'uncanny-automator' ) );

		$this->set_action_tokens(
			array(
				'RESPONSE' => array(
					'name' => esc_html_x( 'Response', 'OpenAI', 'uncanny-automator' ),
					'type' => 'text',
				),
			),
			$this->get_action_code()
		);
	}

	/**
	 * @return array
	 */
	public function options() {

		$description = wp_kses_post(
			sprintf(
				/* translators: 1: Opening anchor tag. 2: Closing anchor tag. */
				esc_html_x(
					'The maximum number of tokens. Tokens are shared between the prompt and the response. %1$sLearn more about tokens%2$s.',
					'OpenAI',
					'uncanny-automator'
				),
				'<a href="https://help.openai.com/en/articles/4936856-what-are-tokens-and-how-to-count-them" target="_blank">',
				'</a>'
			)
		);

		return array(
			array(
				'option_code'     => 'MODEL',
				'label'           => esc_attr_x( 'Model', 'OpenAI', 'uncanny-automator' ),
				'input_type'      => 'select',
				'required'        => true,
				'options'         => $this->load_models(),
				'options_show_id' => false,
			),
			array(
				'option_code' => 'TEMPERATURE',
				'label'       => esc_attr_x( 'Temperature', 'OpenAI', 'uncanny-automator' ),
				'input_type'  => 'text',
				'placeholder' => '0.7',
				'description' => esc_html_x( 'Higher values mean the model will take more risks. Try 0.9 for more creative applications and a value closer to 0 for a well-defined answer.', 'OpenAI', 'uncanny-automator' ),
			),
			array(
				'option_code' => 'MAX_LEN',
				'label'       => esc_attr_x( 'Maximum length', 'OpenAI', 'uncanny-automator' ),
				'description' => $description,
				'input_type'  => 'text',
				'placeholder' => '256',
			),
			$this->helpers->get_prompt_field( $this->get_action_meta() ),
		);
	}

	/**
	 * @param int   $user_id
	 * @param array $action_data
	 * @param int   $recipe_id
	 * @param array $args
	 * @param array $parsed
	 *
	 * @return bool
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		$this->helpers->migrate_text_models( $recipe_id );

		// Read from post meta (not $parsed) because migrate_text_models() may have just updated it.
		$model       = get_post_meta( $action_data['ID'], 'MODEL', true );
		$temperature = ! empty( $parsed['TEMPERATURE'] ) ? sanitize_text_field( $parsed['TEMPERATURE'] ) : '0.7';
		$max_tokens  = ! empty( $parsed['MAX_LEN'] ) ? sanitize_text_field( $parsed['MAX_LEN'] ) : '256';
		$prompt      = sanitize_textarea_field( $parsed[ $this->get_action_meta() ] ?? '' );

		$body = array(
			'prompt'      => $prompt,
			'temperature' => floatval( $temperature ),
			'max_tokens'  => intval( $max_tokens ),
			'model'       => $model,
		);

		$body = apply_filters( 'automator_open_ai_text_generate', $body );

		$response      = $this->api->openai_request( 'v1/completions', $body );
		$response_text = $this->api->get_completion_text( $response );

		$this->hydrate_tokens(
			array(
				'RESPONSE' => $response_text,
			)
		);

		return true;
	}

	/**
	 * Load available text models.
	 *
	 * @return array
	 */
	private function load_models() {

		$recipe_id = automator_filter_input( 'post' );

		$this->helpers->migrate_text_models( $recipe_id );

		return array(
			array(
				'value' => 'babbage-002',
				'text'  => 'babbage-002',
			),
			array(
				'value' => 'davinci-002',
				'text'  => 'davinci-002',
			),
			array(
				'value' => 'gpt-3.5-turbo-instruct',
				'text'  => 'gpt-3.5-turbo-instruct',
			),
		);
	}
}
