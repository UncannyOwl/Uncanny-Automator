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
class OPEN_AI_TEXT_TRANSLATE extends App_Action {

	const PROMPT = "Translate the following text into {{target_lang}}:\n{{content}}";

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
		$this->set_action_code( 'OPEN_AI_TEXT_TRANSLATE' );
		$this->set_action_meta( 'OPEN_AI_TEXT_TRANSLATE_META' );
		$this->set_support_link( Automator()->get_author_support_link( $this->get_action_code(), 'knowledge-base/open-ai/' ) );
		$this->set_requires_user( false );
		$this->set_wpautop( false );
		$this->set_background_processing( false );

		$this->set_sentence(
			sprintf(
				// translators: %1$s is the input field name
				esc_attr_x( 'Translate {{text:%1$s}} with GPT-4', 'OpenAI', 'uncanny-automator' ),
				'FILLER:' . $this->get_action_meta()
			)
		);

		$this->set_readable_sentence( esc_attr_x( 'Translate {{text}} with GPT-4', 'OpenAI', 'uncanny-automator' ) );

		$this->set_action_tokens(
			array(
				'TEXT_TRANSLATED' => array(
					'name' => esc_html_x( 'Translated text', 'OpenAI', 'uncanny-automator' ),
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
				'option_code'     => 'LANG',
				'label'           => esc_attr_x( 'Target language', 'OpenAI', 'uncanny-automator' ),
				'input_type'      => 'select',
				'options'         => $this->get_languages(),
				'options_show_id' => false,
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

		$content     = isset( $parsed[ $this->get_action_meta() ] ) ? sanitize_textarea_field( $parsed[ $this->get_action_meta() ] ) : '';
		$target_lang = isset( $parsed['LANG'] ) ? sanitize_text_field( $parsed['LANG'] ) : '';

		$prompt = strtr(
			self::PROMPT,
			array(
				'{{content}}'     => $content,
				'{{target_lang}}' => $target_lang,
			)
		);

		$response_text = $this->api->process_chat_completion( $prompt, $this->model, $this->get_action_code() );

		$this->hydrate_tokens(
			array(
				'TEXT_TRANSLATED' => $response_text,
			)
		);

		return true;
	}

	/**
	 * Get the list of supported languages.
	 *
	 * @return array
	 */
	private function get_languages() {
		$languages = array(
			'English',
			'Japanese',
			'Spanish',
			'German',
			'French',
			'Italian',
			'Russian',
			'Dutch',
			'Polish',
			'Turkish',
			'Persian',
			'Vietnamese',
			'Chinese',
			'Czech',
			'Swedish',
			'Indonesian',
			'Danish',
			'Hungarian',
			'Hebrew',
			'Arabic',
			'Romanian',
			'Greek',
			'Korean',
			'Thai',
		);

		return array_map(
			function ( $lang ) {
				return array(
					'value' => $lang,
					'text'  => $lang,
				);
			},
			$languages
		);
	}
}
