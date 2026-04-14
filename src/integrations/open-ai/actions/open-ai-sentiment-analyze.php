<?php

namespace Uncanny_Automator\Integrations\OpenAI;

use Uncanny_Automator\Recipe\App_Action;

/**
 * @package Uncanny_Automator
 * @since 4.11
 *
 * @property OpenAI_App_Helpers $helpers
 * @property OpenAI_Api_Caller $api
 */
class OPEN_AI_SENTIMENT_ANALYZE extends App_Action {

	const PROMPT = "Classify the sentiment of the following text as Positive, Neutral or Negative:\n{{content}}";

	protected $model = 'gpt-4';

	/**
	 * GPT-4 gating - action only registers if user has GPT-4 access.
	 *
	 * @return bool
	 */
	public function requirements_met() {
		return $this->helpers->has_gpt4_access();
	}

	/**
	 * Setup Action.
	 *
	 * @return void
	 */
	protected function setup_action() {

		$this->set_integration( 'OPEN_AI' );
		$this->set_action_code( 'OPEN_AI_SENTIMENT_ANALYZE' );
		$this->set_action_meta( 'OPEN_AI_SENTIMENT_ANALYZE_META' );
		$this->set_support_link( Automator()->get_author_support_link( $this->get_action_code(), 'knowledge-base/open-ai/' ) );
		$this->set_requires_user( false );
		$this->set_wpautop( false );
		$this->set_background_processing( false );

		$this->set_sentence(
			sprintf(
				// translators: %1$s is the input field name
				esc_attr_x( 'Analyze {{sentiment:%1$s}} with GPT-4', 'OpenAI', 'uncanny-automator' ),
				'FILLER:' . $this->get_action_meta()
			)
		);

		$this->set_readable_sentence( esc_attr_x( 'Analyze {{sentiment}} with GPT-4', 'OpenAI', 'uncanny-automator' ) );

		$this->set_action_tokens(
			array(
				'SENTIMENT_ANALYSIS' => array(
					'name' => esc_html_x( 'Sentiment analysis', 'OpenAI', 'uncanny-automator' ),
					'type' => 'text',
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
		return array(
			$this->helpers->get_content_field( $this->get_action_meta() ),
		);
	}

	/**
	 * Processes action.
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

		$content = isset( $parsed[ $this->get_action_meta() ] ) ? sanitize_textarea_field( $parsed[ $this->get_action_meta() ] ) : '';

		$prompt = strtr( self::PROMPT, array( '{{content}}' => $content ) );

		$response_text = $this->api->process_chat_completion( $prompt, $this->model, $this->get_action_code() );

		$this->hydrate_tokens(
			array(
				'SENTIMENT_ANALYSIS' => $response_text,
			)
		);

		return true;
	}
}
