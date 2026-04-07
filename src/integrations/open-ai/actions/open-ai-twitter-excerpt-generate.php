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
class OPEN_AI_TWITTER_EXCERPT_GENERATE extends App_Action {

	/**
	 * Prompt for generating a single marketing tweet with hashtags.
	 *
	 * @var string
	 */
	const PROMPT = "Generate a single marketing tweet, with appropriate hashtags, that's less than 210 characters, with no more than 1 emoji and no links, as if you are the author of the content below, based on the following content:\n\n{{content}}";

	/**
	 * Prompt for generating a single marketing tweet without hashtags.
	 *
	 * @var string
	 */
	const PROMPT_NO_HASHTAGS = "Generate a single marketing tweet without hashtags, that's less than 210 characters, with no more than 1 emoji and no links, as if you are the author of the content below, based on the following content:\n\n{{content}}";

	protected $model = 'gpt-4';

	/**
	 * @return bool
	 */
	public function requirements_met() {
		return $this->helpers->has_gpt4_access();
	}

	/**
	 * @return void
	 */
	protected function setup_action() {

		$this->set_integration( 'OPEN_AI' );
		$this->set_action_code( 'OPEN_AI_TWITTER_EXCERPT_GENERATE' );
		$this->set_action_meta( 'OPEN_AI_TWITTER_EXCERPT_GENERATE_META' );
		$this->set_support_link( Automator()->get_author_support_link( $this->get_action_code(), 'knowledge-base/open-ai/' ) );
		$this->set_requires_user( false );
		$this->set_wpautop( false );
		$this->set_background_processing( false );

		$this->set_sentence(
			sprintf(
				// translators: %1$s is the input field name
				esc_attr_x( 'Generate {{an excerpt:%1$s}} suitable for Twitter with GPT-4', 'OpenAI', 'uncanny-automator' ),
				'FILLER:' . $this->get_action_meta()
			)
		);

		$this->set_readable_sentence( esc_attr_x( 'Generate {{an excerpt}} suitable for Twitter with GPT-4', 'OpenAI', 'uncanny-automator' ) );

		$this->set_action_tokens(
			array(
				'GENERATED_EXCERPT' => array(
					'name' => esc_html_x( 'Generated excerpt', 'OpenAI', 'uncanny-automator' ),
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
		return array(
			$this->helpers->get_content_field( $this->get_action_meta() ),
			array(
				'option_code' => 'DISABLE_HASHTAGS',
				'label'       => esc_attr_x( 'Disable hashtags generation', 'OpenAI', 'uncanny-automator' ),
				'input_type'  => 'checkbox',
				'is_toggle'   => true,
			),
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

		$content = isset( $parsed[ $this->get_action_meta() ] )
			? sanitize_textarea_field( wp_strip_all_tags( preg_replace( '/<br\s?\/?>/i', "\r\n", $parsed[ $this->get_action_meta() ] ) ) )
			: '';

		$replace_pairs = array( '{{content}}' => $content );

		$prompt = strtr( self::PROMPT, $replace_pairs );

		if ( $this->hashtags_disabled( $parsed ) ) {
			$prompt = strtr( self::PROMPT_NO_HASHTAGS, $replace_pairs );
		}

		$response_text = $this->api->process_chat_completion( $prompt, $this->model, $this->get_action_code() );

		// Remove quotes from the response text. AI sometimes returns text with quotes.
		$response_text = trim( $response_text, '"\'' );

		$this->hydrate_tokens(
			array(
				'GENERATED_EXCERPT' => $response_text,
			)
		);

		return true;
	}

	/**
	 * Checks if hashtags generation is disabled.
	 *
	 * @param array $parsed The parsed action data.
	 *
	 * @return bool
	 */
	private function hashtags_disabled( $parsed ) {
		return isset( $parsed['DISABLE_HASHTAGS'] )
			&& 'true' === $parsed['DISABLE_HASHTAGS'];
	}
}
