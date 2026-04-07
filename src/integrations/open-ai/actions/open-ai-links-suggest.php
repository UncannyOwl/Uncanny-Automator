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
class OPEN_AI_LINKS_SUGGEST extends App_Action {

	const PROMPT = "Generate a list of up to {{max_n_pages}} pages on {{site_url}} that might be helpful in resolving the following question:\n{{request}}";

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
		$this->set_action_code( 'OPEN_AI_LINKS_SUGGEST' );
		$this->set_action_meta( 'OPEN_AI_LINKS_SUGGEST_META' );
		$this->set_support_link( Automator()->get_author_support_link( $this->get_action_code(), 'knowledge-base/open-ai/' ) );
		$this->set_requires_user( false );
		$this->set_wpautop( false );
		$this->set_background_processing( false );

		$this->set_sentence(
			sprintf(
				// translators: %1$s is the input field name
				esc_attr_x( 'Create a list of links that might help resolve {{a customer request:%1$s}} with GPT-4', 'OpenAI', 'uncanny-automator' ),
				'FILLER:' . $this->get_action_meta()
			)
		);

		$this->set_readable_sentence( esc_attr_x( 'Create a list of links that might help resolve {{a customer request}} with GPT-4', 'OpenAI', 'uncanny-automator' ) );

		$this->set_action_tokens(
			array(
				'GENERATED_LINKS_LIST' => array(
					'name' => esc_html_x( 'Generated list of links', 'OpenAI', 'uncanny-automator' ),
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
			$this->helpers->get_content_field( $this->get_action_meta(), esc_attr_x( 'Request', 'OpenAI', 'uncanny-automator' ) ),
			array(
				'option_code' => 'MAX_LENGTH',
				'label'       => esc_attr_x( 'Maximum number of links', 'OpenAI', 'uncanny-automator' ),
				'input_type'  => 'int',
				'required'    => true,
			),
			array(
				'option_code' => 'SITE_URL',
				'label'       => esc_attr_x( 'Site URL', 'OpenAI', 'uncanny-automator' ),
				'input_type'  => 'url',
				'required'    => true,
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

		$request    = isset( $parsed[ $this->get_action_meta() ] ) ? sanitize_textarea_field( $parsed[ $this->get_action_meta() ] ) : '';
		$max_length = isset( $parsed['MAX_LENGTH'] ) ? sanitize_text_field( $parsed['MAX_LENGTH'] ) : '';
		$site_url   = isset( $parsed['SITE_URL'] ) ? esc_url_raw( $parsed['SITE_URL'] ) : '';

		$prompt = strtr(
			self::PROMPT,
			array(
				'{{max_n_pages}}' => $max_length,
				'{{request}}'     => $request,
				'{{site_url}}'    => $site_url,
			)
		);

		$response_text = $this->api->process_chat_completion( $prompt, $this->model, $this->get_action_code() );

		$this->hydrate_tokens(
			array(
				'GENERATED_LINKS_LIST' => $response_text,
			)
		);

		return true;
	}
}
