<?php
namespace Uncanny_Automator;

use Uncanny_Automator\OpenAI\HTTP_Client;
use Uncanny_Automator\Recipe;

/**
 * @package Uncanny_Automator
 * @since 4.11
 */
class OPEN_AI_LINKS_SUGGEST {

	use Recipe\Actions, Recipe\Action_Tokens;

	const PROMPT = "Generate a list of up to {{max_n_pages}} pages on {{site_url}} that might be helpful in resolving the following question:\n{{request}}";

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
		$this->set_action_code( 'OPEN_AI_LINKS_SUGGEST' );
		$this->set_action_meta( 'OPEN_AI_LINKS_SUGGEST_META' );
		$this->set_support_link( Automator()->get_author_support_link( $this->get_action_code(), 'knowledge-base/open-ai/' ) );
		$this->set_requires_user( false );
		$this->set_options_callback( array( $this, 'load_options' ) );
		$this->set_wpautop( false );
		$this->set_background_processing( false );

		$this->set_sentence(
			sprintf(
				/* translators: Action sentence */
				esc_attr__( 'Create a list of links that might help resolve {{a customer request:%1$s}} with GPT-4', 'uncanny-automator' ),
				'FILLER:' . $this->get_action_meta()
			)
		);

		/* translators: Action sentence */
		$this->set_readable_sentence( esc_attr__( 'Create a list of links that might help resolve {{a customer request}} with GPT-4', 'uncanny-automator' ) );

		$this->set_action_tokens(
			array(
				'GENERATED_LINKS_LIST' => array(
					'name' => __( 'Generated list of links', 'uncanny-automator' ),
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
							'label'       => esc_attr__( 'Request', 'uncanny-automator' ),
							'input_type'  => 'textarea',
							'required'    => true,
						),
						array(
							'option_code' => 'MAX_LENGTH',
							/* translators: Action field */
							'label'       => esc_attr__( 'Maximum number of links', 'uncanny-automator' ),
							'input_type'  => 'int',
							'required'    => true,
						),
						array(
							'option_code' => 'SITE_URL',
							/* translators: Action field */
							'label'       => esc_attr__( 'Site URL', 'uncanny-automator' ),
							'input_type'  => 'url',
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

		$request    = isset( $parsed[ $this->get_action_meta() ] ) ? sanitize_textarea_field( $parsed[ $this->get_action_meta() ] ) : '';
		$max_length = isset( $parsed['MAX_LENGTH'] ) ? sanitize_text_field( $parsed['MAX_LENGTH'] ) : '';
		$site_url   = isset( $parsed['SITE_URL'] ) ? esc_url_raw( $parsed['SITE_URL'] ) : '';

		$replace_pairs = array(
			'{{max_n_pages}}' => $max_length,
			'{{request}}'     => $request,
			'{{site_url}}'    => $site_url,
		);

		$prompt = strtr( self::PROMPT, $replace_pairs );

		try {

			$response_text = $helper->process_openai_chat_completions( $prompt, $this->model, $this->get_action_code() );

			$this->hydrate_tokens(
				array(
					'GENERATED_LINKS_LIST' => $response_text,
				)
			);

		} catch ( \Exception $e ) {

			$action_data['complete_with_errors'] = true;

			return Automator()->complete->action( $user_id, $action_data, $recipe_id, $e->getMessage() );

		}

		return Automator()->complete->action( $user_id, $action_data, $recipe_id );

	}

}
