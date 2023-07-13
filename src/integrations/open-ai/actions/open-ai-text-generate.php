<?php
namespace Uncanny_Automator;

use Uncanny_Automator\OpenAI\HTTP_Client;

/**
 * @since 4.10
 * @package Uncanny_Automator
 */
class OPEN_AI_TEXT_GENERATE {

	use Recipe\Actions, Recipe\Action_Tokens;

	public function __construct() {

		$this->setup_action();

		$this->set_helpers( new Open_AI_Helpers( false ) );

	}

	/**
	 * Setup Action.
	 *
	 * @return void.
	 */
	protected function setup_action() {

		$this->set_integration( 'OPEN_AI' );

		$this->set_action_code( 'OPEN_AI_TEXT_GENERATE' );

		$this->set_action_meta( 'OPEN_AI_TEXT_GENERATE_META' );

		$this->set_is_pro( false );

		$this->set_support_link( Automator()->get_author_support_link( $this->get_action_code(), 'knowledge-base/open-ai/' ) );

		$this->set_requires_user( false );

		$this->set_sentence(
			sprintf(
				/* translators: Action sentence */
				esc_attr__( 'Use {{a prompt:%1$s}} to generate text', 'uncanny-automator' ),
				$this->get_action_meta()
			)
		);

		/* translators: Action sentence */
		$this->set_readable_sentence( esc_attr__( 'Use {{a prompt}} to generate text', 'uncanny-automator' ) );

		$this->set_options_callback( array( $this, 'load_options' ) );

		$this->set_wpautop( false );

		$this->set_background_processing( true );

		$this->set_action_tokens(
			array(
				'RESPONSE' => array(
					'name' => __( 'Response', 'uncanny-automator' ),
					'type' => 'text',
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
					'The maximum number of tokens. Tokens are shared between the prompt and the response. %1$sLearn more about tokens%2$s.',
					'uncanny-automator'
				),
				'<a href="https://help.openai.com/en/articles/4936856-what-are-tokens-and-how-to-count-them" target="_blank">',
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
								'text-curie-001'   => 'Curie',
								'text-babbage-001' => 'Babbage',
								'text-ada-001'     => 'Ada',
							),
							'options_show_id' => false,
						),
						array(
							'option_code' => 'TEMPERATURE',
							/* translators: Action field */
							'label'       => esc_attr__( 'Temperature', 'uncanny-automator' ),
							'input_type'  => 'text',
							'placeholder' => '0.7',
							'description' => esc_html__( 'Higher values mean the model will take more risks. Try 0.9 for more creative applications and a value closer to 0 for a well-defined answer.', 'uncanny-automator' ),
						),
						array(
							'option_code' => 'MAX_LEN',
							/* translators: Action field */
							'label'       => esc_attr__( 'Maximum length', 'uncanny-automator' ),
							'description' => $description,
							'input_type'  => 'text',
							'placeholder' => '256',
						),
						array(
							'option_code'       => $this->get_action_meta(),
							/* translators: Action field */
							'label'             => esc_attr__( 'Prompt', 'uncanny-automator' ),
							'input_type'        => 'textarea',
							'supports_markdown' => true,
							'required'          => true,
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

		$model       = isset( $parsed['MODEL'] ) ? sanitize_text_field( $parsed['MODEL'] ) : 'curie';
		$temperature = ! empty( $parsed['TEMPERATURE'] ) ? sanitize_text_field( $parsed['TEMPERATURE'] ) : 0.7;
		$max_tokens  = ! empty( $parsed['MAX_LEN'] ) ? sanitize_text_field( $parsed['MAX_LEN'] ) : 256;
		$prompt      = isset( $parsed[ $this->get_action_meta() ] ) ? sanitize_textarea_field( $parsed[ $this->get_action_meta() ] ) : '';

		try {

			$body = array(
				'prompt'      => $prompt,
				'temperature' => floatval( $temperature ),
				'max_tokens'  => intval( $max_tokens ),
				'model'       => $model,
			);

			$body = apply_filters( 'automator_open_ai_text_generate', $body );

			require_once dirname( __DIR__ ) . '/client/http-client.php';

			$client = new HTTP_Client( Api_Server::get_instance() );
			$client->set_endpoint( 'v1/completions' );
			$client->set_api_key( (string) automator_get_option( 'automator_open_ai_secret', '' ) );
			$client->set_request_body( $body );

			try {

				$client->send_request();
				$this->hydrate_tokens_from_response( $client->get_response() );

			} catch ( \Exception $e ) {

				$action_data['complete_with_errors'] = true;
				return Automator()->complete->action( $user_id, $action_data, $recipe_id, $e->getMessage() );

			}

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

		$response_text = isset( $response['choices'][0]['text'] )
			? $response['choices'][0]['text'] :
			''; // Defaults to empty string.

		if ( 0 === strlen( $response_text ) ) {
			throw new \Exception( 'The model predicted a completion that results in no output. Consider adjusting your prompt.', 400 );
		}

		$this->hydrate_tokens(
			array(
				'RESPONSE' => $response_text,
			)
		);

		return $this;

	}

}
