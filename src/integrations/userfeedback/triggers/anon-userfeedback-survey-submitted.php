<?php

namespace Uncanny_Automator;

use Uncanny_Automator\Recipe;

/**
 * Class Automator_UserFeedback_Trigger
 */
class ANON_USERFEEDBACK_SURVEY_SUBMITTED {
	use Recipe\Triggers;

	/**
	 * Automator_UserFeedback_Trigger constructor.
	 */
	public function __construct() {
		$this->setup_trigger();
	}

	/**
	 * Setup integration
	 */
	protected function setup_trigger() {
		$this->set_integration( 'USERFEEDBACK' ); // Or UOA if you want to show it under Automator integration
		$this->set_trigger_code( 'ANON_USERFEEDBACK_SURVEY_SUBMITTED' );
		$this->set_trigger_meta( 'UFSURVEY' );
		$this->set_trigger_type( 'anonymous' );
		$this->set_is_login_required( false );
		$this->set_sentence( sprintf( esc_attr__( 'A visitor submits {{a survey:%1$s}}', 'uncanny-automator' ), $this->trigger_meta ) );
		$this->set_readable_sentence( esc_attr__( 'A visitor submits {{a survey}}', 'uncanny-automator' ) );
		// The action hook to attach this trigger into.
		$this->set_action_hook( 'userfeedback_survey_response' );
		$this->set_action_args_count( 3 );
		$this->set_trigger_tokens(
			array(
				array(
					'tokenId'         => 'USERFEEDBACK_SURVEY_ID',
					'tokenIdentifier' => $this->get_trigger_code(),
					'tokenName'       => __( 'Survey ID', 'uncanny-automator' ),
					'tokenType'       => 'text',
				),
				array(
					'tokenId'         => 'USERFEEDBACK_SURVEY_TITLE',
					'tokenIdentifier' => $this->get_trigger_code(),
					'tokenName'       => __( 'Survey title', 'uncanny-automator' ),
					'tokenType'       => 'text',
				),
				array(
					'tokenId'         => 'USERFEEDBACK_SURVEY_RESPONSE',
					'tokenIdentifier' => $this->get_trigger_code(),
					'tokenName'       => __( 'Survey response', 'uncanny-automator' ),
					'tokenType'       => 'text',
				),
				array(
					'tokenId'         => 'USERFEEDBACK_SURVEY_RESPONSE_JSON',
					'tokenIdentifier' => $this->get_trigger_code(),
					'tokenName'       => __( 'Survey response (JSON)', 'uncanny-automator' ),
					'tokenType'       => 'text',
				),
				array(
					'tokenId'         => 'USERFEEDBACK_SURVEY_USER_IP',
					'tokenIdentifier' => $this->get_trigger_code(),
					'tokenName'       => __( 'User IP address', 'uncanny-automator' ),
					'tokenType'       => 'text',
				),
				array(
					'tokenId'         => 'USERFEEDBACK_SURVEY_USER_BROWSER',
					'tokenIdentifier' => $this->get_trigger_code(),
					'tokenName'       => __( 'User browser', 'uncanny-automator' ),
					'tokenType'       => 'text',
				),
				array(
					'tokenId'         => 'USERFEEDBACK_SURVEY_USER_OS',
					'tokenIdentifier' => $this->get_trigger_code(),
					'tokenName'       => __( 'User OS', 'uncanny-automator' ),
					'tokenType'       => 'text',
				),
				array(
					'tokenId'         => 'USERFEEDBACK_SURVEY_USER_DEVICE',
					'tokenIdentifier' => $this->get_trigger_code(),
					'tokenName'       => __( 'User device', 'uncanny-automator' ),
					'tokenType'       => 'text',
				),
			)
		);
		$this->set_token_parser( array( $this, 'hydrate_tokens' ) );
		$this->set_options_callback( array( $this, 'load_options' ) ); // only load in Recipe UI instead of each page
		$this->register_trigger();
	}


	/**
	 * Populate the dropdown for the trigger options.
	 * @return array
	 */
	public function load_options() {
		global $wpdb;
		$surveys = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}userfeedback_surveys WHERE status = %s", 'publish' ) );
		$options = array( '-1' => esc_html( __( 'Any survey', 'uncanny-automator' ) ) );
		foreach ( $surveys as $survey ) {
			$options[ $survey->id ] = $survey->title;
		}

		return Automator()->utilities->keep_order_of_options(
			array(
				'options' => array(
					Automator()->helpers->recipe->field->select(
						array(
							'input_type'            => 'select',
							'option_code'           => $this->trigger_meta,
							/* translators: HTTP request method */
							'label'                 => esc_html( __( 'Survey', 'uncanny-automator' ) ),
							'required'              => true,
							'supports_custom_value' => false,
							'options'               => $options,
						)
					),
				),
			)
		);
	}

	/**
	 * Save the token data for the trigger.
	 *
	 * @param $args
	 * @param $args
	 */
	public function save_token_data( $args, $trigger ) {
		global $wpdb;
		if ( isset( $args['trigger_args'] ) ) {
			$trigger_log_entry = $args['trigger_entry'];

			$survey_id   = $args['trigger_args'][0];
			$response_id = $args['trigger_args'][1];

			$survey          = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}userfeedback_surveys WHERE id = %d", $survey_id ) );
			$survey_response = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}userfeedback_survey_responses WHERE id = %d", $response_id ) );

			$survey_questions = array_map(
				function ( $question ) {
					$ret[ $question->id ] = $question->title;

					return $ret;
				},
				json_decode( $survey->questions )
			)[0];

			$response_answers = array_map(
				function ( $answer ) use ( $survey_questions ) {
					$question = $survey_questions[ $answer->question_id ];
					$answer   = $answer->value;

					return array(
						'string' => "{$question}: {$answer}",
						'data'   => array( $question => $answer ),
					);
				},
				json_decode( $survey_response->answers )
			)[0];

			$user_ip      = $survey_response->user_ip;
			$user_browser = $survey_response->user_browser;
			$user_os      = $survey_response->user_os;
			$user_device  = $survey_response->user_device;

			$trigger_meta       = $this->get_trigger_meta();
			$post_meta_key      = "{$trigger_meta}_readable";
			$trigger_meta_value = get_post_meta( $this->get_trigger_to_match(), $post_meta_key, true );
			//$token_values[ $trigger_meta ] = $trigger_meta_value;

			$token_values = array(
				'UFSURVEY'                          => $trigger_meta_value,
				'USERFEEDBACK_SURVEY_ID'            => $survey_id,
				'USERFEEDBACK_SURVEY_TITLE'         => $survey->title,
				'USERFEEDBACK_SURVEY_RESPONSE'      => $response_answers['string'],
				'USERFEEDBACK_SURVEY_RESPONSE_JSON' => json_encode( $response_answers['data'] ),
				'USERFEEDBACK_SURVEY_USER_IP'       => $user_ip,
				'USERFEEDBACK_SURVEY_USER_BROWSER'  => $user_browser,
				'USERFEEDBACK_SURVEY_USER_OS'       => $user_os,
				'USERFEEDBACK_SURVEY_USER_DEVICE'   => $user_device,
			);

			foreach ( $token_values as $key => $value ) {
				Automator()->db->token->save( $key, $value, $trigger_log_entry );
			}
		}
	}


	/**
	 * Return saved token data for the trigger.
	 *
	 * @param $args
	 * @param $trigger
	 *
	 * @return string
	 */
	public function hydrate_tokens( $args, $trigger ) {
		if ( isset( $trigger['replace_args']['pieces'][2] ) ) {
			return Automator()->db->token->get( $trigger['replace_args']['pieces'][2], $trigger['args'] );
		}

		return '';
	}

	/**
	 * Run validation functions on the trigger.
	 * @return bool
	 */
	public function validate_trigger( ...$args ) {
		return true;
	}

	/**
	 * Prepare the trigger to run.
	 *
	 * @param mixed ...$args
	 */
	protected function prepare_to_run( ...$args ) {
		$this->set_conditional_trigger( true );
	}

	/**
	 * Validate trigger conditions.
	 *
	 * @param ...$args
	 *
	 * @return array
	 */
	public function validate_conditions( ...$args ) {
		list( $userfeedback_survey_id ) = $args[0];

		return $this->find_all( $this->trigger_recipes() )
					->where( array( $this->get_trigger_meta() ) )
					->match( array( $userfeedback_survey_id ) )
					->format( array( 'intval' ) )
					->get();
	}

	/**
	 * Check if the trigger should continue for anonymous users.
	 *
	 * @param ...$args
	 *
	 * @return bool
	 */
	public function do_continue_anon_trigger( ...$args ) {
		return true;
	}
}
