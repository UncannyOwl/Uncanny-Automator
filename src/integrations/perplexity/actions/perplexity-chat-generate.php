<?php
namespace Uncanny_Automator\Integrations\Perplexity\Actions;

use Uncanny_Automator\Recipe\App_Action;
use Uncanny_Automator\Core\Lib\AI\Core\Traits\Base_AI_Provider_Trait;
use Uncanny_Automator\Core\Lib\AI\Core\Traits\Base_Payload_Message_Array_Builder_Trait;
use Uncanny_Automator\Core\Lib\AI\Http\Response;

/**
 * Perplexity AI chat generation action.
 *
 * Provides AI text generation capabilities using Perplexity's search-augmented models
 * through the Uncanny Automator AI framework.
 *
 * @package Uncanny_Automator\Integrations\Perplexity\Actions
 * @since 5.7
 */
class Perplexity_Chat_Generate extends App_Action {

	use Base_AI_Provider_Trait;
	use Base_Payload_Message_Array_Builder_Trait;

	/**
	 * Setup action configuration.
	 *
	 * @return void
	 */
	protected function setup_action() {

		$this->set_integration( 'PERPLEXITY' );
		$this->set_action_code( 'PERPLEXITY_CHAT_GENERATE' );
		$this->set_action_meta( 'PERPLEXITY_CHAT_GENERATE_META' );
		$this->set_is_pro( false );
		$this->set_requires_user( false );

		$this->set_sentence(
			sprintf(
				/* translators: 1: Prompt field placeholder, 2: Model field placeholder */
				esc_html_x(
					'Use {{a prompt:%1$s}} to generate a text response with {{a Perplexity model:%2$s}}',
					'Perplexity',
					'uncanny-automator'
				),
				'PROMPT:' . $this->get_action_meta(),
				'MODEL:' . $this->get_action_meta()
			)
		);

		$this->set_readable_sentence(
			esc_html_x(
				'Use {{a prompt}} to generate a text response with {{a Perplexity model}}',
				'Perplexity',
				'uncanny-automator'
			)
		);

		$this->set_wpautop( false );
		$this->set_background_processing( false );

		// Define action tokens
		$response_token = array(
			'name' => esc_attr_x( 'Response', 'Perplexity', 'uncanny-automator' ),
			'type' => 'text',
		);

		$citations_token = array(
			'name' => esc_attr_x( 'Citations', 'Perplexity', 'uncanny-automator' ),
			'type' => 'text',
		);

		$search_results_token = array(
			'name' => esc_attr_x( 'Search results', 'Perplexity', 'uncanny-automator' ),
			'type' => 'text',
		);

		$related_questions_token = array(
			'name' => esc_attr_x( 'Related questions', 'Perplexity', 'uncanny-automator' ),
			'type' => 'text',
		);

		$prompt_tokens_token = array(
			'name' => esc_attr_x( 'Prompt tokens usage', 'Perplexity', 'uncanny-automator' ),
		);

		$completion_tokens_token = array(
			'name' => esc_attr_x( 'Completion tokens usage', 'Perplexity', 'uncanny-automator' ),
		);

		$total_tokens_token = array(
			'name' => esc_attr_x( 'Total tokens usage', 'Perplexity', 'uncanny-automator' ),
		);

		$model_used_token = array(
			'name' => esc_attr_x( 'Model used', 'Perplexity', 'uncanny-automator' ),
		);

		$tokens = array(
			'RESPONSE'                => $response_token,
			'CITATIONS'               => $citations_token,
			'SEARCH_RESULTS'          => $search_results_token,
			'RELATED_QUESTIONS'       => $related_questions_token,
			'USAGE_PROMPT_TOKENS'     => $prompt_tokens_token,
			'USAGE_COMPLETION_TOKENS' => $completion_tokens_token,
			'USAGE_TOTAL_TOKENS'      => $total_tokens_token,
			'MODEL_USED'              => $model_used_token,
		);

		$this->set_action_tokens( $tokens, $this->get_action_code() );
	}

	/**
	 * Define action option fields.
	 *
	 * @return array
	 */
	public function options() {
		// Model options
		$model_options = array(
			array(
				'text'  => 'Sonar (Search)',
				'value' => 'sonar',
			),
			array(
				'text'  => 'Sonar Pro (Search)',
				'value' => 'sonar-pro',
			),
			array(
				'text'  => 'Sonar Deep Research (Research)',
				'value' => 'sonar-deep-research',
			),
			array(
				'text'  => 'Sonar Reasoning (Reasoning)',
				'value' => 'sonar-reasoning',
			),
			array(
				'text'  => 'Sonar Reasoning Pro (Reasoning)',
				'value' => 'sonar-reasoning-pro',
			),
		);

		// Model selection field
		$model_field = array(
			'option_code'     => 'MODEL',
			'label'           => esc_attr_x( 'Model', 'Perplexity', 'uncanny-automator' ),
			'input_type'      => 'select',
			'required'        => true,
			'options'         => $model_options,
			'options_show_id' => false,
			'description'     => esc_html_x( 'Search models are designed to retrieve and synthesize information efficiently. Research models conduct in-depth analysis and generate detailed reports. Reasoning models are excel at complex, multi-step tasks.', 'Perplexity', 'uncanny-automator' ),
			'relevant_tokens' => array(),
		);

		// Search domain filter.
		$search_domain_filter_field = array(
			'option_code'     => 'SEARCH_DOMAIN_FILTER',
			'label'           => esc_attr_x( 'Search domain filter', 'Perplexity', 'uncanny-automator' ),
			'input_type'      => 'text',
			'required'        => false,
			'description'     => esc_html_x( 'A list of domains to limit search results to. Currently limited to 10 domains for Allowlisting and Denylisting. For Denylisting, add a - at the beginning of the domain string. Separate multiple domains with commas.', 'Perplexity', 'uncanny-automator' ),
			'relevant_tokens' => array(),
		);

		// Return related questions field.
		$return_related_questions_field = array(
			'option_code'     => 'RETURN_RELATED_QUESTIONS',
			'label'           => esc_attr_x( 'Return related questions', 'Perplexity', 'uncanny-automator' ),
			'description'     => esc_html_x( 'Determines whether related questions should be returned. "No" by default.', 'Perplexity', 'uncanny-automator' ),
			'input_type'      => 'checkbox',
			'required'        => false,
			'is_toggle'       => true,
			'relevant_tokens' => array(),
		);

		// Temperature field
		$temperature_field = array(
			'option_code'     => 'TEMPERATURE',
			'label'           => esc_attr_x( 'Temperature', 'Perplexity', 'uncanny-automator' ),
			'input_type'      => 'text',
			'placeholder'     => '0.2',
			'default'         => '0.2',
			'description'     => esc_html_x( 'The amount of randomness in the response, valued between 0 and 2. Lower values (e.g., 0.1) make the output more focused, deterministic, and less creative. Higher values (e.g., 1.5) make the output more random and creative. Use lower values for factual/information retrieval tasks and higher values for creative applications.', 'Perplexity', 'uncanny-automator' ),
			'relevant_tokens' => array(),
		);

		// Max length field
		$max_tokens_field = array(
			'option_code'     => 'MAX_TOKEN',
			'label'           => esc_attr_x( 'Maximum tokens', 'Perplexity', 'uncanny-automator' ),
			'input_type'      => 'text',
			'placeholder'     => '2048',
			'default'         => '2048',
			// phpcs:ignore Uncanny_Automator.Strings.SentenceCase.PotentialCaseIssue
			'description'     => esc_html_x( "The maximum number of completion tokens returned by the API. Controls the length of the model's response. If the response would exceed this limit, it will be truncated. Higher values allow for longer responses but may increase processing time and costs.", 'Perplexity', 'uncanny-automator' ),
			'relevant_tokens' => array(),
		);

		// System message field
		$system_message_field = array(
			'option_code'     => 'SYSTEM_CONTENT',
			'label'           => esc_attr_x( 'System message', 'Perplexity', 'uncanny-automator' ),
			'description'     => esc_attr_x( 'Add context or instructions to have Perplexity respond with those details in mind.', 'Perplexity', 'uncanny-automator' ),
			'input_type'      => 'textarea',
			'required'        => false,
			'relevant_tokens' => array(),
		);

		// Prompt field
		$prompt_field = array(
			'option_code'       => $this->get_action_meta(),
			'label'             => esc_attr_x( 'Prompt', 'Perplexity', 'uncanny-automator' ),
			'input_type'        => 'textarea',
			'supports_markdown' => true,
			'required'          => true,
			'relevant_tokens'   => array(),
		);

		return array(
			$model_field,
			$search_domain_filter_field,
			$return_related_questions_field,
			$temperature_field,
			$max_tokens_field,
			$system_message_field,
			$prompt_field,
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

		$temperature              = $this->get_parsed_meta_value( 'TEMPERATURE', 0.2 );
		$max_tokens               = $this->get_parsed_meta_value( 'MAX_TOKEN', '1024' );
		$model                    = $this->get_parsed_meta_value( 'MODEL', '' );
		$system_content           = $this->get_parsed_meta_value( 'SYSTEM_CONTENT', '' );
		$prompt                   = $this->get_parsed_meta_value( $this->get_action_meta(), '' );
		$search_domain_filter     = $this->get_parsed_meta_value( 'SEARCH_DOMAIN_FILTER', '' );
		$return_related_questions = $this->get_parsed_meta_value( 'RETURN_RELATED_QUESTIONS', 'false' );

		if ( ! empty( $search_domain_filter ) ) {
			$search_domain_filter = array_map( 'trim', explode( ',', $search_domain_filter ) );
		} else {
			$search_domain_filter = null;
		}

		if ( empty( $temperature ) ) {
			$temperature = 0.2;
		}

		$provider        = $this->get_provider( 'PERPLEXITY' );
		$payload_builder = $this->get_payload_builder( $provider );

		$messages = $this->create_simple_message( $system_content, $prompt );

		// Build the payload
		$payload_builder = $payload_builder
			->endpoint( 'https://api.perplexity.ai/chat/completions' )
			->model( $model )
			->temperature( (float) $temperature )
			->max_tokens( (int) $max_tokens )
			->messages( $messages )
			->body( 'return_related_questions', 'true' === $return_related_questions );

		// Only add search_domain_filter if it's a valid non-empty array
		if ( ! empty( $search_domain_filter ) && is_array( $search_domain_filter ) ) {
			$payload_builder = $payload_builder->body( 'search_domain_filter', $search_domain_filter );
		}

		$payload = $payload_builder->json_content()->build();

		// Send request and get response.
		$response    = $provider->send_request( $payload );
		$ai_response = $provider->parse_response( $response );

		// Process tokens.
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
		$raw      = $ai_response->get_raw();

		// Create token values
		$token_values = array(
			'RESPONSE'                => $content,
			'CITATIONS'               => $this->format_citations( $raw ),
			'SEARCH_RESULTS'          => $this->format_search_results( $raw ),
			'RELATED_QUESTIONS'       => $this->format_related_questions( $raw ),
			'USAGE_PROMPT_TOKENS'     => $metadata['prompt_tokens'] ?? null,
			'USAGE_COMPLETION_TOKENS' => $metadata['completion_tokens'] ?? null,
			'USAGE_TOTAL_TOKENS'      => $metadata['total_tokens'] ?? null,
			'MODEL_USED'              => $metadata['model'] ?? null,
		);

		$this->hydrate_tokens( $token_values );

		return $this;
	}

	/**
	 * Format search results into readable string.
	 *
	 * @param array $raw
	 *
	 * @return string
	 */
	private function format_search_results( $raw ) {

		$search_results = $raw['search_results'] ?? array();

		if ( empty( $search_results ) || ! is_array( $search_results ) ) {
			return '';
		}

		$formatted_results = array();

		foreach ( $search_results as $result ) {
			$formatted_results[] = $this->format_single_search_result( $result );
		}

		return implode( "\n", $formatted_results );
	}

	/**
	 * Format single search result into readable string.
	 *
	 * @param array $result
	 *
	 * @return string
	 */
	private function format_single_search_result( $result ) {

		$title = $result['title'] ?? '';
		$url   = $result['url'] ?? '';
		$date  = $result['date'] ?? '';

		$formatted_result = $title;

		if ( ! empty( $url ) ) {
			$formatted_result .= ' (' . $url . ')';
		}

		if ( ! empty( $date ) ) {
			$formatted_result .= ' - ' . $date;
		}

		return $formatted_result;
	}

	/**
	 * Format related questions into readable string.
	 *
	 * @param array $raw
	 *
	 * @return string
	 */
	private function format_related_questions( $raw ) {

		$related_questions = $raw['related_questions'] ?? array();

		if ( empty( $related_questions ) || ! is_array( $related_questions ) ) {
			return '';
		}

		return implode( ', ', $related_questions );
	}

	/**
	 * Format citations into readable string.
	 *
	 * @param array $raw
	 *
	 * @return string
	 */
	private function format_citations( $raw ) {

		$citations = $raw['citations'] ?? array();

		if ( empty( $citations ) || ! is_array( $citations ) ) {
			return '';
		}

		return implode( ', ', $citations );
	}
}
