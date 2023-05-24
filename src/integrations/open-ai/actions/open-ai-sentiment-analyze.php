<?php
namespace Uncanny_Automator;

use Uncanny_Automator\OpenAI\HTTP_Client;
use Uncanny_Automator\Recipe;

/**
 * @package Uncanny_Automator
 * @since 4.11
 */
class OPEN_AI_SENTIMENT_ANALYZE {

	use Recipe\Actions, Recipe\Action_Tokens;

	const PROMPT = "Classify the sentiment of the following text as Positive, Neutral or Negative:\n{{content}}";

	protected $model = 'gpt-4';

	public function __construct() {

		$this->set_helpers( new Open_AI_Helpers( false ) );

		if ( ! $this->get_helpers()->has_gpt4_access() ) {
			return;
		}

		$this->setup_action();

	}

	/**
	 * Setup Action.
	 *
	 * @return void.
	 */
	protected function setup_action() {

		$this->set_integration( 'OPEN_AI' );
		$this->set_action_code( 'OPEN_AI_SENTIMENT_ANALYZE' );
		$this->set_action_meta( 'OPEN_AI_SENTIMENT_ANALYZE_META' );
		$this->set_support_link( Automator()->get_author_support_link( $this->get_action_code(), 'knowledge-base/open-ai/' ) );
		$this->set_requires_user( false );
		$this->set_options_callback( array( $this, 'load_options' ) );
		$this->set_wpautop( false );
		$this->set_background_processing( false );

		$this->set_sentence(
			sprintf(
				/* translators: Action sentence */
				esc_attr__( 'Analyze {{sentiment:%1$s}} with GPT-4', 'uncanny-automator' ),
				'FILLER:' . $this->get_action_meta()
			)
		);

		/* translators: Action sentence */
		$this->set_readable_sentence( esc_attr__( 'Analyze {{sentiment}} with GPT-4', 'uncanny-automator' ) );

		$this->set_action_tokens(
			array(
				'SENTIMENT_ANALYSIS' => array(
					'name' => __( 'Sentiment analysis', 'uncanny-automator' ),
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

		return Automator()->utilities->keep_order_of_options(
			array(
				'options_group' => array(
					$this->get_action_meta() => array(
						array(
							'option_code' => $this->get_action_meta(),
							/* translators: Action field */
							'label'       => esc_attr__( 'Content', 'uncanny-automator' ),
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

		$helper = $this->get_helpers();

		$content = isset( $parsed[ $this->get_action_meta() ] ) ? sanitize_textarea_field( $parsed[ $this->get_action_meta() ] ) : '';

		$replace_pairs = array(
			'{{content}}' => $content,
		);

		$prompt = strtr( self::PROMPT, $replace_pairs );

		try {

			$response_text = $helper->process_openai_chat_completions( $prompt, $this->model, $this->get_action_code() );

			$this->hydrate_tokens(
				array(
					'SENTIMENT_ANALYSIS' => $response_text,
				)
			);

		} catch ( \Exception $e ) {

			$action_data['complete_with_errors'] = true;

			return Automator()->complete->action( $user_id, $action_data, $recipe_id, $e->getMessage() );

		}

		return Automator()->complete->action( $user_id, $action_data, $recipe_id );

	}

}
