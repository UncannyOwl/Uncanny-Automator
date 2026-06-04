<?php

namespace Uncanny_Automator\Integrations\Userfeedback;

use Uncanny_Automator\Recipe\Abstract_Helpers;

/**
 * Class Userfeedback_Helpers
 *
 * @package Uncanny_Automator
 */
class Userfeedback_Helpers extends Abstract_Helpers {

	/**
	 * Get all published UserFeedback surveys as dropdown options.
	 *
	 * @param bool $include_any Whether to include the "Any survey" sentinel.
	 *
	 * @return array
	 */
	public function get_surveys( $include_any = true ) {

		global $wpdb;

		$options = array();

		if ( true === $include_any ) {
			$options[] = array(
				'text'  => esc_html_x( 'Any survey', 'UserFeedback', 'uncanny-automator' ),
				'value' => '-1',
			);
		}

		$surveys = $wpdb->get_results(
			$wpdb->prepare( "SELECT id, title FROM {$wpdb->prefix}userfeedback_surveys WHERE status = %s", 'publish' )
		);

		if ( empty( $surveys ) ) {
			return $options;
		}

		foreach ( $surveys as $survey ) {
			$options[] = array(
				'text'  => $survey->title,
				'value' => (string) $survey->id,
			);
		}

		return $options;
	}

	/**
	 * Remote-data handler: Load all surveys (with "Any survey").
	 *
	 * Reachable via `POST /wp-json/uap/v2/remote-data/userfeedback/surveys`.
	 *
	 * @param Remote_Data_Request $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_surveys( $request ): array {
		unset( $request );

		return $this->remote_data_success( $this->get_surveys( true ) );
	}

	/**
	 * Fetch a survey row by ID.
	 *
	 * @param int $survey_id The survey ID.
	 *
	 * @return object|null
	 */
	public function get_survey( $survey_id ) {

		global $wpdb;

		return $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$wpdb->prefix}userfeedback_surveys WHERE id = %d", $survey_id )
		);
	}

	/**
	 * Fetch a survey response row by ID.
	 *
	 * @param int $response_id The response ID.
	 *
	 * @return object|null
	 */
	public function get_survey_response( $response_id ) {

		global $wpdb;

		return $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$wpdb->prefix}userfeedback_survey_responses WHERE id = %d", $response_id )
		);
	}

	/**
	 * Build the readable response data for the survey tokens.
	 *
	 * @param object|null $survey   The survey row.
	 * @param object|null $response The survey response row.
	 *
	 * @return array{string:string,json:string}
	 */
	public function build_response_strings( $survey, $response ) {

		$out = array(
			'string' => '',
			'json'   => '',
		);

		if ( null === $survey || null === $response ) {
			return $out;
		}

		$questions_raw = json_decode( $survey->questions );
		$answers_raw   = json_decode( $response->answers );

		if ( ! is_array( $questions_raw ) || ! is_array( $answers_raw ) ) {
			return $out;
		}

		$survey_questions = array();
		foreach ( $questions_raw as $question ) {
			$survey_questions[ $question->id ] = $question->title;
		}

		$strings = array();
		$data    = array();

		foreach ( $answers_raw as $answer ) {

			$question_title = isset( $survey_questions[ $answer->question_id ] ) ? $survey_questions[ $answer->question_id ] : '';
			$value          = $answer->value;

			if ( is_array( $value ) ) {
				$value = join( ', ', $value );
			}

			$strings[] = $question_title . ': ' . $value;
			$data[]    = wp_json_encode( array( $question_title => $value ) );
		}

		$out['string'] = join( ', ', $strings );
		$out['json']   = join( ', ', $data );

		return $out;
	}
}
