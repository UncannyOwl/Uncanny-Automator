<?php

namespace Uncanny_Automator\Integrations\Userfeedback;

use Uncanny_Automator\Recipe\Trigger;
use Uncanny_Automator\Recipe\Trigger_Definition;

/**
 * Class ANON_USERFEEDBACK_SURVEY_SUBMITTED
 *
 * @property Userfeedback_Helpers $item_helpers
 *
 * @package Uncanny_Automator
 */
class ANON_USERFEEDBACK_SURVEY_SUBMITTED extends Trigger {

	/**
	 * Static definition — opts the trigger into lazy loading.
	 *
	 * @return Trigger_Definition
	 */
	public static function definition() {
		return self::new_definition( 'ANON_USERFEEDBACK_SURVEY_SUBMITTED', 'USERFEEDBACK' )
			->trigger_meta( 'UFSURVEY' )
			->trigger_type( 'anonymous' )
			->hook( 'userfeedback_survey_response', 10, 3 );
	}

	/**
	 * Setup trigger.
	 *
	 * @return void
	 */
	protected function setup_trigger() {
		// integration / code / trigger_meta / trigger_type / hook are auto-applied from definition().

		$this->set_is_login_required( false );

		// translators: 1: Survey
		$this->set_sentence( sprintf( esc_html_x( 'A visitor submits {{a survey:%1$s}}', 'UserFeedback', 'uncanny-automator' ), $this->get_trigger_meta() ) );
		$this->set_readable_sentence( esc_html_x( 'A visitor submits {{a survey}}', 'UserFeedback', 'uncanny-automator' ) );
	}

	/**
	 * Trigger options.
	 *
	 * @return array
	 */
	public function options() {

		return array(
			array(
				'option_code' => $this->get_trigger_meta(),
				'label'       => esc_html_x( 'Survey', 'UserFeedback', 'uncanny-automator' ),
				'input_type'  => 'select',
				'required'    => true,
				'options'     => array(),
				'remote_data' => $this->item_helpers->remote_data_load_config( 'surveys' ),
			),
		);
	}

	/**
	 * Define trigger tokens.
	 *
	 * @param array $trigger The trigger settings.
	 * @param array $tokens  Existing tokens.
	 *
	 * @return array
	 */
	public function define_tokens( $trigger, $tokens ) {
		return array(
			array(
				'tokenId'   => 'USERFEEDBACK_SURVEY_ID',
				'tokenName' => esc_html_x( 'Survey ID', 'UserFeedback', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'USERFEEDBACK_SURVEY_TITLE',
				'tokenName' => esc_html_x( 'Survey title', 'UserFeedback', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'USERFEEDBACK_SURVEY_RESPONSE',
				'tokenName' => esc_html_x( 'Survey response', 'UserFeedback', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'USERFEEDBACK_SURVEY_RESPONSE_JSON',
				'tokenName' => esc_html_x( 'Survey response (JSON)', 'UserFeedback', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'USERFEEDBACK_SURVEY_USER_IP',
				'tokenName' => esc_html_x( 'User IP address', 'UserFeedback', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'USERFEEDBACK_SURVEY_USER_BROWSER',
				'tokenName' => esc_html_x( 'User browser', 'UserFeedback', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'USERFEEDBACK_SURVEY_USER_OS',
				'tokenName' => esc_html_x( 'User OS', 'UserFeedback', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'USERFEEDBACK_SURVEY_USER_DEVICE',
				'tokenName' => esc_html_x( 'User device', 'UserFeedback', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
		);
	}

	/**
	 * Validate whether the trigger should fire.
	 *
	 * @param array $trigger   The trigger settings.
	 * @param array $hook_args The arguments from the WP hook.
	 *
	 * @return bool
	 */
	public function validate( $trigger, $hook_args ) {

		if ( ! isset( $trigger['meta'][ $this->get_trigger_meta() ] ) ) {
			return false;
		}

		if ( ! isset( $hook_args[0] ) ) {
			return false;
		}

		$selected_survey_id = (int) $trigger['meta'][ $this->get_trigger_meta() ];
		$fired_survey_id    = (int) $hook_args[0];

		// "Any survey".
		if ( -1 === $selected_survey_id ) {
			return true;
		}

		return $fired_survey_id === $selected_survey_id;
	}

	/**
	 * Hydrate token values from hook arguments.
	 *
	 * @param array $trigger   The completed trigger settings.
	 * @param array $hook_args The hook arguments.
	 *
	 * @return array
	 */
	public function hydrate_tokens( $trigger, $hook_args ) {

		$survey_id   = isset( $hook_args[0] ) ? (int) $hook_args[0] : 0;
		$response_id = isset( $hook_args[1] ) ? (int) $hook_args[1] : 0;

		$survey   = $this->item_helpers->get_survey( $survey_id );
		$response = $this->item_helpers->get_survey_response( $response_id );

		$response_strings = $this->item_helpers->build_response_strings( $survey, $response );

		$survey_title = null !== $survey && isset( $survey->title ) ? $survey->title : '';

		return array(
			'UFSURVEY'                          => $survey_title,
			'USERFEEDBACK_SURVEY_ID'            => $survey_id,
			'USERFEEDBACK_SURVEY_TITLE'         => $survey_title,
			'USERFEEDBACK_SURVEY_RESPONSE'      => $response_strings['string'],
			'USERFEEDBACK_SURVEY_RESPONSE_JSON' => $response_strings['json'],
			'USERFEEDBACK_SURVEY_USER_IP'       => null !== $response ? $response->user_ip : '',
			'USERFEEDBACK_SURVEY_USER_BROWSER'  => null !== $response ? $response->user_browser : '',
			'USERFEEDBACK_SURVEY_USER_OS'       => null !== $response ? $response->user_os : '',
			'USERFEEDBACK_SURVEY_USER_DEVICE'   => null !== $response ? $response->user_device : '',
		);
	}
}
